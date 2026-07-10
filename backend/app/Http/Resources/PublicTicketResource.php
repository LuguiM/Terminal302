<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ventaHorario = $this->ventaHorario;
        $horario = $ventaHorario?->horario;
        $ruta = $horario?->ruta;

        return [
            'codigo_ticket' => $this->codigo_ticket,
            'estado' => [
                'id' => $this->estado?->id,
                'nombre' => $this->estado?->nombre,
            ],
            'ruta' => $ruta?->ruta,
            'denominacion' => $ruta?->denominacion,
            'operador' => [
                'id' => $horario?->operador?->id,
                'nombre_comercial' => $horario?->operador?->nombre_comercial,
            ],
            'dia' => [
                'id' => $horario?->dia?->id,
                'nombre' => $horario?->dia?->nombre,
                'orden' => $horario?->dia?->orden,
            ],
            'hora_salida' => substr((string) $horario?->hora_salida, 0, 5),
            'fecha_operacion' => $ventaHorario?->fecha_operacion?->toDateString(),
            'es_sobreventa' => (bool) $this->es_sobreventa,
            'tipo_envio' => [
                'id' => $this->tipoEnvio?->id,
                'nombre' => $this->tipoEnvio?->nombre,
            ],
        ];
    }
}
