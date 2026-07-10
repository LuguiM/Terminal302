<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\ValidarTicketRequest;
use App\Http\Resources\TicketResource;
use App\Http\Resources\ValidacionResource;
use App\Models\Estado;
use App\Models\Operador;
use App\Models\User;
use App\Models\Ticket;
use App\Models\Validacion;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ValidadorTicketController extends Controller
{
    private const OPERATION_TIMEZONE = 'America/El_Salvador';

    public function validar(ValidarTicketRequest $request): JsonResponse
    {
        $validador = $request->user();

        if ((int) $validador?->estado_id !== Estado::ACTIVO_ID) {
            return response()->json([
                'message' => 'El usuario no esta activo.',
            ], 403);
        }

        $operador = $this->operadorForValidador($validador);

        if (! $operador) {
            return response()->json([
                'message' => 'El validador autenticado no tiene operador asociado.',
            ], 404);
        }

        $issuedStatus = $this->statusByName('emitido');
        $validatedStatus = $this->statusByName('validado');
        $cancelledStatus = $this->statusByName('cancelado');

        if (! $issuedStatus) {
            return $this->missingStatusResponse('emitido');
        }

        if (! $validatedStatus) {
            return $this->missingStatusResponse('validado');
        }

        if (! $cancelledStatus) {
            return $this->missingStatusResponse('cancelado');
        }

        $result = DB::transaction(function () use ($request, $operador, $validador, $issuedStatus, $validatedStatus, $cancelledStatus): JsonResponse|array {
            $ticket = Ticket::query()
                ->lockForUpdate()
                ->with([
                    'estado',
                    'tipoEnvio',
                    'ticketPlantilla',
                    'ventaHorario.horario.operador',
                    'validacion',
                ])
                ->where('codigo_ticket', $request->validated('codigo_ticket'))
                ->first();

            if (! $ticket) {
                return response()->json([
                    'message' => 'El codigo de ticket solicitado no existe.',
                ], 404);
            }

            $validationResponse = $this->validateTicketForOperator(
                ticket: $ticket,
                operador: $operador,
                issuedStatusId: (int) $issuedStatus->id,
                validatedStatusId: (int) $validatedStatus->id,
                cancelledStatusId: (int) $cancelledStatus->id,
            );

            if ($validationResponse) {
                return $validationResponse;
            }

            $validacion = Validacion::query()->create([
                'ticket_id' => $ticket->id,
                'validador_id' => $validador?->id,
                'fecha_validacion' => CarbonImmutable::now(self::OPERATION_TIMEZONE),
                'resultado' => Validacion::RESULTADO_VALIDO,
                'observacion' => $request->validated('observacion'),
            ]);

            $ticket->forceFill([
                'estado_id' => $validatedStatus->id,
            ])->save();

            return [
                'ticket' => $ticket->fresh(['estado', 'tipoEnvio', 'ticketPlantilla', 'ventaHorario', 'vendedor']),
                'validacion' => $validacion->fresh(['ticket.estado', 'validador']),
            ];
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json([
            'message' => 'Ticket validado correctamente.',
            'ticket' => new TicketResource($result['ticket']),
            'validacion' => new ValidacionResource($result['validacion']),
        ]);
    }

    private function operadorForValidador(?User $validador): ?Operador
    {
        return $validador
            ?->operadorEmpleado()
            ->with('operador')
            ->first()
            ?->operador
            ?? $validador?->operador()->first();
    }

    private function validateTicketForOperator(
        Ticket $ticket,
        Operador $operador,
        int $issuedStatusId,
        int $validatedStatusId,
        int $cancelledStatusId,
    ): ?JsonResponse {
        $ticketOperadorId = $ticket->ventaHorario?->horario?->operador_id;

        if ((int) $ticketOperadorId !== (int) $operador->id) {
            return response()->json([
                'message' => 'El ticket pertenece a otro operador.',
            ], 403);
        }

        if ((int) $ticket->estado_id === $validatedStatusId || $ticket->validacion) {
            return response()->json([
                'message' => 'El ticket ya fue validado.',
            ], 409);
        }

        if ((int) $ticket->estado_id === $cancelledStatusId) {
            return response()->json([
                'message' => 'El ticket esta cancelado.',
            ], 409);
        }

        if ((int) $ticket->estado_id !== $issuedStatusId) {
            return response()->json([
                'message' => 'El ticket no esta en estado emitido.',
            ], 422);
        }

        return null;
    }

    private function statusByName(string $name): ?Estado
    {
        return Estado::query()
            ->whereRaw('LOWER(nombre) = ?', [$name])
            ->first();
    }

    private function missingStatusResponse(string $statusName): JsonResponse
    {
        return response()->json([
            'message' => "No se encontro el estado requerido: {$statusName}.",
        ], 500);
    }
}
