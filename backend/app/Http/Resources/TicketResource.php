<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'venta_horario_id' => $this->venta_horario_id,
            'vendedor' => $this->whenLoaded('vendedor', fn (): ?array => $this->vendedor ? [
                'id' => $this->vendedor->id,
                'name' => $this->vendedor->name,
                'email' => $this->vendedor->email,
            ] : null),
            'correo_destino' => $this->correo_destino,
            'telefono_destino' => $this->telefono_destino,
            'numero_asiento' => $this->numero_asiento,
            'es_sobreventa' => (bool) $this->es_sobreventa,
            'tipo_envio_id' => $this->tipo_envio_id,
            'tipo_envio' => $this->whenLoaded('tipoEnvio', fn (): ?array => $this->tipoEnvio ? [
                'id' => $this->tipoEnvio->id,
                'nombre' => $this->tipoEnvio->nombre,
                'descripcion' => $this->tipoEnvio->descripcion,
            ] : null),
            'estado' => [
                'id' => $this->estado?->id,
                'nombre' => $this->estado?->nombre,
            ],
            'qr_path' => $this->qr_path,
            'ticket_image_path' => $this->ticket_image_path,
            'image_url' => $this->ticket_image_path ? Storage::url($this->ticket_image_path) : null,
            'print_url' => $this->ticket_image_path ? Storage::url($this->ticket_image_path) : null,
            'ticket_plantilla_id' => $this->ticket_plantilla_id,
            'ticket_plantilla' => $this->whenLoaded('ticketPlantilla', fn (): ?array => $this->ticketPlantilla ? [
                'id' => $this->ticketPlantilla->id,
                'nombre' => $this->ticketPlantilla->nombre,
                'image_path' => $this->ticketPlantilla->image_path,
            ] : null),
            'venta_horario' => $this->whenLoaded('ventaHorario', fn (): ?array => $this->ventaHorario ? [
                'id' => $this->ventaHorario->id,
                'fecha_operacion' => $this->ventaHorario->fecha_operacion?->toDateString(),
                'venta_cerrada' => (bool) $this->ventaHorario->venta_cerrada,
                'total_tickets_vendidos' => $this->ventaHorario->total_tickets_vendidos,
                'total_tickets_sobreventa' => $this->ventaHorario->total_tickets_sobreventa,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
