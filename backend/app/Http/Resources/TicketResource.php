<?php

namespace App\Http\Resources;

use App\Support\StorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo_ticket' => $this->codigo_ticket,
            'correo_destino' => $this->correo_destino,
            'telefono_destino' => $this->telefono_destino,
            'numero_asiento' => $this->numero_asiento,
            'es_sobreventa' => (bool) $this->es_sobreventa,
            'tipo_envio' => $this->whenLoaded('tipoEnvio', fn (): ?array => $this->tipoEnvio ? [
                'nombre' => $this->tipoEnvio->nombre,
            ] : null),
            'estado' => [
                'nombre' => $this->estado?->nombre,
            ],
            'image_url' => StorageUrl::for($this->ticket_image_path),
            'print_url' => StorageUrl::for($this->ticket_image_path),
            'procesamiento_estado' => $this->whenLoaded('procesamientoEstado', fn (): ?array => $this->procesamientoEstado ? [
                'nombre' => $this->procesamientoEstado->nombre,
            ] : null),
            'venta_horario' => $this->whenLoaded('ventaHorario', fn (): ?array => $this->ventaHorario ? [
                'venta_cerrada' => (bool) $this->ventaHorario->venta_cerrada,
                'horario' => $this->ventaHorario->relationLoaded('horario') && $this->ventaHorario->horario ? [
                    'hora_salida' => substr((string) $this->ventaHorario->horario->hora_salida, 0, 5),
                    'ruta' => $this->ventaHorario->horario->ruta ? [
                        'ruta' => $this->ventaHorario->horario->ruta->ruta,
                    ] : null,
                ] : null,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
