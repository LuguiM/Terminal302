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
            'user_id' => $this->user?->id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'estado' => [
                'id' => $this->estado?->id,
                'nombre' => $this->estado?->nombre,
            ],
            'motivo_desactivacion' => $this->motivo_desactivacion,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
