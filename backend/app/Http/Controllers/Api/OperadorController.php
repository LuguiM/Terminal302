<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operador\StoreOperadorRequest;
use App\Http\Requests\Operador\UpdateOperadorRequest;
use App\Http\Resources\OperadorResource;
use App\Models\Estado;
use App\Models\Operador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperadorController extends Controller
{
    public function me(Request $request): JsonResponse|OperadorResource
    {
        $operador = $request->user()
            ->operador()
            ->with(['tipoOperador', 'estado'])
            ->first();

        if (! $operador) {
            return response()->json([
                'message' => 'El empresario autenticado no tiene operador registrado.',
            ], 404);
        }

        return new OperadorResource($operador);
    }

    public function store(StoreOperadorRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->operador()->exists()) {
            return response()->json([
                'message' => 'El empresario ya tiene un operador registrado.',
            ], 409);
        }

        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $operador = Operador::query()->create([
            ...$request->validated(),
            'user_id' => $user->id,
            'estado_id' => $activeStatus->id,
        ]);

        return response()->json([
            'message' => 'Operador registrado correctamente.',
            'operador' => new OperadorResource($operador->load(['tipoOperador', 'estado'])),
        ], 201);
    }

    public function update(UpdateOperadorRequest $request, Operador $operador): JsonResponse
    {
        if ((int) $operador->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'El operador no pertenece al empresario autenticado.',
            ], 403);
        }

        $operador->update($request->validated());

        return response()->json([
            'message' => 'Operador actualizado correctamente.',
            'operador' => new OperadorResource($operador->fresh(['tipoOperador', 'estado'])),
        ]);
    }

    private function missingStatusResponse(string $statusName): JsonResponse
    {
        return response()->json([
            'message' => "No se encontro el estado requerido: {$statusName}.",
        ], 500);
    }
}
