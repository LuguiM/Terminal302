<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TicketProcessingEventService
{
    public function publish(Ticket $ticket): string
    {
        $eventId = (string) Str::uuid();
        $path = "ticket-events/pending/{$eventId}.json";

        // Persistir primero el identificador evita que S3 invoque al consumidor
        // antes de que Laravel pueda reconocer el evento como el vigente.
        $ticket->forceFill(['processing_event_path' => $path])->save();

        $stored = Storage::disk(config('filesystems.default'))->put(
            $path,
            json_encode(
                $this->payload($ticket, $eventId),
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ),
        );

        if (! $stored) {
            throw new RuntimeException('No se pudo publicar el evento de entrega digital.');
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Ticket $ticket, string $eventId): array
    {
        return [
            'schema_version' => 1,
            'event_id' => $eventId,
            'ticket_id' => $ticket->id,
            'created_at' => now()->utc()->toIso8601String(),
        ];
    }
}
