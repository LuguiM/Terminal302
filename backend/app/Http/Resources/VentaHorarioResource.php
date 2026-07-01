<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VentaHorarioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'horario_id' => $this->horario_id,
            'fecha_operacion' => $this->fecha_operacion?->toDateString(),
            'venta_cerrada' => (bool) $this->venta_cerrada,
            'cerrada_por' => $this->whenLoaded('cerradaPor', fn (): ?array => $this->cerradaPor ? [
                'id' => $this->cerradaPor->id,
                'name' => $this->cerradaPor->name,
                'email' => $this->cerradaPor->email,
            ] : null),
            'fecha_cierre' => $this->fecha_cierre?->toISOString(),
            'motivo_cierre' => $this->motivo_cierre,
            'total_tickets_vendidos' => $this->total_tickets_vendidos,
            'total_tickets_sobreventa' => $this->total_tickets_sobreventa,
            'estado' => [
                'id' => $this->estado?->id,
                'nombre' => $this->estado?->nombre,
            ],
            'horario' => $this->whenLoaded('horario', fn (): array => [
                'id' => $this->horario?->id,
                'hora_salida' => substr((string) $this->horario?->hora_salida, 0, 5),
                'sobreventa_permitida' => (bool) $this->horario?->sobreventa_permitida,
                'ruta' => [
                    'id' => $this->horario?->ruta?->id,
                    'ruta' => $this->horario?->ruta?->ruta,
                    'denominacion' => $this->horario?->ruta?->denominacion,
                ],
                'operador' => [
                    'id' => $this->horario?->operador?->id,
                    'nombre_comercial' => $this->horario?->operador?->nombre_comercial,
                ],
                'bus' => [
                    'id' => $this->horario?->bus?->id,
                    'placa' => $this->horario?->bus?->placa,
                    'marca' => $this->horario?->bus?->marca,
                    'nombre_unidad' => $this->horario?->bus?->nombre_unidad,
                    'capacidad' => $this->horario?->bus?->capacidad,
                ],
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
