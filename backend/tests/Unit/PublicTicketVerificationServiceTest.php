<?php

namespace Tests\Unit;

use App\Models\Estado;
use App\Models\Ticket;
use App\Models\VentaHorario;
use App\Services\Tickets\PublicTicketVerificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PublicTicketVerificationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_local_driver_matches_ticket_status_and_operation_date(): void
    {
        CarbonImmutable::setTestNow('2026-07-16 08:00:00');
        config()->set('services.ticket_verification.driver', 'local');
        $service = app(PublicTicketVerificationService::class);

        $usable = $service->verify($this->ticket('Emitido', '2026-07-16'));
        $wrongDate = $service->verify($this->ticket('Emitido', '2026-07-15'));
        $validated = $service->verify($this->ticket('Validado', '2026-07-16'));

        $this->assertTrue($usable['usable']);
        $this->assertSame('usable', $usable['code']);
        $this->assertSame('fallback', $usable['source']);
        $this->assertSame('wrong_date', $wrongDate['code']);
        $this->assertSame('already_validated', $validated['code']);
    }

    public function test_http_driver_returns_lambda_verification_and_only_sends_public_fields(): void
    {
        CarbonImmutable::setTestNow('2026-07-16 08:00:00');
        config()->set('services.ticket_verification', [
            'driver' => 'http',
            'base_url' => 'http://lambda.test',
            'internal_token' => 'secret-token',
            'timeout' => 1,
        ]);
        Http::fake([
            'lambda.test/*' => Http::response([
                'usable' => true,
                'code' => 'usable',
                'message' => 'Verificado por Lambda.',
                'evaluated_at' => '2026-07-16T14:00:00Z',
            ]),
        ]);

        $verification = app(PublicTicketVerificationService::class)
            ->verify($this->ticket('Emitido', '2026-07-16'));

        $this->assertSame('lambda', $verification['source']);
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->url() === 'http://lambda.test/tickets/verify'
                && $request->hasHeader('X-Internal-Token', 'secret-token')
                && array_keys($data) === ['codigo_ticket', 'estado', 'fecha_operacion', 'current_date']
                && ! isset($data['correo_destino'], $data['telefono_destino'], $data['vendedor']);
        });
    }

    public function test_http_errors_and_invalid_responses_use_fallback(): void
    {
        CarbonImmutable::setTestNow('2026-07-16 08:00:00');
        config()->set('services.ticket_verification', [
            'driver' => 'http',
            'base_url' => 'http://lambda.test',
            'internal_token' => 'secret-token',
            'timeout' => 1,
        ]);
        Log::spy();
        $service = app(PublicTicketVerificationService::class);

        Http::fake(['lambda.test/*' => Http::response(['message' => 'Unavailable'], 500)]);
        $serverError = $service->verify($this->ticket('Emitido', '2026-07-16'));

        Http::fake(['lambda.test/*' => Http::response(['usable' => 'yes'])]);
        $invalidJson = $service->verify($this->ticket('Emitido', '2026-07-16'));

        Http::fake(['lambda.test/*' => Http::failedConnection('Connection timed out')]);
        $timeout = $service->verify($this->ticket('Emitido', '2026-07-16'));

        $this->assertSame('fallback', $serverError['source']);
        $this->assertSame('fallback', $invalidJson['source']);
        $this->assertSame('fallback', $timeout['source']);
        $this->assertTrue($serverError['usable']);
        Log::shouldHaveReceived('warning')->times(3);
    }

    private function ticket(string $status, string $operationDate): Ticket
    {
        $ticket = new Ticket([
            'codigo_ticket' => 'TKT-UNIT-001',
            'correo_destino' => 'private@example.test',
            'telefono_destino' => '77777777',
        ]);
        $ticket->setRelation('estado', new Estado(['nombre' => $status]));
        $ticket->setRelation('ventaHorario', new VentaHorario(['fecha_operacion' => $operationDate]));

        return $ticket;
    }
}
