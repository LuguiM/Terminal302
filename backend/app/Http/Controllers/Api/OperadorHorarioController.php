<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horario\HorariosPorRutaDiaRequest;
use App\Http\Resources\HorarioResource;
use App\Http\Resources\RutaResource;
use App\Models\Dia;
use App\Models\Estado;
use App\Models\Horario;
use App\Models\Operador;
use App\Models\OperadorRuta;
use App\Models\Ruta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperadorHorarioController extends Controller
{
    public function rutas(Request $request): JsonResponse
    {
        $operador = $this->authenticatedOperador($request);

        if (! $operador) {
            return $this->missingOperadorAutenticadoResponse();
        }

        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $rutas = Ruta::query()
            ->with('estado')
            ->where('estado_id', $activeStatus->id)
            ->whereHas('operadorRutas', function ($query) use ($operador, $activeStatus): void {
                $query->where('operador_id', $operador->id)
                    ->where('estado_id', $activeStatus->id);
            })
            ->orderBy('ruta')
            ->get();

        return response()->json([
            'rutas' => RutaResource::collection($rutas),
        ]);
    }

    public function diasPorRuta(Request $request, int|string $ruta): JsonResponse
    {
        $operador = $this->authenticatedOperador($request);

        if (! $operador) {
            return $this->missingOperadorAutenticadoResponse();
        }

        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $validationResponse = $this->validateOwnedActiveRoute((int) $ruta, $operador, $activeStatus);

        if ($validationResponse) {
            return $validationResponse;
        }

        $rutaModel = Ruta::query()->with('estado')->find((int) $ruta);

        $dias = Dia::query()
            ->whereHas('horarios', function ($query) use ($ruta, $operador, $activeStatus): void {
                $query->where('ruta_id', (int) $ruta)
                    ->where('operador_id', $operador->id)
                    ->where('estado_id', $activeStatus->id);
            })
            ->orderBy('orden')
            ->get();

        return response()->json([
            'ruta' => new RutaResource($rutaModel),
            'dias' => $this->formatDias($dias),
        ]);
    }

    public function horariosPorRutaYDia(HorariosPorRutaDiaRequest $request): JsonResponse
    {
        $operador = $this->authenticatedOperador($request);

        if (! $operador) {
            return $this->missingOperadorAutenticadoResponse();
        }

        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $validationResponse = $this->validateOwnedActiveRoute(
            rutaId: $request->integer('ruta_id'),
            operador: $operador,
            activeStatus: $activeStatus,
        );

        if ($validationResponse) {
            return $validationResponse;
        }

        $horarios = Horario::query()
            ->with(['ruta', 'operador', 'bus', 'dia', 'estado'])
            ->where('ruta_id', $request->integer('ruta_id'))
            ->where('dia_id', $request->integer('dia_id'))
            ->where('operador_id', $operador->id)
            ->where('estado_id', $activeStatus->id)
            ->orderBy('hora_salida')
            ->get();

        return response()->json([
            'horarios' => HorarioResource::collection($horarios),
        ]);
    }

    private function authenticatedOperador(Request $request): ?Operador
    {
        return $request->user()
            ?->operador()
            ->first();
    }

    private function validateOwnedActiveRoute(int $rutaId, Operador $operador, Estado $activeStatus): ?JsonResponse
    {
        $ruta = Ruta::query()->find($rutaId);

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

        $hasActiveRoute = OperadorRuta::query()
            ->where('ruta_id', $ruta->id)
            ->where('operador_id', $operador->id)
            ->where('estado_id', $activeStatus->id)
            ->exists();

        if (! $hasActiveRoute) {
            return response()->json([
                'message' => 'La ruta no pertenece al operador autenticado.',
            ], 403);
        }

        return null;
    }

    private function missingOperadorAutenticadoResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'El empresario autenticado no tiene operador registrado.',
        ], 404);
    }

    private function missingStatusResponse(string $statusName): JsonResponse
    {
        return response()->json([
            'message' => "No se encontro el estado requerido: {$statusName}.",
        ], 500);
    }

    /**
     * @param  iterable<Dia>  $dias
     * @return array<int, array<string, mixed>>
     */
    private function formatDias(iterable $dias): array
    {
        return collect($dias)
            ->map(fn (Dia $dia): array => [
                'id' => $dia->id,
                'nombre' => $dia->nombre,
                'orden' => $dia->orden,
            ])
            ->values()
            ->all();
    }
}
