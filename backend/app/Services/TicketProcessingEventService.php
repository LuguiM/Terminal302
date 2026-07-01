<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TipoEnvio;
use Illuminate\Support\Facades\Storage;

class TicketProcessingEventService
{
    public function publish(Ticket $ticket): string
    {
        $ticket->loadMissing([
            'tipoEnvio',
            'procesamientoEstado',
            'ventaHorario.horario.ruta',
            'ventaHorario.horario.operador',
            'ventaHorario.horario.bus',
            'ventaHorario.horario.dia',
        ]);

        $path = "ticket-events/pending/{$ticket->codigo_ticket}.json";

        // En AWS, este archivo podra activar una Lambda mediante S3 Trigger.
        Storage::disk(config('filesystems.default'))->put(
            $path,
            json_encode($this->payload($ticket), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Ticket $ticket): array
    {
        $ventaHorario = $ticket->ventaHorario;
        $horario = $ventaHorario?->horario;
        $ruta = $horario?->ruta;
        $operador = $horario?->operador;
        $bus = $horario?->bus;
        $tipoEnvioNombre = mb_strtolower((string) $ticket->tipoEnvio?->nombre);

        return [
            'ticket_id' => $ticket->id,
            'codigo_ticket' => $ticket->codigo_ticket,
            'venta_horario_id' => $ticket->venta_horario_id,
            'vendedor_id' => $ticket->vendedor_id,
            'tipo_envio' => $ticket->tipoEnvio?->nombre,
            'correo_destino' => $ticket->correo_destino,
            'telefono_destino' => $ticket->telefono_destino,
            'ticket_plantilla_id' => $ticket->ticket_plantilla_id,
            'procesamiento_estado_id' => $ticket->procesamiento_estado_id,
            'procesamiento_estado' => $ticket->procesamientoEstado?->nombre,
            'qr_path' => $ticket->qr_path,
            'ticket_image_path' => $ticket->ticket_image_path,
            'ruta' => [
                'id' => $ruta?->id,
                'ruta' => $ruta?->ruta,
                'denominacion' => $ruta?->denominacion,
                'tarifa' => $ruta?->tarifa,
            ],
            'operador' => [
                'id' => $operador?->id,
                'nombre_comercial' => $operador?->nombre_comercial,
            ],
            'bus' => [
                'id' => $bus?->id,
                'placa' => $bus?->placa,
                'marca' => $bus?->marca,
                'nombre_unidad' => $bus?->nombre_unidad,
            ],
            'horario' => [
                'id' => $horario?->id,
                'hora_salida' => $horario?->hora_salida,
                'dia' => $horario?->dia?->nombre,
            ],
            'venta_horario' => [
                'id' => $ventaHorario?->id,
                'fecha_operacion' => $ventaHorario?->fecha_operacion?->toDateString(),
            ],
            'flags' => [
                'es_sobreventa' => (bool) $ticket->es_sobreventa,
                'requiere_envio_correo' => $tipoEnvioNombre === TipoEnvio::DIGITAL && ! empty($ticket->correo_destino),
                'requiere_envio_whatsapp' => ! empty($ticket->telefono_destino),
            ],
        ];
    }
}
