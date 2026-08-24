<?php

namespace App\Http\Resources;

use App\Support\StorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'image_url' => StorageUrl::for($this->image_path),
            'download_url' => $this->id ? url("/api/admin/ticket-plantillas/{$this->id}/download") : null,
            'image_size_bytes' => $this->image_path && Storage::exists($this->image_path)
                ? Storage::size($this->image_path)
                : null,
            'qr_location' => $this->qr_location,
            'precio_location' => $this->precio_location,
            'fecha_hora_location' => $this->fecha_hora_location,
            'asiento_location' => $this->asiento_location,
            'codigo_ticket_location' => $this->codigo_ticket_location,
            'ruta_location' => $this->ruta_location,
            'salida_location' => $this->salida_location,
            'operador_location' => $this->operador_location,
            'estado' => [
                'nombre' => $this->estado?->nombre,
            ],
            'es_predeterminada' => $this->es_predeterminada,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
