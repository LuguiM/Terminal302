<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketPlantillaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'image_path' => $this->image_path,
            'qr_location' => $this->qr_location,
            'precio_location' => $this->precio_location,
            'fecha_hora_location' => $this->fecha_hora_location,
            'asiento_location' => $this->asiento_location,
            'codigo_ticket_location' => $this->codigo_ticket_location,
            'ruta_location' => $this->ruta_location,
            'operador_location' => $this->operador_location,
            'estado' => [
                'id' => $this->estado?->id,
                'nombre' => $this->estado?->nombre,
            ],
            'es_predeterminada' => $this->es_predeterminada,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
