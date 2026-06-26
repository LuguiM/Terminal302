<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ruta\StoreRutaRequest;
use App\Http\Requests\Ruta\UpdateRutaRequest;
use App\Http\Resources\RutaResource;
use App\Models\Estado;
use App\Models\Ruta;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRutaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 50);

        $rutas = Ruta::query()
            ->with('estado')
            ->orderBy('id')
            ->paginate($perPage);

        return ApiResponse::paginated($rutas, 'rutas', RutaResource::class);
    }

    public function store(StoreRutaRequest $request): JsonResponse
    {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $ruta = Ruta::query()->create([
            ...$request->validated(),
            'estado_id' => $activeStatus->id,
        ]);

        return response()->json([
            'message' => 'Ruta creada correctamente.',
            'ruta' => new RutaResource($ruta->load('estado')),
        ], 201);
    }

    public function update(UpdateRutaRequest $request, int|string $ruta): JsonResponse
    {
        $ruta = $this->findRuta($ruta);

        if (! $ruta) {
            return $this->missingRutaResponse();
        }

        $ruta->update($request->validated());

        return response()->json([
            'message' => 'Ruta actualizada correctamente.',
            'ruta' => new RutaResource($ruta->fresh('estado')),
        ]);
    }

    public function toggleStatus(int|string $ruta): JsonResponse
    {
        $ruta = $this->findRuta($ruta);

        if (! $ruta) {
            return $this->missingRutaResponse();
        }

        $activeStatus = Estado::activo();
        $inactiveStatus = Estado::inactivo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        if (! $inactiveStatus) {
            return $this->missingStatusResponse('inactivo/desactivado');
        }

        $ruta->forceFill([
            'estado_id' => (int) $ruta->estado_id === (int) $activeStatus->id
                ? $inactiveStatus->id
                : $activeStatus->id,
        ])->save();

        return response()->json([
            'message' => 'Estado de la ruta actualizado correctamente.',
            'ruta' => new RutaResource($ruta->fresh('estado')),
        ]);
    }

    public function destroy(int|string $ruta): JsonResponse
    {
        $ruta = $this->findRuta($ruta);

        if (! $ruta) {
            return $this->missingRutaResponse();
        }

        $ruta->delete();

        return response()->json([
            'message' => 'Ruta eliminada correctamente.',
        ]);
    }

    private function findRuta(int|string $id): ?Ruta
    {
        return Ruta::query()->find($id);
    }

    private function missingRutaResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'La ruta solicitada no existe.',
        ], 404);
    }

    private function missingStatusResponse(string $statusName): JsonResponse
    {
        return response()->json([
            'message' => "No se encontro el estado requerido: {$statusName}.",
        ], 500);
    }
}
