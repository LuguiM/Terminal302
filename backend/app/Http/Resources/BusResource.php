<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ruta' => [
                'id' => $this->ruta?->id,
                'ruta' => $this->ruta?->ruta,
                'denominacion' => $this->ruta?->denominacion,
                'tarifa' => $this->ruta?->tarifa,
            ],
            'placa' => $this->placa,
            'marca' => $this->marca,
            'nombre_unidad' => $this->nombre_unidad,
            'capacidad' => $this->capacidad,
            'tipo_bus' => [
                'id' => $this->tipoBus?->id,
                'nombre' => $this->tipoBus?->nombre,
            ],
            'estado' => [
                'id' => $this->estado?->id,
                'nombre' => $this->estado?->nombre,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
