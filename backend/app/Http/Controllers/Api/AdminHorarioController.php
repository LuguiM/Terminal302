<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horario\BusesPorRutaOperadorRequest;
use App\Http\Requests\Horario\HorariosPorRutaDiaRequest;
use App\Http\Requests\Horario\StoreHorarioRequest;
use App\Http\Requests\Horario\UpdateHorarioRequest;
use App\Http\Resources\BusResource;
use App\Http\Resources\HorarioResource;
use App\Http\Resources\OperadorResource;
use App\Http\Resources\RutaResource;
use App\Models\Bus;
use App\Models\Dia;
use App\Models\Estado;
use App\Models\Horario;
use App\Models\Operador;
use App\Models\OperadorRuta;
use App\Models\Ruta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminHorarioController extends Controller
{
    public function rutas(Request $request): JsonResponse
    {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $rutas = Ruta::query()
            ->with('estado')
            ->where('estado_id', $activeStatus->id)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('ruta', 'like', $search)
                        ->orWhere('denominacion', 'like', $search);
                });
            })
            ->orderBy('ruta')
            ->get();

        return response()->json([
            'rutas' => RutaResource::collection($rutas),
        ]);
    }

    public function diasPorRuta(int|string $ruta): JsonResponse
    {
        $ruta = $this->findRuta($ruta);

        if (! $ruta) {
            return $this->missingRutaResponse();
        }

        $dias = Dia::query()
            ->whereHas('horarios', fn ($query) => $query->where('ruta_id', $ruta->id))
            ->orderBy('orden')
            ->get();

        return response()->json([
            'ruta' => new RutaResource($ruta->load('estado')),
            'dias' => $this->formatDias($dias),
        ]);
    }

    public function horariosPorRutaYDia(HorariosPorRutaDiaRequest $request): JsonResponse
    {
        $horarios = Horario::query()
            ->with(['ruta', 'operador', 'bus', 'dia', 'estado'])
            ->where('ruta_id', $request->integer('ruta_id'))
            ->where('dia_id', $request->integer('dia_id'))
            ->orderBy('hora_salida')
            ->get();

        return response()->json([
            'horarios' => HorarioResource::collection($horarios),
        ]);
    }

    public function operadoresPorRuta(int|string $ruta): JsonResponse
    {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $ruta = $this->findRuta($ruta);

        if (! $ruta) {
            return $this->missingRutaResponse();
        }

        if ((int) $ruta->estado_id !== (int) $activeStatus->id) {
            return $this->inactiveRutaResponse();
        }

        $operadores = Operador::query()
            ->with(['user', 'tipoOperador', 'estado'])
            ->where('estado_id', $activeStatus->id)
            ->whereHas('operadorRutas', function ($query) use ($ruta, $activeStatus): void {
                $query->where('ruta_id', $ruta->id)
                    ->where('estado_id', $activeStatus->id);
            })
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'operadores' => OperadorResource::collection($operadores),
        ]);
    }

    public function busesPorRutaYOperador(BusesPorRutaOperadorRequest $request): JsonResponse
    {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $validationResponse = $this->validateRutaOperador(
            rutaId: $request->integer('ruta_id'),
            operadorId: $request->integer('operador_id'),
            activeStatus: $activeStatus,
        );

        if ($validationResponse) {
            return $validationResponse;
        }

        $buses = Bus::query()
            ->with(['ruta', 'tipoBus', 'estado'])
            ->where('ruta_id', $request->integer('ruta_id'))
            ->where('operador_id', $request->integer('operador_id'))
            ->where('estado_id', $activeStatus->id)
            ->orderBy('placa')
            ->get();

        return response()->json([
            'buses' => BusResource::collection($buses),
        ]);
    }

    public function store(StoreHorarioRequest $request): JsonResponse
    {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $validationResponse = $this->validateHorarioData($request->validated(), $activeStatus);

        if ($validationResponse) {
            return $validationResponse;
        }

        $horario = Horario::query()->create([
            ...$request->validated(),
            'estado_id' => $activeStatus->id,
        ]);

        return response()->json([
            'message' => 'Horario creado correctamente.',
        ], 201);
    }

    public function update(UpdateHorarioRequest $request, int|string $horario): JsonResponse
    {
        $horario = $this->findHorario($horario);

        if (! $horario) {
            return $this->missingHorarioResponse();
        }

        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $validationResponse = $this->validateHorarioData($request->validated(), $activeStatus, $horario->id);

        if ($validationResponse) {
            return $validationResponse;
        }

        $horario->update($request->validated());

        return response()->json([
            'message' => 'Horario actualizado correctamente.',
        ]);
    }

    public function toggleStatus(int|string $horario): JsonResponse
    {
        $horario = $this->findHorario($horario);

        if (! $horario) {
            return $this->missingHorarioResponse();
        }

        $activeStatus = Estado::activo();
        $inactiveStatus = Estado::inactivo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        if (! $inactiveStatus) {
            return $this->missingStatusResponse('inactivo/desactivado');
        }

        $horario->forceFill([
            'estado_id' => (int) $horario->estado_id === (int) $activeStatus->id
                ? $inactiveStatus->id
                : $activeStatus->id,
        ])->save();

        return response()->json([
            'message' => 'Estado del horario actualizado correctamente.',
        ]);
    }

    public function destroy(int|string $horario): JsonResponse
    {
        $horario = $this->findHorario($horario);

        if (! $horario) {
            return $this->missingHorarioResponse();
        }

        $horario->delete();

        return response()->json([
            'message' => 'Horario eliminado correctamente.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateHorarioData(array $data, Estado $activeStatus, ?int $ignoreHorarioId = null): ?JsonResponse
    {
        $validationResponse = $this->validateRutaOperador(
            rutaId: (int) $data['ruta_id'],
            operadorId: (int) $data['operador_id'],
            activeStatus: $activeStatus,
        );

        if ($validationResponse) {
            return $validationResponse;
        }

        $bus = Bus::query()->find((int) $data['bus_id']);

        if (! $bus) {
            return $this->missingBusResponse();
        }

        if ((int) $bus->estado_id !== (int) $activeStatus->id) {
            return response()->json([
                'message' => 'El bus seleccionado no esta activo.',
            ], 422);
        }

        if ((int) $bus->operador_id !== (int) $data['operador_id']) {
            return response()->json([
                'message' => 'El bus no pertenece al operador seleccionado.',
            ], 422);
        }

        if ((int) $bus->ruta_id !== (int) $data['ruta_id']) {
            return response()->json([
                'message' => 'El bus no pertenece a la ruta seleccionada.',
            ], 422);
        }

        if (! Dia::query()->whereKey((int) $data['dia_id'])->exists()) {
            return response()->json([
                'message' => 'El dia seleccionado no existe.',
            ], 404);
        }

        $duplicateExists = Horario::query()
            ->where('ruta_id', (int) $data['ruta_id'])
            ->where('operador_id', (int) $data['operador_id'])
            ->where('bus_id', (int) $data['bus_id'])
            ->where('dia_id', (int) $data['dia_id'])
            ->where('hora_salida', (string) $data['hora_salida'])
            ->when($ignoreHorarioId, fn ($query) => $query->whereKeyNot($ignoreHorarioId))
            ->exists();

        if ($duplicateExists) {
            return response()->json([
                'message' => 'El horario ya existe con la misma ruta, operador, bus, dia y hora de salida.',
            ], 409);
        }

        return null;
    }

    private function validateRutaOperador(int $rutaId, int $operadorId, Estado $activeStatus): ?JsonResponse
    {
        $ruta = $this->findRuta($rutaId);

        if (! $ruta) {
            return $this->missingRutaResponse();
        }

        if ((int) $ruta->estado_id !== (int) $activeStatus->id) {
            return $this->inactiveRutaResponse();
        }

        $operador = Operador::query()->find($operadorId);

        if (! $operador) {
            return $this->missingOperadorResponse();
        }

        if ((int) $operador->estado_id !== (int) $activeStatus->id) {
            return response()->json([
                'message' => 'El operador seleccionado no esta activo.',
            ], 422);
        }

        $hasActiveRoute = OperadorRuta::query()
            ->where('ruta_id', $ruta->id)
            ->where('operador_id', $operador->id)
            ->where('estado_id', $activeStatus->id)
            ->exists();

        if (! $hasActiveRoute) {
            return response()->json([
                'message' => 'El operador no tiene asignada la ruta seleccionada.',
            ], 403);
        }

        return null;
    }

    private function findRuta(int|string $ruta): ?Ruta
    {
        return Ruta::query()->find($ruta);
    }

    private function findHorario(int|string $horario): ?Horario
    {
        return Horario::query()->find($horario);
    }

    private function missingRutaResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'La ruta seleccionada no existe.',
        ], 404);
    }

    private function inactiveRutaResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'La ruta seleccionada no esta activa.',
        ], 422);
    }

    private function missingOperadorResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'El operador seleccionado no existe.',
        ], 404);
    }

    private function missingBusResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'El bus seleccionado no existe.',
        ], 404);
    }

    private function missingHorarioResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'El horario solicitado no existe.',
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
