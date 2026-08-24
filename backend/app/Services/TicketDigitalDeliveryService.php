<?php

namespace App\Services;

use App\Mail\DigitalTicketMail;
use App\Models\Estado;
use App\Models\ProcesamientoEstado;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Throwable;

class TicketDigitalDeliveryService
{
    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    public const PROCESSING = 'processing';

    public const SKIPPED = 'skipped';

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
            $summary[$result === self::PROCESSING ? self::SKIPPED : $result]++;
        }

        return $summary;
    }

    /**
     * Procesa un evento usando la logica de Laravel. La Lambda pasa
     * $archiveEvent=false porque ella es responsable de mover el objeto S3.
     */
    public function processEvent(string $path, bool $archiveEvent = true): string
    {
        $disk = Storage::disk(config('filesystems.default'));
        $ticket = null;

        try {
            $payload = $this->readPayload($path);
            $this->validatePayload($payload, $path);

            $ticket = Ticket::query()
                ->with(['tipoEnvio', 'procesamientoEstado'])
                ->find($payload['ticket_id']);

            $result = $this->processTicket($ticket, $path);

            if ($archiveEvent && in_array($result, [self::COMPLETED, self::FAILED], true)) {
                $this->moveEvent(
                    $path,
                    $result === self::COMPLETED ? self::COMPLETED_DIRECTORY : self::FAILED_DIRECTORY,
                );
            }

            return $result;
        } catch (Throwable $exception) {
            Log::warning('digital_ticket_delivery_failed', [
                'event_path' => $path,
                'ticket_id' => $ticket?->id,
                'error_type' => $exception::class,
            ]);

            $failedPath = $this->targetPath($path, self::FAILED_DIRECTORY);

            if ($ticket) {
                $this->markFailed($ticket, $path, $this->safeError($exception), $failedPath);
            }

            if ($archiveEvent && $disk->exists($path)) {
                $this->moveEvent($path, self::FAILED_DIRECTORY);
            }

            return self::FAILED;
        }
    }

    private function processTicket(?Ticket $ticket, string $path): string
    {
        if (! $ticket) {
            throw new \RuntimeException('No existe el ticket indicado por el evento.');
        }

        if (! $ticket->tipoEnvio?->isDigital()) {
            throw new \RuntimeException('El ticket no es digital.');
        }

        if ($ticket->procesamientoEstado?->isCompleted()) {
            return self::COMPLETED;
        }

        if ($ticket->processing_event_path !== $path) {
            throw new \RuntimeException('El evento ya no es el vigente para este ticket.');
        }

        if ($ticket->procesamientoEstado?->isFailed()) {
            return self::FAILED;
        }

        if ($ticket->procesamientoEstado?->isProcessing()) {
            return self::PROCESSING;
        }

        if (! $ticket->procesamientoEstado?->isPending()) {
            return self::SKIPPED;
        }

        $processingStatus = $this->processingStatus(ProcesamientoEstado::PROCESSING);
        $completedStatus = $this->processingStatus(ProcesamientoEstado::COMPLETED);

        if (! $processingStatus || ! $completedStatus) {
            throw new \RuntimeException('No se encontraron los estados de procesamiento requeridos.');
        }

        // La actualizacion condicional es la barrera de idempotencia. Solo un
        // consumidor puede pasar de Pendiente a Procesando para este evento.
        $claimed = Ticket::query()
            ->whereKey($ticket->id)
            ->where('processing_event_path', $path)
            ->where('procesamiento_estado_id', $ticket->procesamiento_estado_id)
            ->update([
                'procesamiento_estado_id' => $processingStatus->id,
                'processing_error' => null,
                'processed_at' => null,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            $current = $ticket->fresh(['procesamientoEstado']);

            return $current?->procesamientoEstado?->isCompleted()
                ? self::COMPLETED
                : self::PROCESSING;
        }

        $ticket->forceFill(['procesamiento_estado_id' => $processingStatus->id]);
        $ticket->setRelation('procesamientoEstado', $processingStatus);

        if (! $ticket->correo_destino) {
            throw new \RuntimeException('El ticket digital no tiene correo destino.');
        }

        $disk = Storage::disk(config('filesystems.default'));

        if (! $ticket->ticket_image_path || ! $disk->exists($ticket->ticket_image_path)) {
            throw new \RuntimeException('El ticket digital no tiene imagen final generada.');
        }

        Mail::to($ticket->correo_destino)->send(new DigitalTicketMail($ticket->fresh([
            'tipoEnvio',
            'procesamientoEstado',
            'ventaHorario.horario.ruta',
        ])));

        $ticket->forceFill([
            'procesamiento_estado_id' => $completedStatus->id,
            'processing_error' => null,
            'processed_at' => now(),
            'processing_event_path' => $this->targetPath($path, self::COMPLETED_DIRECTORY),
        ])->save();

        // TODO: Integrar proveedor de WhatsApp cuando se defina.

        return self::COMPLETED;
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

    /** @param array<string, mixed> $payload */
    private function validatePayload(array $payload, string $path): void
    {
        $eventId = $payload['event_id'] ?? null;
        $expectedEventId = pathinfo($path, PATHINFO_FILENAME);

        if (($payload['schema_version'] ?? null) !== 1
            || ! is_string($eventId)
            || $eventId !== $expectedEventId
            || ! filter_var($eventId, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i']])
            || ! is_int($payload['ticket_id'] ?? null)
            || $payload['ticket_id'] < 1) {
            throw new \RuntimeException('El evento de entrega digital no cumple el esquema esperado.');
        }
    }

    private function moveEvent(string $path, string $targetDirectory): string
    {
        $disk = Storage::disk(config('filesystems.default'));
        $targetPath = $this->targetPath($path, $targetDirectory);

        if ($disk->exists($path)) {
            if (! $disk->put($targetPath, $disk->get($path))) {
                throw new \RuntimeException('No se pudo archivar el evento de entrega digital.');
            }

            $disk->delete($path);
        }

        return $targetPath;
    }

    private function targetPath(string $path, string $targetDirectory): string
    {
        return $targetDirectory.'/'.basename($path);
    }

    private function markFailed(Ticket $ticket, string $sourcePath, string $message, string $eventPath): void
    {
        $failedStatus = $this->processingStatus(ProcesamientoEstado::FAILED);

        $completedStatus = $this->processingStatus(ProcesamientoEstado::COMPLETED);

        if (! $failedStatus) {
            return;
        }

        Ticket::query()
            ->whereKey($ticket->id)
            ->where('processing_event_path', $sourcePath)
            ->when($completedStatus, fn ($query) => $query->where('procesamiento_estado_id', '!=', $completedStatus->id))
            ->update([
                'procesamiento_estado_id' => $failedStatus->id,
                'processing_error' => $message,
                'processed_at' => null,
                'processing_event_path' => $eventPath,
                'updated_at' => now(),
            ]);
    }

    private function safeError(Throwable $exception): string
    {
        return mb_substr($exception->getMessage() ?: 'Fallo no especificado de entrega digital.', 0, 1000);
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
