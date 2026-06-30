<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\StoreTicketRequest;
use App\Http\Resources\TicketResource;
use App\Http\Resources\VentaHorarioResource;
use App\Mail\TicketsVendidosMail;
use App\Models\Estado;
use App\Models\Ticket;
use App\Models\TicketPlantilla;
use App\Models\TipoEnvio;
use App\Models\VentaHorario;
use App\Services\Tickets\WhatsAppTicketDeliveryService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class VendedorTicketController extends Controller
{
    private const OPERATION_TIMEZONE = 'America/El_Salvador';

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 50);

        $tickets = Ticket::query()
            ->with(['estado', 'tipoEnvio', 'ticketPlantilla', 'ventaHorario', 'vendedor'])
            ->where('vendedor_id', $request->user()?->id)
            ->when($request->filled('venta_horario_id'), fn ($query) => $query->where('venta_horario_id', $request->integer('venta_horario_id')))
            ->when($request->filled('estado_id'), fn ($query) => $query->where('estado_id', $request->integer('estado_id')))
            ->when($request->filled('codigo_ticket'), fn ($query) => $query->where('codigo_ticket', $request->string('codigo_ticket')->toString()))
            ->when($request->filled('tipo_envio_id'), fn ($query) => $query->where('tipo_envio_id', $request->integer('tipo_envio_id')))
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::paginated($tickets, 'tickets', TicketResource::class);
    }

    public function store(
        StoreTicketRequest $request,
        WhatsAppTicketDeliveryService $whatsAppTicketDeliveryService,
    ): JsonResponse {
        $validated = $request->validated();
        $vendedor = $request->user();
        $generatedTickets = collect();
        $mailVentaHorario = null;

        $result = DB::transaction(function () use ($validated, $vendedor, &$generatedTickets, &$mailVentaHorario): JsonResponse|array {
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

            $ventaHorario = VentaHorario::query()
                ->lockForUpdate()
                ->with(['horario.ruta', 'horario.operador', 'horario.bus', 'estado', 'cerradaPor'])
                ->find($validated['venta_horario_id']);

            if (! $ventaHorario) {
                return response()->json([
                    'message' => 'La venta de horario solicitada no existe.',
                ], 404);
            }

            $validationResponse = $this->validateVentaHorario($ventaHorario, $activeStatus);

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

            for ($index = 0; $index < $cantidad; $index++) {
                $generatedTickets->push(Ticket::query()->create([
                    'venta_horario_id' => $ventaHorario->id,
                    'codigo_ticket' => $this->generateTicketCode(),
                    'vendedor_id' => $vendedor?->id,
                    'correo_destino' => $tipoEnvio->isDigital()
                        ? ($validated['correo_destino'] ?? null)
                        : null,
                    'telefono_destino' => $validated['telefono_destino'] ?? null,
                    'numero_asiento' => null,
                    'es_sobreventa' => $index >= $ticketsNormales,
                    'tipo_envio_id' => $tipoEnvio->id,
                    'estado_id' => $issuedStatus->id,
                    'qr_path' => null,
                    'ticket_plantilla_id' => $ticketPlantilla->id,
                    'ticket_image_path' => null,
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
                ->with(['estado', 'tipoEnvio', 'ticketPlantilla', 'ventaHorario', 'vendedor'])
                ->whereIn('id', $generatedTickets->pluck('id'))
                ->orderBy('id')
                ->get();
            $mailVentaHorario = $ventaHorario->fresh(['horario.ruta', 'horario.operador', 'horario.bus', 'estado', 'cerradaPor']);

            return [
                'venta_horario' => $mailVentaHorario,
                'tickets_normales' => $ticketsNormales,
                'tickets_sobreventa' => $ticketsSobreventa,
            ];
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        if ($generatedTickets->first()?->tipoEnvio?->isDigital()) {
            Mail::to($validated['correo_destino'])->send(new TicketsVendidosMail($generatedTickets, $mailVentaHorario));
        }

        if (! empty($validated['telefono_destino'])) {
            $whatsAppTicketDeliveryService->prepare($validated['telefono_destino'], $generatedTickets);
        }

        return response()->json([
            'message' => 'Tickets generados correctamente.',
            'tipo_envio' => [
                'id' => $generatedTickets->first()?->tipoEnvio?->id,
                'nombre' => $generatedTickets->first()?->tipoEnvio?->nombre,
                'descripcion' => $generatedTickets->first()?->tipoEnvio?->descripcion,
            ],
            'venta_horario' => new VentaHorarioResource($result['venta_horario']),
            'tickets' => TicketResource::collection($generatedTickets),
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

    private function validateVentaHorario(VentaHorario $ventaHorario, Estado $activeStatus): ?JsonResponse
    {
        if ((int) $ventaHorario->estado_id !== (int) $activeStatus->id) {
            return response()->json([
                'message' => 'La venta de horario no esta activa.',
            ], 422);
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
}
