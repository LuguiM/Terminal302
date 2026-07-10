<?php

namespace App\Services;

use App\Mail\DigitalTicketMail;
use App\Models\Estado;
use App\Models\ProcesamientoEstado;
use App\Models\Ticket;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Throwable;

class TicketDigitalDeliveryService
{
    private const PENDING_DIRECTORY = 'ticket-events/pending';
    private const COMPLETED_DIRECTORY = 'ticket-events/completed';
    private const FAILED_DIRECTORY = 'ticket-events/failed';

    /**
     * @return array{processed:int, completed:int, failed:int, skipped:int}
     */
    public function processPending(?int $limit = null): array
    {
        $paths = collect(Storage::disk(config('filesystems.default'))->files(self::PENDING_DIRECTORY))
            ->filter(fn (string $path): bool => str_ends_with($path, '.json'))
            ->sort()
            ->values();

        if ($limit !== null && $limit > 0) {
            $paths = $paths->take($limit)->values();
        }

        $summary = [
            'processed' => 0,
            'completed' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($paths as $path) {
            $result = $this->processEvent($path);
            $summary['processed']++;
            $summary[$result]++;
        }

        return $summary;
    }

    public function processEvent(string $path): string
    {
        $disk = Storage::disk(config('filesystems.default'));
        $ticket = null;

        try {
            $payload = $this->readPayload($path);
            $ticket = Ticket::query()
                ->with([
                    'tipoEnvio',
                    'procesamientoEstado',
                    'ventaHorario.horario.ruta',
                    'ventaHorario.horario.operador',
                    'ventaHorario.horario.bus',
                    'ventaHorario.horario.dia',
                ])
                ->find($payload['ticket_id'] ?? null);

            if (! $ticket) {
                $this->moveEvent($path, self::FAILED_DIRECTORY);

                return 'failed';
            }

            if (! $ticket->tipoEnvio?->isDigital()) {
                $this->moveEvent($path, self::FAILED_DIRECTORY);
                $this->markFailed($ticket, 'El ticket no es digital.');

                return 'failed';
            }

            if (! $ticket->procesamientoEstado?->isPending() && ! $ticket->procesamientoEstado?->isFailed()) {
                return 'skipped';
            }

            $processingStatus = $this->processingStatus(ProcesamientoEstado::PROCESSING);
            $completedStatus = $this->processingStatus(ProcesamientoEstado::COMPLETED);

            if (! $processingStatus || ! $completedStatus) {
                throw new \RuntimeException('No se encontraron los estados de procesamiento requeridos.');
            }

            $ticket->forceFill([
                'procesamiento_estado_id' => $processingStatus->id,
                'processing_error' => null,
                'processed_at' => null,
            ])->save();

            if (! $ticket->correo_destino) {
                throw new \RuntimeException('El ticket digital no tiene correo destino.');
            }

            if (! $ticket->ticket_image_path || ! $disk->exists($ticket->ticket_image_path)) {
                throw new \RuntimeException('El ticket digital no tiene imagen final generada.');
            }

            Mail::to($ticket->correo_destino)->send(new DigitalTicketMail($ticket->fresh([
                'tipoEnvio',
                'procesamientoEstado',
                'ventaHorario.horario.ruta',
            ])));

            $completedPath = $this->moveEvent($path, self::COMPLETED_DIRECTORY);

            $ticket->forceFill([
                'procesamiento_estado_id' => $completedStatus->id,
                'processing_error' => null,
                'processed_at' => now(),
                'processing_event_path' => $completedPath,
            ])->save();

            // TODO: Integrar proveedor de WhatsApp cuando se defina.

            return 'completed';
        } catch (Throwable $exception) {
            if ($ticket) {
                $failedPath = $this->moveEvent($path, self::FAILED_DIRECTORY);
                $this->markFailed($ticket, $exception->getMessage(), $failedPath);
            } elseif ($disk->exists($path)) {
                $this->moveEvent($path, self::FAILED_DIRECTORY);
            }

            return 'failed';
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function readPayload(string $path): array
    {
        $content = Storage::disk(config('filesystems.default'))->get($path);

        return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
    }

    private function moveEvent(string $path, string $targetDirectory): string
    {
        $disk = Storage::disk(config('filesystems.default'));
        $targetPath = $targetDirectory.'/'.basename($path);

        if ($disk->exists($path)) {
            $disk->put($targetPath, $disk->get($path));
            $disk->delete($path);
        }

        return $targetPath;
    }

    private function markFailed(Ticket $ticket, string $message, ?string $eventPath = null): void
    {
        $failedStatus = $this->processingStatus(ProcesamientoEstado::FAILED);

        $ticket->forceFill([
            'procesamiento_estado_id' => $failedStatus?->id,
            'processing_error' => $message,
            'processed_at' => null,
            'processing_event_path' => $eventPath ?? $ticket->processing_event_path,
        ])->save();
    }

    private function processingStatus(string $statusName): ?ProcesamientoEstado
    {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return null;
        }

        return ProcesamientoEstado::query()
            ->where('estado_id', $activeStatus->id)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($statusName)])
            ->first();
    }
}
