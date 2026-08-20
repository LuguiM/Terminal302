<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\StoreTicketRequest;
use App\Http\Resources\TicketResource;
use App\Http\Resources\TipoEnvioResource;
use App\Http\Resources\VentaHorarioResource;
use App\Models\Estado;
use App\Models\ProcesamientoEstado;
use App\Models\Ticket;
use App\Models\TicketPlantilla;
use App\Models\TipoEnvio;
use App\Models\VentaHorario;
use App\Services\TicketProcessingEventService;
use App\Services\TicketRenderService;
use App\Services\VentaHorarioLifecycleService;
use App\Support\ApiResponse;
use App\Support\StorageUrl;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class VendedorTicketController extends Controller
{
    private const OPERATION_TIMEZONE = 'America/El_Salvador';

    public function tipoEnvios(): JsonResponse
    {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $tipoEnvios = TipoEnvio::query()
            ->where('estado_id', $activeStatus->id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'tipo_envios' => TipoEnvioResource::collection($tipoEnvios),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 50);
        $filterResponse = $this->validateProcessingStateFilter($request);

        if ($filterResponse) {
            return $filterResponse;
        }

        $tickets = Ticket::query()
            ->with([
                'estado',
                'tipoEnvio',
                'procesamientoEstado',
                'ticketPlantilla',
                'ventaHorario.horario.ruta',
                'ventaHorario.horario.operador',
                'ventaHorario.horario.bus',
                'vendedor',
            ])
            ->where('vendedor_id', $request->user()?->id)
            ->when($request->filled('venta_horario_id'), fn ($query) => $query->where('venta_horario_id', $request->integer('venta_horario_id')))
            ->when($request->filled('estado_id'), fn ($query) => $query->where('estado_id', $request->integer('estado_id')))
            ->when($request->filled('codigo_ticket'), fn ($query) => $query->whereRaw(
                'LOWER(codigo_ticket) LIKE ?',
                ['%'.mb_strtolower($request->string('codigo_ticket')->toString()).'%'],
            ))
            ->when($request->filled('tipo_envio_id'), fn ($query) => $query->where('tipo_envio_id', $request->integer('tipo_envio_id')))
            ->when($request->filled('fecha'), fn ($query) => $query->whereDate('created_at', $request->string('fecha')->toString()))
            ->when($request->filled('procesamiento_estado_id'), fn ($query) => $query->where('procesamiento_estado_id', $request->integer('procesamiento_estado_id')))
            ->when($request->filled('processing_status_name'), fn ($query) => $query->whereHas(
                'procesamientoEstado',
                fn ($processingQuery) => $processingQuery->whereRaw('LOWER(nombre) = ?', [mb_strtolower($request->string('processing_status_name')->toString())]),
            ))
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::paginated($tickets, 'tickets', TicketResource::class);
    }

    public function store(
        StoreTicketRequest $request,
        TicketProcessingEventService $ticketProcessingEventService,
        TicketRenderService $ticketRenderService,
        VentaHorarioLifecycleService $ventaHorarioLifecycleService,
    ): JsonResponse {
        $validated = $request->validated();
        $vendedor = $request->user();
        $generatedTickets = collect();

        $result = DB::transaction(function () use ($validated, $vendedor, &$generatedTickets, $ventaHorarioLifecycleService): JsonResponse|array {
            $activeStatus = Estado::activo();

            if (! $activeStatus) {
                return $this->missingStatusResponse('activo');
            }

            $issuedStatus = $this->issuedStatus();

            if (! $issuedStatus) {
                return $this->missingStatusResponse('emitido');
            }

            $tipoEnvio = TipoEnvio::query()
                ->whereKey($validated['tipo_envio_id'])
                ->where('estado_id', $activeStatus->id)
                ->first();

            if (! $tipoEnvio) {
                return response()->json([
                    'message' => 'El tipo de envio seleccionado no esta activo.',
                ], 422);
            }

            $ticketPlantilla = TicketPlantilla::query()
                ->where('es_predeterminada', true)
                ->where('estado_id', $activeStatus->id)
                ->first();

            if (! $ticketPlantilla) {
                return response()->json([
                    'message' => 'No existe una plantilla de ticket predeterminada activa.',
                ], 422);
            }

            $pendingProcessingStatus = null;
            $failedProcessingStatus = null;

            if ($tipoEnvio->isDigital()) {
                $pendingProcessingStatus = $this->processingStatus(ProcesamientoEstado::PENDING, $activeStatus);
                $failedProcessingStatus = $this->processingStatus(ProcesamientoEstado::FAILED, $activeStatus);

                if (! $pendingProcessingStatus) {
                    return $this->missingProcessingStatusResponse(ProcesamientoEstado::PENDING);
                }

                if (! $failedProcessingStatus) {
                    return $this->missingProcessingStatusResponse(ProcesamientoEstado::FAILED);
                }
            }

            $ventaHorario = VentaHorario::query()
                ->lockForUpdate()
                ->with(['horario.ruta', 'horario.operador', 'horario.bus', 'horario.dia', 'estado', 'cerradaPor'])
                ->find($validated['venta_horario_id']);

            if (! $ventaHorario) {
                return response()->json([
                    'message' => 'La venta de horario solicitada no existe.',
                ], 404);
            }

            $validationResponse = $this->validateVentaHorario(
                $ventaHorario,
                $activeStatus,
                $ventaHorarioLifecycleService,
                $vendedor?->id,
            );

            if ($validationResponse) {
                return $validationResponse;
            }

            $cantidad = (int) $validated['cantidad'];
            $capacidad = (int) $ventaHorario->horario->bus->capacidad;
            $cuposDisponibles = max($capacidad - (int) $ventaHorario->total_tickets_vendidos, 0);
            $sobreventaPermitida = (bool) $ventaHorario->horario->sobreventa_permitida;

            if ($cuposDisponibles <= 0 && ! $sobreventaPermitida) {
                $this->closeSaleByCapacity($ventaHorario, $vendedor?->id);

                return response()->json([
                    'message' => 'La capacidad del bus fue alcanzada y la venta fue cerrada.',
                    'venta_horario' => new VentaHorarioResource($ventaHorario->fresh(['horario.ruta', 'horario.operador', 'horario.bus', 'estado', 'cerradaPor'])),
                ], 409);
            }

            if ($cantidad > $cuposDisponibles && ! $sobreventaPermitida) {
                if ($cuposDisponibles <= 0) {
                    $this->closeSaleByCapacity($ventaHorario, $vendedor?->id);
                }

                return response()->json([
                    'message' => 'La cantidad solicitada supera los cupos disponibles y la sobreventa no esta permitida.',
                ], 422);
            }

            $ticketsNormales = min($cantidad, $cuposDisponibles);
            $ticketsSobreventa = max($cantidad - $ticketsNormales, 0);
            $firstTicketNumber = (int) $ventaHorario->total_tickets_vendidos + 1;

            for ($index = 0; $index < $cantidad; $index++) {
                $generatedTickets->push(Ticket::query()->create([
                    'venta_horario_id' => $ventaHorario->id,
                    'codigo_ticket' => $this->generateTicketCode(),
                    'vendedor_id' => $vendedor?->id,
                    'correo_destino' => $tipoEnvio->isDigital()
                        ? ($validated['correo_destino'] ?? null)
                        : null,
                    'telefono_destino' => $validated['telefono_destino'] ?? null,
                    'numero_asiento' => $firstTicketNumber + $index,
                    'es_sobreventa' => $index >= $ticketsNormales,
                    'tipo_envio_id' => $tipoEnvio->id,
                    'estado_id' => $issuedStatus->id,
                    'qr_path' => null,
                    'ticket_plantilla_id' => $ticketPlantilla->id,
                    'ticket_image_path' => null,
                    'procesamiento_estado_id' => $pendingProcessingStatus?->id,
                    'processing_error' => null,
                    'processed_at' => null,
                    'processing_event_path' => null,
                ]));
            }

            $ventaHorario->forceFill([
                'total_tickets_vendidos' => (int) $ventaHorario->total_tickets_vendidos + $cantidad,
                'total_tickets_sobreventa' => (int) $ventaHorario->total_tickets_sobreventa + $ticketsSobreventa,
            ]);

            if (! $sobreventaPermitida && ((int) $ventaHorario->total_tickets_vendidos >= $capacidad)) {
                $ventaHorario->forceFill([
                    'venta_cerrada' => true,
                    'cerrada_por' => $vendedor?->id,
                    'fecha_cierre' => CarbonImmutable::now(self::OPERATION_TIMEZONE),
                    'motivo_cierre' => 'Capacidad alcanzada.',
                ]);
            }

            $ventaHorario->save();

            $generatedTickets = Ticket::query()
                ->with(['estado', 'tipoEnvio', 'procesamientoEstado', 'ticketPlantilla', 'ventaHorario', 'vendedor'])
                ->whereIn('id', $generatedTickets->pluck('id'))
                ->orderBy('id')
                ->get();

            return [
                'venta_horario' => $ventaHorario->fresh(['horario.ruta', 'horario.operador', 'horario.bus', 'estado', 'cerradaPor']),
                'tickets_normales' => $ticketsNormales,
                'tickets_sobreventa' => $ticketsSobreventa,
            ];
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $generatedTickets = $generatedTickets
            ->map(fn (Ticket $ticket): Ticket => $ticketRenderService->render($ticket))
            ->values();

        $isDigital = (bool) $generatedTickets->first()?->tipoEnvio?->isDigital();

        if ($isDigital) {
            $activeStatus = Estado::activo();

            if (! $activeStatus) {
                return $this->missingStatusResponse('activo');
            }

            $pendingProcessingStatus = $this->processingStatus(ProcesamientoEstado::PENDING, $activeStatus);
            $failedProcessingStatus = $this->processingStatus(ProcesamientoEstado::FAILED, $activeStatus);

            if (! $pendingProcessingStatus) {
                return $this->missingProcessingStatusResponse(ProcesamientoEstado::PENDING);
            }

            if (! $failedProcessingStatus) {
                return $this->missingProcessingStatusResponse(ProcesamientoEstado::FAILED);
            }

            $this->publishDigitalProcessingEvents(
                $generatedTickets,
                $ticketProcessingEventService,
                $pendingProcessingStatus,
                $failedProcessingStatus,
            );
            $generatedTickets = $this->reloadTickets($generatedTickets->pluck('id'));
        }

        return response()->json([
            'message' => 'Tickets generados correctamente.',
            'impresion' => $isDigital ? null : [
                'tickets' => $generatedTickets->map(fn (Ticket $ticket): array => $this->printableTicketData($ticket))->values(),
            ],
            'resumen' => [
                'cantidad_solicitada' => (int) $validated['cantidad'],
                'cantidad_generada' => $generatedTickets->count(),
                'tickets_normales' => $result['tickets_normales'],
                'tickets_sobreventa' => $result['tickets_sobreventa'],
                'total_tickets_vendidos' => $result['venta_horario']->total_tickets_vendidos,
                'total_tickets_sobreventa' => $result['venta_horario']->total_tickets_sobreventa,
                'venta_cerrada' => (bool) $result['venta_horario']->venta_cerrada,
            ],
        ], 201);
    }

    public function entregas(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 50);
        $filterResponse = $this->validateProcessingStateFilter($request);

        if ($filterResponse) {
            return $filterResponse;
        }

        $tickets = Ticket::query()
            ->with(['estado', 'tipoEnvio', 'procesamientoEstado', 'ticketPlantilla', 'ventaHorario', 'vendedor'])
            ->where('vendedor_id', $request->user()?->id)
            ->whereHas('tipoEnvio', fn ($query) => $query->whereRaw('LOWER(nombre) = ?', [TipoEnvio::DIGITAL]))
            ->when($request->filled('procesamiento_estado_id'), fn ($query) => $query->where('procesamiento_estado_id', $request->integer('procesamiento_estado_id')))
            ->when($request->filled('processing_status_name'), fn ($query) => $query->whereHas(
                'procesamientoEstado',
                fn ($processingQuery) => $processingQuery->whereRaw('LOWER(nombre) = ?', [mb_strtolower($request->string('processing_status_name')->toString())]),
            ))
            ->when($request->filled('venta_horario_id'), fn ($query) => $query->where('venta_horario_id', $request->integer('venta_horario_id')))
            ->when($request->filled('codigo_ticket'), fn ($query) => $query->where('codigo_ticket', $request->string('codigo_ticket')->toString()))
            ->when($request->filled('fecha'), fn ($query) => $query->whereDate('created_at', $request->string('fecha')->toString()))
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::paginated($tickets, 'tickets', TicketResource::class);
    }

    public function retryProcessing(
        Request $request,
        int $id,
        TicketProcessingEventService $ticketProcessingEventService,
    ): JsonResponse {
        $ticket = $this->findSellerTicket($id, $request);

        if ($ticket instanceof JsonResponse) {
            return $ticket;
        }

        if (! $ticket->tipoEnvio?->isDigital()) {
            return response()->json([
                'message' => 'El ticket no es digital y no requiere reprocesamiento.',
            ], 422);
        }

        if (! $ticket->procesamientoEstado) {
            return response()->json([
                'message' => 'El ticket no tiene estado de procesamiento asociado.',
            ], 422);
        }

        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $pendingProcessingStatus = $this->processingStatus(ProcesamientoEstado::PENDING, $activeStatus);
        $failedProcessingStatus = $this->processingStatus(ProcesamientoEstado::FAILED, $activeStatus);

        if (! $pendingProcessingStatus) {
            return $this->missingProcessingStatusResponse(ProcesamientoEstado::PENDING);
        }

        if (! $failedProcessingStatus) {
            return $this->missingProcessingStatusResponse(ProcesamientoEstado::FAILED);
        }

        if (! $ticket->procesamientoEstado->isPending() && ! $ticket->procesamientoEstado->isFailed()) {
            return response()->json([
                'message' => 'El ticket no se encuentra en un estado que permita reintentar el procesamiento.',
            ], 422);
        }

        try {
            $ticket->forceFill([
                'procesamiento_estado_id' => $pendingProcessingStatus->id,
                'processing_error' => null,
                'processed_at' => null,
            ]);
            $ticket->setRelation('procesamientoEstado', $pendingProcessingStatus);

            $eventPath = $ticketProcessingEventService->publish($ticket);
            $ticket->forceFill([
                'procesamiento_estado_id' => $pendingProcessingStatus->id,
                'processing_error' => null,
                'processed_at' => null,
                'processing_event_path' => $eventPath,
            ])->save();
        } catch (Throwable $exception) {
            $ticket->forceFill([
                'procesamiento_estado_id' => $failedProcessingStatus->id,
                'processing_error' => $exception->getMessage(),
                'processed_at' => null,
            ])->save();

            return response()->json([
                'message' => 'No se pudo reintentar el procesamiento del ticket.',
                'ticket' => new TicketResource($ticket->fresh($this->ticketRelations())),
            ], 500);
        }

        return response()->json([
            'message' => 'Procesamiento del ticket reintentado correctamente.',
            'ticket' => new TicketResource($ticket->fresh($this->ticketRelations())),
        ]);
    }

    public function print(Request $request, int $id): JsonResponse
    {
        $ticket = $this->findSellerTicket($id, $request);

        if ($ticket instanceof JsonResponse) {
            return $ticket;
        }

        return response()->json([
            'image_url' => StorageUrl::for($ticket->ticket_image_path),
            'print_url' => StorageUrl::for($ticket->ticket_image_path),
        ]);
    }

    public function templateImage(Request $request, int $id): JsonResponse|StreamedResponse
    {
        $ticket = $this->findSellerTicket($id, $request);

        if ($ticket instanceof JsonResponse) {
            return $ticket;
        }

        $template = $ticket->ticketPlantilla;

        if (! $template) {
            return response()->json([
                'message' => 'El ticket no tiene plantilla asociada.',
            ], 422);
        }

        if (! Storage::exists($template->image_path)) {
            return response()->json([
                'message' => 'El archivo de la plantilla no existe.',
            ], 404);
        }

        return Storage::download(
            $template->image_path,
            basename($template->image_path),
        );
    }

    public function image(Request $request, int $id): JsonResponse|StreamedResponse
    {
        $ticket = $this->findSellerTicket($id, $request);

        if ($ticket instanceof JsonResponse) {
            return $ticket;
        }

        if (! $ticket->ticket_image_path) {
            return response()->json([
                'message' => 'El ticket no tiene imagen generada.',
            ], 422);
        }

        if (! Storage::exists($ticket->ticket_image_path)) {
            return response()->json([
                'message' => 'El archivo del ticket no existe.',
            ], 404);
        }

        return Storage::download(
            $ticket->ticket_image_path,
            basename($ticket->ticket_image_path),
        );
    }

    private function publishDigitalProcessingEvents(
        Collection $tickets,
        TicketProcessingEventService $ticketProcessingEventService,
        ProcesamientoEstado $pendingProcessingStatus,
        ProcesamientoEstado $failedProcessingStatus,
    ): void {
        foreach ($tickets as $ticket) {
            try {
                $eventPath = $ticketProcessingEventService->publish($ticket);
                $ticket->forceFill([
                    'procesamiento_estado_id' => $pendingProcessingStatus->id,
                    'processing_error' => null,
                    'processed_at' => null,
                    'processing_event_path' => $eventPath,
                ])->save();

            } catch (Throwable $exception) {
                // La venta ya fue confirmada; el procesamiento digital queda reintentable por ticket.
                $ticket->forceFill([
                    'procesamiento_estado_id' => $failedProcessingStatus->id,
                    'processing_error' => $exception->getMessage(),
                    'processed_at' => null,
                ])->save();
            }
        }

    }

    private function findSellerTicket(int $id, Request $request): Ticket|JsonResponse
    {
        $ticket = Ticket::query()
            ->with($this->ticketRelations())
            ->find($id);

        if (! $ticket) {
            return response()->json([
                'message' => 'El ticket solicitado no existe.',
            ], 404);
        }

        if ((int) $ticket->vendedor_id !== (int) $request->user()?->id) {
            return response()->json([
                'message' => 'El ticket no pertenece al vendedor autenticado.',
            ], 403);
        }

        return $ticket;
    }

    private function validateProcessingStateFilter(Request $request): ?JsonResponse
    {
        if (! $request->filled('procesamiento_estado_id')) {
            return null;
        }

        $exists = ProcesamientoEstado::query()
            ->whereKey($request->integer('procesamiento_estado_id'))
            ->exists();

        if ($exists) {
            return null;
        }

        return response()->json([
            'message' => 'El estado de procesamiento seleccionado no existe.',
        ], 422);
    }

    /**
     * @return array<int, string>
     */
    private function ticketRelations(): array
    {
        return [
            'estado',
            'tipoEnvio',
            'procesamientoEstado',
            'ticketPlantilla',
            'ventaHorario.horario.ruta',
            'ventaHorario.horario.operador',
            'ventaHorario.horario.bus',
            'ventaHorario.horario.dia',
            'vendedor',
        ];
    }

    private function reloadTickets(Collection $ticketIds): Collection
    {
        return Ticket::query()
            ->with($this->ticketRelations())
            ->whereIn('id', $ticketIds)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function printableTicketData(Ticket $ticket): array
    {
        $ticket->loadMissing($this->ticketRelations());

        return [
            'id' => $ticket->id,
            'codigo_ticket' => $ticket->codigo_ticket,
            'image_url' => StorageUrl::for($ticket->ticket_image_path),
            'print_url' => StorageUrl::for($ticket->ticket_image_path),
        ];
    }

    private function validateVentaHorario(
        VentaHorario $ventaHorario,
        Estado $activeStatus,
        VentaHorarioLifecycleService $ventaHorarioLifecycleService,
        ?int $vendedorId,
    ): ?JsonResponse {
        if ((int) $ventaHorario->estado_id !== (int) $activeStatus->id) {
            return response()->json([
                'message' => 'La venta de horario no esta activa.',
            ], 422);
        }

        if ($ventaHorarioLifecycleService->isExpiredForToday($ventaHorario)) {
            $ventaHorarioLifecycleService->closeExpired($ventaHorario, $vendedorId);

            return response()->json([
                'message' => 'La hora de salida de este horario ya paso y la venta fue cerrada.',
            ], 409);
        }

        if ($ventaHorario->venta_cerrada) {
            return response()->json([
                'message' => 'La venta de horario ya esta cerrada.',
            ], 409);
        }

        if (! $ventaHorario->horario) {
            return response()->json([
                'message' => 'No se pudo obtener el horario asociado a la venta.',
            ], 422);
        }

        if (! $ventaHorario->horario->bus) {
            return response()->json([
                'message' => 'No se pudo obtener el bus asociado al horario.',
            ], 422);
        }

        if (! $ventaHorario->horario->ruta) {
            return response()->json([
                'message' => 'No se pudo obtener la ruta asociada al horario.',
            ], 422);
        }

        if (! $ventaHorario->horario->operador) {
            return response()->json([
                'message' => 'No se pudo obtener el operador asociado al horario.',
            ], 422);
        }

        if ((int) $ventaHorario->horario->operador->estado_id !== (int) $activeStatus->id) {
            return response()->json([
                'message' => 'El operador del horario esta desactivado.',
            ], 422);
        }

        return null;
    }

    private function closeSaleByCapacity(VentaHorario $ventaHorario, ?int $vendedorId): void
    {
        $ventaHorario->forceFill([
            'venta_cerrada' => true,
            'cerrada_por' => $vendedorId,
            'fecha_cierre' => CarbonImmutable::now(self::OPERATION_TIMEZONE),
            'motivo_cierre' => 'Capacidad alcanzada.',
        ])->save();
    }

    private function issuedStatus(): ?Estado
    {
        return Estado::query()
            ->whereRaw('LOWER(nombre) = ?', ['emitido'])
            ->first();
    }

    private function processingStatus(string $statusName, Estado $activeStatus): ?ProcesamientoEstado
    {
        return ProcesamientoEstado::query()
            ->where('estado_id', $activeStatus->id)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($statusName)])
            ->first();
    }

    private function generateTicketCode(): string
    {
        do {
            $code = 'TKT-'.now(self::OPERATION_TIMEZONE)->format('Ymd').'-'.Str::upper(Str::random(10));
        } while (Ticket::query()->where('codigo_ticket', $code)->exists());

        return $code;
    }

    private function missingStatusResponse(string $statusName): JsonResponse
    {
        return response()->json([
            'message' => "No se encontro el estado requerido: {$statusName}.",
        ], 500);
    }

    private function missingProcessingStatusResponse(string $statusName): JsonResponse
    {
        return response()->json([
            'message' => "No se encontro el estado de procesamiento requerido: {$statusName}.",
        ], 500);
    }
}
