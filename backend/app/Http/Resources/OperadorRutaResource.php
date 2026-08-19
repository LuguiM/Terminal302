<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperadorRutaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ruta_id' => $this->ruta?->id,
            'ruta' => $this->ruta?->ruta,
            'denominacion' => $this->ruta?->denominacion,
            'tarifa' => $this->ruta?->tarifa,
            'estado' => [
                'nombre' => $this->estado?->nombre,
            ],
        ];
    }
}
