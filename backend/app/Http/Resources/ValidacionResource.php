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
            'ticket' => $this->whenLoaded('ticket', fn (): ?array => $this->ticket ? [
                'codigo_ticket' => $this->ticket->codigo_ticket,
                'estado' => [
                    'nombre' => $this->ticket->estado?->nombre,
                ],
            ] : null),
            'validador' => $this->whenLoaded('validador', fn (): ?array => $this->validador ? [
                'name' => $this->validador->name,
            ] : null),
            'fecha_validacion' => $this->fecha_validacion?->toISOString(),
            'resultado' => $this->resultado,
            'observacion' => $this->observacion,
        ];
    }
}
