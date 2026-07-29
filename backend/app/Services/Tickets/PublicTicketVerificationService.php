<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublicTicketVerificationService
{
    private const OPERATION_TIMEZONE = 'America/El_Salvador';

    private const VALID_CODES = [
        'usable',
        'wrong_date',
        'already_validated',
        'cancelled',
        'unsupported_status',
    ];

    /**
     * @return array{usable: bool, code: string, message: string, evaluated_at: string, source: string}
     */
    public function verify(Ticket $ticket): array
    {
        $payload = $this->payload($ticket);

        if (config('services.ticket_verification.driver', 'local') !== 'http') {
            return $this->localVerification($payload);
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('services.ticket_verification.base_url'), '/'))
                ->acceptJson()
                ->withHeaders([
                    'X-Internal-Token' => (string) config('services.ticket_verification.internal_token'),
                ])
                ->timeout((int) config('services.ticket_verification.timeout', 3))
                ->post('/tickets/verify', $payload);

            $verification = $response->json();

            if (! $response->successful() || ! $this->isValidVerification($verification)) {
                throw new \UnexpectedValueException('Invalid Lambda verification response.');
            }

            return [...$verification, 'source' => 'lambda'];
        } catch (Throwable $exception) {
            Log::warning('Public ticket verification used the Laravel fallback.', [
                'exception' => $exception::class,
            ]);

            return $this->localVerification($payload);
        }
    }

    /**
     * @return array{codigo_ticket: string, estado: string, fecha_operacion: string, current_date: string}
     */
    private function payload(Ticket $ticket): array
    {
        return [
            'codigo_ticket' => (string) $ticket->codigo_ticket,
            'estado' => (string) $ticket->estado?->nombre,
            'fecha_operacion' => (string) $ticket->ventaHorario?->fecha_operacion?->toDateString(),
            'current_date' => CarbonImmutable::now(self::OPERATION_TIMEZONE)->toDateString(),
        ];
    }

    /**
     * @param  array{codigo_ticket: string, estado: string, fecha_operacion: string, current_date: string}  $payload
     * @return array{usable: bool, code: string, message: string, evaluated_at: string, source: string}
     */
    private function localVerification(array $payload): array
    {
        [$usable, $code, $message] = match ($payload['estado']) {
            'Emitido' => $payload['fecha_operacion'] === $payload['current_date']
                ? [true, 'usable', 'El ticket esta emitido y corresponde a la fecha de operacion actual.']
                : [false, 'wrong_date', 'El ticket no corresponde a la fecha de operacion actual.'],
            'Validado' => [false, 'already_validated', 'El ticket ya fue validado.'],
            'Cancelado' => [false, 'cancelled', 'El ticket esta cancelado.'],
            default => [false, 'unsupported_status', 'El ticket no se encuentra en un estado utilizable.'],
        };

        return [
            'usable' => $usable,
            'code' => $code,
            'message' => $message,
            'evaluated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'source' => 'fallback',
        ];
    }

    private function isValidVerification(mixed $verification): bool
    {
        return is_array($verification)
            && is_bool($verification['usable'] ?? null)
            && in_array($verification['code'] ?? null, self::VALID_CODES, true)
            && is_string($verification['message'] ?? null)
            && is_string($verification['evaluated_at'] ?? null);
    }
}
