<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VentaHorario\CerrarVentaHorarioRequest;
use App\Http\Resources\RutaResource;
use App\Http\Resources\VentaHorarioResource;
use App\Models\Estado;
use App\Models\Horario;
use App\Models\Ruta;
use App\Models\VentaHorario;
use App\Services\VentaHorarioLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class VendedorVentaHorarioController extends Controller
{
    private const OPERATION_TIMEZONE = 'America/El_Salvador';

    public function rutasDisponibles(VentaHorarioLifecycleService $ventaHorarioLifecycleService): JsonResponse
    {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $now = CarbonImmutable::now(self::OPERATION_TIMEZONE);
        $ventaHorarioLifecycleService->closeExpiredForToday($activeStatus, $now);

        $rutas = Ruta::query()
            ->with('estado')
            ->where('estado_id', $activeStatus->id)
            ->whereHas('horarios', fn ($query) => $query
                ->where('estado_id', $activeStatus->id)
                ->where('hora_salida', '>=', $now->format('H:i'))
                ->whereHas('dia', fn ($dayQuery) => $dayQuery->where('orden', $now->dayOfWeekIso)))
            ->orderBy('ruta')
            ->get();

        return response()->json([
            'rutas' => RutaResource::collection($rutas),
        ]);
    }

    public function horariosDisponiblesPorRuta(
        int|string $ruta,
        VentaHorarioLifecycleService $ventaHorarioLifecycleService,
    ): JsonResponse {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $now = CarbonImmutable::now(self::OPERATION_TIMEZONE);
        $ventaHorarioLifecycleService->closeExpiredForToday($activeStatus, $now);

        $ruta = Ruta::query()->with('estado')->find($ruta);

        if (! $ruta) {
            return response()->json([
                'message' => 'La ruta seleccionada no existe.',
            ], 404);
        }

        if ((int) $ruta->estado_id !== (int) $activeStatus->id) {
            return response()->json([
                'message' => 'La ruta seleccionada no esta activa.',
            ], 422);
        }

        $fechaOperacion = $now->toDateString();

        $horarios = Horario::query()
            ->with(['ruta', 'operador', 'bus', 'estado'])
            ->where('ruta_id', $ruta->id)
            ->where('estado_id', $activeStatus->id)
            ->where('hora_salida', '>=', $now->format('H:i'))
            ->whereHas('dia', fn ($query) => $query->where('orden', $now->dayOfWeekIso))
            ->orderBy('hora_salida')
            ->get();

        if ($horarios->isEmpty()) {
            return response()->json([
                'message' => 'No existen horarios activos vigentes para esta ruta.',
            ], 404);
        }

        $horarioEnMeta = $horarios->first();

        if (! $horarioEnMeta) {
            return response()->json([
                'message' => 'No se pudo determinar horario en meta o proximo horario.',
            ], 422);
        }

        $metaMinute = $this->minutesFromTime($horarioEnMeta->hora_salida);
        $proximoHorario = $horarios
            ->filter(fn (Horario $horario): bool => $horario->id !== $horarioEnMeta->id)
            ->filter(fn (Horario $horario): bool => $this->minutesFromTime($horario->hora_salida) > $metaMinute)
            ->sortBy(fn (Horario $horario): int => $this->minutesFromTime($horario->hora_salida))
            ->first();

        return response()->json([
            'fecha_operacion' => $fechaOperacion,
            'ruta' => new RutaResource($ruta),
            'en_meta' => $this->formatHorarioDisponible($horarioEnMeta, $fechaOperacion, $activeStatus),
            'proximo_a_salir' => $proximoHorario
                ? $this->formatHorarioDisponible($proximoHorario, $fechaOperacion, $activeStatus)
                : null,
        ]);
    }

    public function cerrar(CerrarVentaHorarioRequest $request, int|string $ventaHorario): JsonResponse
    {
        $ventaHorario = VentaHorario::query()
            ->with(['horario.ruta', 'horario.operador', 'horario.bus', 'estado', 'cerradaPor'])
            ->find($ventaHorario);

        if (! $ventaHorario) {
            return response()->json([
                'message' => 'La venta de horario solicitada no existe.',
            ], 404);
        }

        if ($ventaHorario->venta_cerrada) {
            return response()->json([
                'message' => 'La venta de horario ya esta cerrada.',
            ], 409);
        }

        $ventaHorario->forceFill([
            'venta_cerrada' => true,
            'cerrada_por' => $request->user()?->id,
            'fecha_cierre' => CarbonImmutable::now(self::OPERATION_TIMEZONE),
            'motivo_cierre' => $request->validated('motivo_cierre'),
        ])->save();

        return response()->json([
            'message' => 'Venta de horario cerrada correctamente.',
            'venta_horario' => new VentaHorarioResource(
                $ventaHorario->fresh(['horario.ruta', 'horario.operador', 'horario.bus', 'estado', 'cerradaPor'])
            ),
        ]);
    }

    private function formatHorarioDisponible(Horario $horario, string $fechaOperacion, Estado $activeStatus): array
    {
        $ventaHorario = VentaHorario::query()->firstOrCreate(
            [
                'horario_id' => $horario->id,
                'fecha_operacion' => $fechaOperacion,
            ],
            [
                'venta_cerrada' => false,
                'total_tickets_vendidos' => 0,
                'total_tickets_sobreventa' => 0,
                'estado_id' => $activeStatus->id,
            ],
        );

        $ventaHorario->loadMissing('estado');

        return [
            'horario_id' => $horario->id,
            'venta_horario_id' => $ventaHorario->id,
            'hora_salida' => substr((string) $horario->hora_salida, 0, 5),
            'ruta' => [
                'id' => $horario->ruta?->id,
                'ruta' => $horario->ruta?->ruta,
                'denominacion' => $horario->ruta?->denominacion,
                'tarifa' => $horario->ruta?->tarifa,
            ],
            'operador' => [
                'id' => $horario->operador?->id,
                'nombre_comercial' => $horario->operador?->nombre_comercial,
            ],
            'bus' => [
                'id' => $horario->bus?->id,
                'placa' => $horario->bus?->placa,
                'marca' => $horario->bus?->marca,
                'nombre_unidad' => $horario->bus?->nombre_unidad,
            ],
            'capacidad' => $horario->bus?->capacidad,
            'total_tickets_vendidos' => $ventaHorario->total_tickets_vendidos,
            'total_tickets_sobreventa' => $ventaHorario->total_tickets_sobreventa,
            'sobreventa_permitida' => (bool) $horario->sobreventa_permitida,
            'venta_cerrada' => (bool) $ventaHorario->venta_cerrada,
            'puede_vender' => ! $ventaHorario->venta_cerrada
                && (int) $ventaHorario->estado_id === (int) $activeStatus->id,
        ];
    }

    private function minutesFromTime(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) $parts[0] * 60) + (int) $parts[1];
    }

    private function missingStatusResponse(string $statusName): JsonResponse
    {
        return response()->json([
            'message' => "No se encontro el estado requerido: {$statusName}.",
        ], 500);
    }
}
