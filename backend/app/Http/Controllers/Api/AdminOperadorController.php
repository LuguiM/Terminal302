<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operador\ToggleOperadorStatusRequest;
use App\Http\Resources\OperadorResource;
use App\Models\Estado;
use App\Models\Operador;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminOperadorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 50);

        $operadores = Operador::query()
            ->with(['user', 'tipoOperador', 'estado'])
            ->orderBy('id')
            ->paginate($perPage);

        return ApiResponse::paginated($operadores, 'operadores', OperadorResource::class);
    }

    public function show(Operador $operador): OperadorResource
    {
        return new OperadorResource($operador->load(['user', 'tipoOperador', 'estado']));
    }

    public function toggleStatus(ToggleOperadorStatusRequest $request, Operador $operador): JsonResponse
    {
        $activeStatus = Estado::activo();
        $inactiveStatus = Estado::inactivo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        if (! $inactiveStatus) {
            return $this->missingStatusResponse('inactivo/desactivado');
        }

        if ((int) $operador->estado_id === (int) $activeStatus->id) {
            $motivo = trim((string) $request->input('motivo_desactivacion'));

            if ($motivo === '') {
                throw ValidationException::withMessages([
                    'motivo_desactivacion' => ['El motivo de desactivacion es obligatorio.'],
                ]);
            }

            $operador->forceFill([
                'estado_id' => $inactiveStatus->id,
                'motivo_desactivacion' => $motivo,
            ])->save();
        } else {
            $operador->forceFill([
                'estado_id' => $activeStatus->id,
                'motivo_desactivacion' => null,
            ])->save();
        }

        return response()->json([
            'message' => 'Estado del operador actualizado correctamente.',
            'operador' => new OperadorResource($operador->fresh(['user', 'tipoOperador', 'estado'])),
        ]);
    }

    private function missingStatusResponse(string $statusName): JsonResponse
    {
        return response()->json([
            'message' => "No se encontro el estado requerido: {$statusName}.",
        ], 500);
    }
}
