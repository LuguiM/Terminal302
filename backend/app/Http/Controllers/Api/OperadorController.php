<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operador\StoreOperadorRequest;
use App\Http\Requests\Operador\UpdateOperadorRequest;
use App\Http\Resources\OperadorResource;
use App\Models\Estado;
use App\Models\Operador;
use App\Models\TipoOperador;
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

        $operatorData = $this->operatorDataForType($request->validated());

        $operador = Operador::query()->create([
            ...$operatorData,
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

        $operador->update($this->operatorDataForType($request->validated()));

        return response()->json([
            'message' => 'Operador actualizado correctamente.',
            'operador' => new OperadorResource($operador->fresh(['tipoOperador', 'estado'])),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function operatorDataForType(array $data): array
    {
        $baseData = [
            'tipo_operador_id' => $data['tipo_operador_id'],
            'nombre_comercial' => $this->nullableString($data, 'nombre_comercial'),
        ];

        $tipoOperador = mb_strtolower((string) TipoOperador::query()->find($data['tipo_operador_id'])?->nombre);

        if ($tipoOperador === 'empresa') {
            return [
                ...$baseData,
                'razon_social' => $this->nullableString($data, 'razon_social'),
                'representante_legal' => $this->nullableString($data, 'representante_legal'),
                'direccion' => $this->nullableString($data, 'direccion'),
                'telefono' => $this->nullableString($data, 'telefono'),
                'telefono_opcional' => null,
                'correo_administrativo' => $this->nullableString($data, 'correo_administrativo'),
                'nit' => $this->nullableString($data, 'nit'),
                'dui' => null,
            ];
        }

        return [
            ...$baseData,
            'razon_social' => null,
            'representante_legal' => null,
            'direccion' => null,
            'telefono' => $this->nullableString($data, 'telefono'),
            'telefono_opcional' => $this->nullableString($data, 'telefono_opcional'),
            'correo_administrativo' => null,
            'nit' => null,
            'dui' => $this->nullableString($data, 'dui'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function nullableString(array $data, string $key): ?string
    {
        $value = trim((string) ($data[$key] ?? ''));

        return $value === '' ? null : $value;
    }

    private function missingStatusResponse(string $statusName): JsonResponse
    {
        return response()->json([
            'message' => "No se encontro el estado requerido: {$statusName}.",
        ], 500);
    }
}
