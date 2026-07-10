<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ValidacionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket' => $this->whenLoaded('ticket', fn (): ?array => $this->ticket ? [
                'id' => $this->ticket->id,
                'codigo_ticket' => $this->ticket->codigo_ticket,
                'estado' => [
                    'id' => $this->ticket->estado?->id,
                    'nombre' => $this->ticket->estado?->nombre,
                ],
            ] : null),
            'validador' => $this->whenLoaded('validador', fn (): ?array => $this->validador ? [
                'id' => $this->validador->id,
                'name' => $this->validador->name,
                'email' => $this->validador->email,
            ] : null),
            'fecha_validacion' => $this->fecha_validacion?->toISOString(),
            'resultado' => $this->resultado,
            'observacion' => $this->observacion,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
