<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperadorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();

        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn (): array => [
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'tipo_operador' => [
                'id' => $this->tipoOperador?->id,
                'nombre' => $this->tipoOperador?->nombre,
            ],
            'nombre_comercial' => $this->nombre_comercial,
            'razon_social' => $this->razon_social,
            'representante_legal' => $this->representante_legal,
            'telefono' => $this->telefono,
            'telefono_opcional' => $this->telefono_opcional,
            'correo_administrativo' => $this->correo_administrativo,
            'nit' => $this->nit,
            'dui' => $this->dui,
            'direccion' => $this->direccion,
            'estado' => [
                'nombre' => $this->estado?->nombre,
            ],
            'motivo_desactivacion' => $this->motivo_desactivacion,
            'rutas_count' => $this->when(
                array_key_exists('operador_rutas_count', $attributes),
                (int) ($this->operador_rutas_count ?? 0),
            ),
            'buses_count' => $this->when(
                array_key_exists('buses_count', $attributes),
                (int) ($this->buses_count ?? 0),
            ),
        ];
    }
}
