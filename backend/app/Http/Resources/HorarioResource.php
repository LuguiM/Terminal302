<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HorarioResource extends JsonResource
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
            ],
            'operador' => [
                'id' => $this->operador?->id,
                'nombre_comercial' => $this->operador?->nombre_comercial,
            ],
            'bus' => [
                'id' => $this->bus?->id,
                'placa' => $this->bus?->placa,
                'marca' => $this->bus?->marca,
                'nombre_unidad' => $this->bus?->nombre_unidad,
                'capacidad' => $this->bus?->capacidad,
            ],
            'dia' => [
                'id' => $this->dia?->id,
                'nombre' => $this->dia?->nombre,
                'orden' => $this->dia?->orden,
            ],
            'hora_salida' => substr((string) $this->hora_salida, 0, 5),
            'sobreventa_permitida' => (bool) $this->sobreventa_permitida,
            'estado' => [
                'id' => $this->estado?->id,
                'nombre' => $this->estado?->nombre,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
