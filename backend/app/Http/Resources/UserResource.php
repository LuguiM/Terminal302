<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => [
                'id' => $this->role?->id,
                'nombre' => $this->role?->nombre,
            ],
            'estado' => [
                'nombre' => $this->estado?->nombre,
            ],
            'operador' => $this->whenLoaded('operador', fn (): ?array => $this->operador ? [
                'id' => $this->operador->id,
                'nombre_comercial' => $this->operador->nombre_comercial,
                'razon_social' => $this->operador->razon_social,
            ] : null),
            'operador_empleado' => $this->whenLoaded('operadorEmpleado', fn (): ?array => $this->operadorEmpleado ? [
                'operador' => $this->operadorEmpleado->operador ? [
                    'id' => $this->operadorEmpleado->operador->id,
                    'nombre_comercial' => $this->operadorEmpleado->operador->nombre_comercial,
                    'razon_social' => $this->operadorEmpleado->operador->razon_social,
                ] : null,
            ] : null),
            'must_change_password' => $this->must_change_password,
        ];
    }
}
