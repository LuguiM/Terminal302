<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuRutaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'ruta' => $this->ruta,
            'orden' => $this->orden,
            'icono' => $this->icono,
            'visible' => (bool) $this->visible,
            'requiere_autenticacion' => (bool) $this->requiere_autenticacion,
            'dependencia' => $this->dependencia,
            'role_id' => $this->role_id,
            'base_url' => $this->base_url,
            'estado' => $this->whenLoaded('estado', fn (): ?array => $this->estado ? [
                'id' => $this->estado->id,
                'nombre' => $this->estado->nombre,
            ] : null),
            'role' => $this->whenLoaded('role', fn (): ?array => $this->role ? [
                'id' => $this->role->id,
                'nombre' => $this->role->nombre,
            ] : null),
            'dependencias' => $this->whenLoaded(
                'dependencias',
                fn () => self::collection($this->dependencias),
                [],
            ),
        ];
    }
}
