<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicHorarioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'horario_id' => $this->id,
            'dia' => [
                'id' => $this->dia?->id,
                'nombre' => $this->dia?->nombre,
                'orden' => $this->dia?->orden,
            ],
            'hora_salida' => substr((string) $this->hora_salida, 0, 5),
            'operador' => $this->operador?->nombre,
            'tarifa' => $this->ruta?->tarifa,
        ];
    }
}
