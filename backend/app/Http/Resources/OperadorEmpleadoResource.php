<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperadorEmpleadoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'estado' => [
                'nombre' => $this->estado?->nombre,
            ],
            'motivo_desactivacion' => $this->motivo_desactivacion,
        ];
    }
}
