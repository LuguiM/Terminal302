<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OperadorRuta\StoreOperadorRutaRequest;
use App\Http\Resources\OperadorRutaResource;
use App\Http\Resources\RutaResource;
use App\Models\Estado;
use App\Models\Operador;
use App\Models\OperadorRuta;
use App\Models\Ruta;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperadorRutaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $operador = $this->authenticatedOperador($request);

        if (! $operador) {
            return $this->missingOperadorResponse();
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 50);

        $operadorRutas = OperadorRuta::query()
            ->with(['ruta.estado', 'estado'])
            ->where('operador_id', $operador->id)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = mb_strtolower((string) $request->input('search'));

                $query->whereHas('ruta', function ($rutaQuery) use ($search): void {
                    $rutaQuery
                        ->whereRaw('LOWER(ruta) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(denominacion) LIKE ?', ["%{$search}%"]);
                });
            })
            ->orderBy('id')
            ->paginate($perPage);

        return ApiResponse::paginated($operadorRutas, 'operador_rutas', OperadorRutaResource::class);
    }

    public function rutasDisponibles(Request $request): JsonResponse
    {
        $operador = $this->authenticatedOperador($request);

        if (! $operador) {
            return $this->missingOperadorResponse();
        }

        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $rutas = Ruta::query()
            ->with('estado')
            ->where('estado_id', $activeStatus->id)
            ->whereDoesntHave('operadorRutas', function ($query) use ($operador): void {
                $query->where('operador_id', $operador->id);
            })
            ->orderBy('ruta')
            ->get();

        return response()->json([
            'rutas' => RutaResource::collection($rutas),
        ]);
    }

    public function store(StoreOperadorRutaRequest $request): JsonResponse
    {
        $operador = $this->authenticatedOperador($request);

        if (! $operador) {
            return $this->missingOperadorResponse();
        }

        $ruta = Ruta::query()
            ->with('estado')
            ->find($request->integer('ruta_id'));

        if (! $ruta) {
            return response()->json([
                'message' => 'La ruta seleccionada no existe.',
            ], 404);
        }

        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        if ((int) $ruta->estado_id !== (int) $activeStatus->id) {
            return response()->json([
                'message' => 'La ruta seleccionada no esta activa.',
            ], 422);
        }

        $exists = OperadorRuta::query()
            ->where('operador_id', $operador->id)
            ->where('ruta_id', $ruta->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'La ruta ya esta asignada al operador.',
            ], 409);
        }

        $operadorRuta = OperadorRuta::query()->create([
            'operador_id' => $operador->id,
            'ruta_id' => $ruta->id,
            'estado_id' => $activeStatus->id,
        ]);

        return response()->json([
            'message' => 'Ruta asignada al operador correctamente.',
            'operador_ruta' => new OperadorRutaResource($operadorRuta->load(['ruta.estado', 'estado'])),
        ], 201);
    }

    public function toggleStatus(Request $request, int|string $operadorRuta): JsonResponse
    {
        $operador = $this->authenticatedOperador($request);

        if (! $operador) {
            return $this->missingOperadorResponse();
        }

        $operadorRuta = $this->findOwnedOperadorRuta($operador, $operadorRuta);

        if (! $operadorRuta) {
            return $this->missingOwnedOperadorRutaResponse();
        }

        $activeStatus = Estado::activo();
        $inactiveStatus = Estado::inactivo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        if (! $inactiveStatus) {
            return $this->missingStatusResponse('inactivo/desactivado');
        }

        $operadorRuta->forceFill([
            'estado_id' => (int) $operadorRuta->estado_id === (int) $activeStatus->id
                ? $inactiveStatus->id
                : $activeStatus->id,
        ])->save();

        return response()->json([
            'message' => 'Estado de la ruta asignada actualizado correctamente.',
            'operador_ruta' => new OperadorRutaResource($operadorRuta->fresh(['ruta.estado', 'estado'])),
        ]);
    }

    public function destroy(Request $request, int|string $operadorRuta): JsonResponse
    {
        $operador = $this->authenticatedOperador($request);

        if (! $operador) {
            return $this->missingOperadorResponse();
        }

        $operadorRuta = $this->findOwnedOperadorRuta($operador, $operadorRuta);

        if (! $operadorRuta) {
            return $this->missingOwnedOperadorRutaResponse();
        }

        $operadorRuta->delete();

        return response()->json([
            'message' => 'Ruta asignada eliminada correctamente.',
        ]);
    }

    private function authenticatedOperador(Request $request): ?Operador
    {
        return $request->user()
            ?->operador()
            ->first();
    }

    private function findOwnedOperadorRuta(Operador $operador, int|string $operadorRuta): ?OperadorRuta
    {
        return OperadorRuta::query()
            ->where('operador_id', $operador->id)
            ->find($operadorRuta);
    }

    private function missingOperadorResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'El empresario autenticado no tiene operador registrado.',
        ], 404);
    }

    private function missingOwnedOperadorRutaResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'La asignacion de ruta no pertenece al operador autenticado.',
        ], 404);
    }

    private function missingStatusResponse(string $statusName): JsonResponse
    {
        return response()->json([
            'message' => "No se encontro el estado requerido: {$statusName}.",
        ], 500);
    }
}
