<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperadorListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre_comercial' => $this->nombre_comercial,
            'razon_social' => $this->razon_social,
            'estado' => [
                'nombre' => $this->estado?->nombre,
            ],
            'rutas_count' => (int) ($this->operador_rutas_count ?? 0),
            'buses_count' => (int) ($this->buses_count ?? 0),
        ];
    }
}
