<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Dia;
use App\Models\Estado;
use App\Models\Horario;
use App\Models\Operador;
use App\Models\OperadorRuta;
use App\Models\ProcesamientoEstado;
use App\Models\Role;
use App\Models\Ruta;
use App\Models\Ticket;
use App\Models\TicketPlantilla;
use App\Models\TipoEnvio;
use App\Models\TipoBus;
use App\Models\TipoOperador;
use App\Models\User;
use App\Models\VentaHorario;
use App\Mail\DigitalTicketMail;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake(config('filesystems.default'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_vendedor_can_list_only_own_tickets_paginated_with_filters(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $otherVendedor = $this->createUser('vendedor', 'other@example.test');
        $plantilla = $this->createTicketPlantilla();
        $tipoEnvio = $this->createTipoEnvio(TipoEnvio::IMPRESO);
        $ventaHorario = $this->createVentaHorario($this->createHorarioContext());
        $ownTicket = $this->createTicket($ventaHorario, $vendedor, $plantilla, $tipoEnvio, 'TKT-OWN-001');
        $ownTicket->forceFill([
            'created_at' => CarbonImmutable::parse('2026-07-02 10:00:00', 'America/El_Salvador'),
            'updated_at' => CarbonImmutable::parse('2026-07-02 10:00:00', 'America/El_Salvador'),
        ])->save();
        $otherOwnTicket = $this->createTicket($ventaHorario, $vendedor, $plantilla, $tipoEnvio, 'TKT-OWN-002');
        $otherOwnTicket->forceFill([
            'created_at' => CarbonImmutable::parse('2026-07-03 10:00:00', 'America/El_Salvador'),
            'updated_at' => CarbonImmutable::parse('2026-07-03 10:00:00', 'America/El_Salvador'),
        ])->save();
        $this->createTicket($ventaHorario, $otherVendedor, $plantilla, $tipoEnvio, 'TKT-OTHER-001');

        Sanctum::actingAs($vendedor);

        $this->getJson("/api/vendedor/tickets?venta_horario_id={$ventaHorario->id}&estado_id=".Estado::EMITIDO_ID.'&codigo_ticket=own&tipo_envio_id='.$tipoEnvio->id.'&fecha=2026-07-02')
            ->assertOk()
            ->assertJsonStructure([
                'tickets' => [
                    [
                        'id',
                        'codigo_ticket',
                        'correo_destino',
                        'telefono_destino',
                        'numero_asiento',
                        'es_sobreventa',
                        'tipo_envio',
                        'estado',
                        'image_url',
                        'print_url',
                        'procesamiento_estado',
                        'venta_horario',
                        'created_at',
                    ],
                ],
                'pagination' => [
                    'page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ])
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('tickets.0.id', $ownTicket->id)
            ->assertJsonPath('tickets.0.codigo_ticket', 'TKT-OWN-001')
            ->assertJsonPath('tickets.0.venta_horario.horario.ruta.ruta', $ventaHorario->horario->ruta->ruta)
            ->assertJsonPath('tickets.0.venta_horario.horario.hora_salida', '08:00');
    }

    public function test_vendedor_can_list_active_tipo_envios(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $impreso = $this->createTipoEnvio(TipoEnvio::IMPRESO);
        $digital = $this->createTipoEnvio(TipoEnvio::DIGITAL);
        $this->createTipoEnvio('inactivo', Estado::DESACTIVADO_ID, 'Desactivado');

        Sanctum::actingAs($vendedor);

        $this->getJson('/api/vendedor/tipo-envios')
            ->assertOk()
            ->assertJsonPath('tipo_envios.0.id', $impreso->id)
            ->assertJsonPath('tipo_envios.0.nombre', TipoEnvio::IMPRESO)
            ->assertJsonPath('tipo_envios.1.id', $digital->id)
            ->assertJsonPath('tipo_envios.1.nombre', TipoEnvio::DIGITAL)
            ->assertJsonCount(2, 'tipo_envios');
    }

    public function test_vendedor_can_sell_printed_tickets_with_default_template_and_issued_status(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $plantilla = $this->createTicketPlantilla();
        $tipoEnvio = $this->createTipoEnvio(TipoEnvio::IMPRESO);
        $ventaHorario = $this->createVentaHorario($this->createHorarioContext(capacidad: 3));

        Sanctum::actingAs($vendedor);

        $response = $this->postJson('/api/vendedor/tickets', [
            'venta_horario_id' => $ventaHorario->id,
            'cantidad' => 2,
            'tipo_envio_id' => $tipoEnvio->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Tickets generados correctamente.')
            ->assertJsonPath('resumen.cantidad_solicitada', 2)
            ->assertJsonPath('resumen.cantidad_generada', 2)
            ->assertJsonPath('resumen.tickets_normales', 2)
            ->assertJsonPath('resumen.tickets_sobreventa', 0)
            ->assertJsonPath('resumen.total_tickets_vendidos', 2)
            ->assertJsonPath('resumen.total_tickets_sobreventa', 0)
            ->assertJsonPath('resumen.venta_cerrada', false)
            ->assertJsonMissingPath('tickets')
            ->assertJsonMissingPath('event_paths')
            ->assertJsonStructure([
                'impresion' => [
                    'tickets' => [
                        [
                            'id',
                            'codigo_ticket',
                            'image_url',
                            'print_url',
                        ],
                    ],
                ],
            ]);

        $storedTicket = Ticket::query()->firstOrFail();
        $this->assertNotNull($response->json('impresion.tickets.0.image_url'));
        Storage::disk(config('filesystems.default'))->assertExists($storedTicket->qr_path);
        Storage::disk(config('filesystems.default'))->assertExists($storedTicket->ticket_image_path);

        $this->assertDatabaseCount('tickets', 2);
        $this->assertDatabaseHas('ventas_horarios', [
            'id' => $ventaHorario->id,
            'total_tickets_vendidos' => 2,
            'total_tickets_sobreventa' => 0,
            'venta_cerrada' => false,
        ]);
        $this->assertSame(2, Ticket::query()->distinct('codigo_ticket')->count('codigo_ticket'));
        Mail::assertNothingSent();
    }

    public function test_vendedor_can_sell_digital_tickets_and_publish_processing_events(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $this->createTicketPlantilla();
        $pending = $this->createProcesamientoEstado(ProcesamientoEstado::PENDING);
        $this->createProcesamientoEstado(ProcesamientoEstado::FAILED);
        $tipoEnvio = $this->createTipoEnvio(TipoEnvio::DIGITAL);
        $ventaHorario = $this->createVentaHorario($this->createHorarioContext());

        Sanctum::actingAs($vendedor);

        $response = $this->postJson('/api/vendedor/tickets', [
            'venta_horario_id' => $ventaHorario->id,
            'cantidad' => 3,
            'tipo_envio_id' => $tipoEnvio->id,
            'correo_destino' => 'cliente@example.test',
            'telefono_destino' => '77777777',
        ])
            ->assertCreated()
            ->assertJsonMissingPath('tickets')
            ->assertJsonMissingPath('event_paths');

        $ticket = Ticket::query()->firstOrFail();
        $expectedPath = "ticket-events/pending/{$ticket->codigo_ticket}.json";

        Storage::disk(config('filesystems.default'))->assertExists($expectedPath);
        $this->assertSame($expectedPath, $ticket->fresh()->processing_event_path);
        $this->assertStringContainsString('cliente@example.test', Storage::disk(config('filesystems.default'))->get($expectedPath));
        $this->assertStringContainsString('77777777', Storage::disk(config('filesystems.default'))->get($expectedPath));
        Mail::assertNothingSent();
    }

    public function test_digital_delivery_command_processes_pending_ticket_and_sends_email(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $this->createTicketPlantilla();
        $this->createProcesamientoEstado(ProcesamientoEstado::PENDING);
        $this->createProcesamientoEstado(ProcesamientoEstado::PROCESSING);
        $completed = $this->createProcesamientoEstado(ProcesamientoEstado::COMPLETED);
        $this->createProcesamientoEstado(ProcesamientoEstado::FAILED);
        $tipoEnvio = $this->createTipoEnvio(TipoEnvio::DIGITAL);
        $ventaHorario = $this->createVentaHorario($this->createHorarioContext());

        Sanctum::actingAs($vendedor);

        $this->postJson('/api/vendedor/tickets', [
            'venta_horario_id' => $ventaHorario->id,
            'cantidad' => 1,
            'tipo_envio_id' => $tipoEnvio->id,
            'correo_destino' => 'cliente@example.test',
        ])->assertCreated();

        $ticket = Ticket::query()->firstOrFail();
        $pendingPath = "ticket-events/pending/{$ticket->codigo_ticket}.json";
        $completedPath = "ticket-events/completed/{$ticket->codigo_ticket}.json";

        Storage::disk(config('filesystems.default'))->assertExists($pendingPath);

        $this->artisan('tickets:process-digital-deliveries')
            ->assertExitCode(0);

        $ticket->refresh();

        $this->assertSame($completed->id, $ticket->procesamiento_estado_id);
        $this->assertNotNull($ticket->processed_at);
        $this->assertNull($ticket->processing_error);
        $this->assertSame($completedPath, $ticket->processing_event_path);
        Storage::disk(config('filesystems.default'))->assertMissing($pendingPath);
        Storage::disk(config('filesystems.default'))->assertExists($completedPath);
        Mail::assertSent(DigitalTicketMail::class, function (DigitalTicketMail $mail): bool {
            $attachment = $mail->attachments()[0] ?? null;

            return $mail->hasTo('cliente@example.test')
                && $mail->ticket->codigo_ticket !== null
                && $attachment?->as === $mail->ticket->codigo_ticket.'.png'
                && $attachment?->mime === 'image/png';
        });
    }

    public function test_digital_delivery_command_marks_ticket_as_failed_when_processing_fails(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $plantilla = $this->createTicketPlantilla();
        $digital = $this->createTipoEnvio(TipoEnvio::DIGITAL);
        $pending = $this->createProcesamientoEstado(ProcesamientoEstado::PENDING);
        $this->createProcesamientoEstado(ProcesamientoEstado::PROCESSING);
        $failed = $this->createProcesamientoEstado(ProcesamientoEstado::FAILED);
        $ventaHorario = $this->createVentaHorario($this->createHorarioContext());
        $ticket = $this->createTicket(
            $ventaHorario,
            $vendedor,
            $plantilla,
            $digital,
            'TKT-DIGITAL-FAILED',
            procesamientoEstado: $pending,
        );
        $eventPath = "ticket-events/pending/{$ticket->codigo_ticket}.json";
        Storage::put($eventPath, json_encode([
            'ticket_id' => $ticket->id,
            'codigo_ticket' => $ticket->codigo_ticket,
        ]));

        $this->artisan('tickets:process-digital-deliveries')
            ->assertExitCode(1);

        $ticket->refresh();

        $this->assertSame($failed->id, $ticket->procesamiento_estado_id);
        $this->assertNull($ticket->processed_at);
        $this->assertNotNull($ticket->processing_error);
        $this->assertSame("ticket-events/failed/{$ticket->codigo_ticket}.json", $ticket->processing_event_path);
        Storage::disk(config('filesystems.default'))->assertMissing($eventPath);
        Storage::disk(config('filesystems.default'))->assertExists("ticket-events/failed/{$ticket->codigo_ticket}.json");
        Mail::assertNothingSent();
    }

    public function test_vendedor_can_sell_overbooking_when_allowed(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $this->createTicketPlantilla();
        $tipoEnvio = $this->createTipoEnvio(TipoEnvio::IMPRESO);
        $ventaHorario = $this->createVentaHorario(
            horario: $this->createHorarioContext(capacidad: 2, sobreventaPermitida: true),
            totalVendidos: 1,
        );

        Sanctum::actingAs($vendedor);

        $this->postJson('/api/vendedor/tickets', [
            'venta_horario_id' => $ventaHorario->id,
            'cantidad' => 3,
            'tipo_envio_id' => $tipoEnvio->id,
        ])
            ->assertCreated()
            ->assertJsonPath('resumen.tickets_normales', 1)
            ->assertJsonPath('resumen.tickets_sobreventa', 2)
            ->assertJsonPath('resumen.total_tickets_vendidos', 4)
            ->assertJsonPath('resumen.total_tickets_sobreventa', 2)
            ->assertJsonMissingPath('tickets');

        $this->assertSame(1, Ticket::query()->where('es_sobreventa', false)->count());
        $this->assertSame(2, Ticket::query()->where('es_sobreventa', true)->count());

        $this->assertDatabaseHas('ventas_horarios', [
            'id' => $ventaHorario->id,
            'total_tickets_vendidos' => 4,
            'total_tickets_sobreventa' => 2,
            'venta_cerrada' => false,
        ]);
    }

    public function test_vendedor_can_list_digital_deliveries_with_filters(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $otherVendedor = $this->createUser('vendedor', 'other-delivery@example.test');
        $plantilla = $this->createTicketPlantilla();
        $digital = $this->createTipoEnvio(TipoEnvio::DIGITAL);
        $impreso = $this->createTipoEnvio(TipoEnvio::IMPRESO);
        $pending = $this->createProcesamientoEstado(ProcesamientoEstado::PENDING);
        $ventaHorario = $this->createVentaHorario($this->createHorarioContext());
        $digitalTicket = $this->createTicket(
            $ventaHorario,
            $vendedor,
            $plantilla,
            $digital,
            'TKT-DIGITAL-001',
            procesamientoEstado: $pending,
        );
        $this->createTicket($ventaHorario, $vendedor, $plantilla, $impreso, 'TKT-PRINT-001');
        $this->createTicket($ventaHorario, $otherVendedor, $plantilla, $digital, 'TKT-DIGITAL-OTHER', procesamientoEstado: $pending);

        Sanctum::actingAs($vendedor);

        $this->getJson("/api/vendedor/tickets/entregas?procesamiento_estado_id={$pending->id}&venta_horario_id={$ventaHorario->id}&codigo_ticket=TKT-DIGITAL-001&fecha=".$digitalTicket->created_at->toDateString())
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('tickets.0.id', $digitalTicket->id)
            ->assertJsonPath('tickets.0.procesamiento_estado.nombre', ProcesamientoEstado::PENDING);
    }

    public function test_vendedor_can_retry_digital_ticket_processing(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $plantilla = $this->createTicketPlantilla();
        $digital = $this->createTipoEnvio(TipoEnvio::DIGITAL);
        $pending = $this->createProcesamientoEstado(ProcesamientoEstado::PENDING);
        $failed = $this->createProcesamientoEstado(ProcesamientoEstado::FAILED);
        $ventaHorario = $this->createVentaHorario($this->createHorarioContext());
        $ticket = $this->createTicket(
            $ventaHorario,
            $vendedor,
            $plantilla,
            $digital,
            'TKT-RETRY-001',
            procesamientoEstado: $failed,
            processingError: 'Error anterior',
        );

        Sanctum::actingAs($vendedor);

        $this->postJson("/api/vendedor/tickets/{$ticket->id}/retry-processing")
            ->assertOk()
            ->assertJsonPath('message', 'Procesamiento del ticket reintentado correctamente.')
            ->assertJsonPath('ticket.procesamiento_estado.nombre', ProcesamientoEstado::PENDING)
            ->assertJsonMissingPath('ticket.procesamiento_estado_id')
            ->assertJsonMissingPath('ticket.processing_error')
            ->assertJsonMissingPath('processing_event_path');

        Storage::disk(config('filesystems.default'))->assertExists("ticket-events/pending/{$ticket->codigo_ticket}.json");
    }

    public function test_retry_processing_rejects_non_digital_or_foreign_tickets(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $otherVendedor = $this->createUser('vendedor', 'other-retry@example.test');
        $plantilla = $this->createTicketPlantilla();
        $impreso = $this->createTipoEnvio(TipoEnvio::IMPRESO);
        $digital = $this->createTipoEnvio(TipoEnvio::DIGITAL);
        $failed = $this->createProcesamientoEstado(ProcesamientoEstado::FAILED);
        $ventaHorario = $this->createVentaHorario($this->createHorarioContext());
        $printedTicket = $this->createTicket($ventaHorario, $vendedor, $plantilla, $impreso, 'TKT-PRINT-RETRY');
        $foreignTicket = $this->createTicket($ventaHorario, $otherVendedor, $plantilla, $digital, 'TKT-FOREIGN-RETRY', procesamientoEstado: $failed);

        Sanctum::actingAs($vendedor);

        $this->postJson("/api/vendedor/tickets/{$printedTicket->id}/retry-processing")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El ticket no es digital y no requiere reprocesamiento.');

        $this->postJson("/api/vendedor/tickets/{$foreignTicket->id}/retry-processing")
            ->assertForbidden()
            ->assertJsonPath('message', 'El ticket no pertenece al vendedor autenticado.');
    }

    public function test_vendedor_can_get_print_data_without_changing_ticket_status(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $plantilla = $this->createTicketPlantilla();
        $digital = $this->createTipoEnvio(TipoEnvio::DIGITAL);
        $failed = $this->createProcesamientoEstado(ProcesamientoEstado::FAILED);
        $ventaHorario = $this->createVentaHorario($this->createHorarioContext());
        $ticket = $this->createTicket(
            $ventaHorario,
            $vendedor,
            $plantilla,
            $digital,
            'TKT-PRINT-DATA',
            ticketImagePath: 'tickets/final/TKT-PRINT-DATA.png',
            procesamientoEstado: $failed,
        );

        Sanctum::actingAs($vendedor);

        $this->getJson("/api/vendedor/tickets/{$ticket->id}/print")
            ->assertOk()
            ->assertJsonPath('image_url', Storage::url('tickets/final/TKT-PRINT-DATA.png'))
            ->assertJsonMissingPath('ticket')
            ->assertJsonMissingPath('printable_data');

        $this->assertSame($failed->id, $ticket->fresh()->procesamiento_estado_id);
    }

    public function test_vendedor_can_download_template_image_for_own_ticket(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $otherVendedor = $this->createUser('vendedor', 'other-template@example.test');
        $plantilla = $this->createTicketPlantilla();
        $tipoEnvio = $this->createTipoEnvio(TipoEnvio::IMPRESO);
        $ventaHorario = $this->createVentaHorario($this->createHorarioContext());
        $ticket = $this->createTicket($ventaHorario, $vendedor, $plantilla, $tipoEnvio, 'TKT-TEMPLATE-OWN');
        $foreignTicket = $this->createTicket($ventaHorario, $otherVendedor, $plantilla, $tipoEnvio, 'TKT-TEMPLATE-FOREIGN');

        Sanctum::actingAs($vendedor);

        $this->getJson("/api/vendedor/tickets/{$ticket->id}/template-image")
            ->assertOk();

        $this->getJson("/api/vendedor/tickets/{$foreignTicket->id}/template-image")
            ->assertForbidden()
            ->assertJsonPath('message', 'El ticket no pertenece al vendedor autenticado.');
    }

    public function test_vendedor_can_download_generated_ticket_image_for_own_ticket(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $otherVendedor = $this->createUser('vendedor', 'other-image@example.test');
        $plantilla = $this->createTicketPlantilla();
        $tipoEnvio = $this->createTipoEnvio(TipoEnvio::IMPRESO);
        $ventaHorario = $this->createVentaHorario($this->createHorarioContext());
        Storage::put('tickets/final/TKT-IMAGE-OWN.png', 'fake png');
        $ticket = $this->createTicket(
            $ventaHorario,
            $vendedor,
            $plantilla,
            $tipoEnvio,
            'TKT-IMAGE-OWN',
            ticketImagePath: 'tickets/final/TKT-IMAGE-OWN.png',
        );
        $foreignTicket = $this->createTicket(
            $ventaHorario,
            $otherVendedor,
            $plantilla,
            $tipoEnvio,
            'TKT-IMAGE-FOREIGN',
            ticketImagePath: 'tickets/final/TKT-IMAGE-FOREIGN.png',
        );

        Sanctum::actingAs($vendedor);

        $this->getJson("/api/vendedor/tickets/{$ticket->id}/image")
            ->assertOk();

        $this->getJson("/api/vendedor/tickets/{$foreignTicket->id}/image")
            ->assertForbidden()
            ->assertJsonPath('message', 'El ticket no pertenece al vendedor autenticado.');
    }

    public function test_vendedor_ticket_image_endpoint_requires_generated_image(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $plantilla = $this->createTicketPlantilla();
        $tipoEnvio = $this->createTipoEnvio(TipoEnvio::IMPRESO);
        $ventaHorario = $this->createVentaHorario($this->createHorarioContext());
        $ticket = $this->createTicket($ventaHorario, $vendedor, $plantilla, $tipoEnvio, 'TKT-NO-IMAGE');

        Sanctum::actingAs($vendedor);

        $this->getJson("/api/vendedor/tickets/{$ticket->id}/image")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El ticket no tiene imagen generada.');
    }

    public function test_sale_is_rejected_without_partial_tickets_when_quantity_exceeds_capacity_and_overbooking_is_disabled(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $this->createTicketPlantilla();
        $tipoEnvio = $this->createTipoEnvio(TipoEnvio::IMPRESO);
        $ventaHorario = $this->createVentaHorario(
            horario: $this->createHorarioContext(capacidad: 2),
            totalVendidos: 1,
        );

        Sanctum::actingAs($vendedor);

        $this->postJson('/api/vendedor/tickets', [
            'venta_horario_id' => $ventaHorario->id,
            'cantidad' => 2,
            'tipo_envio_id' => $tipoEnvio->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La cantidad solicitada supera los cupos disponibles y la sobreventa no esta permitida.');

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseHas('ventas_horarios', [
            'id' => $ventaHorario->id,
            'total_tickets_vendidos' => 1,
            'venta_cerrada' => false,
        ]);
    }

    public function test_sale_is_rejected_when_schedule_operator_is_inactive(): void
    {
        $this->estado(Estado::DESACTIVADO_ID, 'Desactivado');
        $vendedor = $this->createUser('vendedor', 'inactive-operator-seller@example.test');
        $this->createTicketPlantilla();
        $tipoEnvio = $this->createTipoEnvio(TipoEnvio::IMPRESO);
        $horario = $this->createHorarioContext();
        $ventaHorario = $this->createVentaHorario($horario);
        $horario->operador->forceFill([
            'estado_id' => Estado::DESACTIVADO_ID,
            'motivo_desactivacion' => 'Operacion suspendida',
        ])->save();

        Sanctum::actingAs($vendedor);

        $this->postJson('/api/vendedor/tickets', [
            'venta_horario_id' => $ventaHorario->id,
            'cantidad' => 1,
            'tipo_envio_id' => $tipoEnvio->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El operador del horario esta desactivado.');

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_sale_closes_when_capacity_is_already_reached_and_overbooking_is_disabled(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $this->createTicketPlantilla();
        $tipoEnvio = $this->createTipoEnvio(TipoEnvio::IMPRESO);
        $ventaHorario = $this->createVentaHorario(
            horario: $this->createHorarioContext(capacidad: 2),
            totalVendidos: 2,
        );

        Sanctum::actingAs($vendedor);

        $this->postJson('/api/vendedor/tickets', [
            'venta_horario_id' => $ventaHorario->id,
            'cantidad' => 1,
            'tipo_envio_id' => $tipoEnvio->id,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'La capacidad del bus fue alcanzada y la venta fue cerrada.')
            ->assertJsonPath('venta_horario.venta_cerrada', true);

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseHas('ventas_horarios', [
            'id' => $ventaHorario->id,
            'venta_cerrada' => true,
            'cerrada_por' => $vendedor->id,
            'motivo_cierre' => 'Capacidad alcanzada.',
        ]);
    }

    public function test_successful_sale_that_fills_capacity_closes_sale_when_overbooking_is_disabled(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $this->createTicketPlantilla();
        $tipoEnvio = $this->createTipoEnvio(TipoEnvio::IMPRESO);
        $ventaHorario = $this->createVentaHorario(
            horario: $this->createHorarioContext(capacidad: 2),
            totalVendidos: 1,
        );

        Sanctum::actingAs($vendedor);

        $this->postJson('/api/vendedor/tickets', [
            'venta_horario_id' => $ventaHorario->id,
            'cantidad' => 1,
            'tipo_envio_id' => $tipoEnvio->id,
        ])
            ->assertCreated()
            ->assertJsonPath('resumen.venta_cerrada', true);

        $this->assertDatabaseHas('ventas_horarios', [
            'id' => $ventaHorario->id,
            'total_tickets_vendidos' => 2,
            'venta_cerrada' => true,
            'motivo_cierre' => 'Capacidad alcanzada.',
        ]);
    }

    public function test_sale_is_rejected_and_closed_when_departure_time_has_passed(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-30 09:00:00', 'America/El_Salvador'));

        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $this->createTicketPlantilla();
        $tipoEnvio = $this->createTipoEnvio(TipoEnvio::IMPRESO);
        $horario = $this->createHorarioContext();
        $horario->forceFill([
            'dia_id' => $this->createDia(2, 'Martes')->id,
        ])->save();
        $ventaHorario = $this->createVentaHorario($horario);

        Sanctum::actingAs($vendedor);

        $this->postJson('/api/vendedor/tickets', [
            'venta_horario_id' => $ventaHorario->id,
            'cantidad' => 1,
            'tipo_envio_id' => $tipoEnvio->id,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'La hora de salida de este horario ya paso y la venta fue cerrada.');

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseHas('ventas_horarios', [
            'id' => $ventaHorario->id,
            'venta_cerrada' => true,
            'cerrada_por' => $vendedor->id,
            'motivo_cierre' => 'Hora de salida alcanzada.',
        ]);
    }

    public function test_ticket_store_validation_rejects_invalid_and_forbidden_fields(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $digital = $this->createTipoEnvio(TipoEnvio::DIGITAL);
        $inactiveTipoEnvio = $this->createTipoEnvio(TipoEnvio::IMPRESO, Estado::DESACTIVADO_ID, 'Desactivado');

        Sanctum::actingAs($vendedor);

        $this->postJson('/api/vendedor/tickets', [
            'venta_horario_id' => 1,
            'cantidad' => 0,
            'tipo_envio_id' => $digital->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cantidad', 'correo_destino']);

        $this->postJson('/api/vendedor/tickets', [
            'venta_horario_id' => 1,
            'cantidad' => 1,
            'tipo_entrega' => 'impreso',
            'vendedor_id' => $vendedor->id,
            'estado_id' => Estado::EMITIDO_ID,
            'es_sobreventa' => false,
            'codigo_ticket' => 'MANUAL',
            'ticket_plantilla_id' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tipo_envio_id',
                'tipo_entrega',
                'vendedor_id',
                'estado_id',
                'es_sobreventa',
                'codigo_ticket',
                'ticket_plantilla_id',
            ]);

        $this->postJson('/api/vendedor/tickets', [
            'venta_horario_id' => 1,
            'cantidad' => 1,
            'tipo_envio_id' => $inactiveTipoEnvio->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo_envio_id']);
    }

    public function test_ticket_store_returns_clear_business_errors(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $tipoEnvio = $this->createTipoEnvio(TipoEnvio::IMPRESO);
        $this->createTicketPlantilla();
        $inactiveVenta = $this->createVentaHorario($this->createHorarioContext(), estadoId: Estado::DESACTIVADO_ID, estadoName: 'Desactivado');
        $closedVenta = $this->createVentaHorario($this->createHorarioContext(), cerrada: true);

        Sanctum::actingAs($vendedor);

        $this->postJson('/api/vendedor/tickets', [
            'venta_horario_id' => 999999,
            'cantidad' => 1,
            'tipo_envio_id' => $tipoEnvio->id,
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'La venta de horario solicitada no existe.');

        $this->postJson('/api/vendedor/tickets', [
            'venta_horario_id' => $inactiveVenta->id,
            'cantidad' => 1,
            'tipo_envio_id' => $tipoEnvio->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La venta de horario no esta activa.');

        $this->postJson('/api/vendedor/tickets', [
            'venta_horario_id' => $closedVenta->id,
            'cantidad' => 1,
            'tipo_envio_id' => $tipoEnvio->id,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'La venta de horario ya esta cerrada.');
    }

    public function test_ticket_store_requires_issued_status_and_active_default_template(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $tipoEnvio = $this->createTipoEnvio(TipoEnvio::IMPRESO);
        $ventaHorario = $this->createVentaHorario($this->createHorarioContext());

        Estado::query()->where('id', Estado::EMITIDO_ID)->delete();

        Sanctum::actingAs($vendedor);

        $this->postJson('/api/vendedor/tickets', [
            'venta_horario_id' => $ventaHorario->id,
            'cantidad' => 1,
            'tipo_envio_id' => $tipoEnvio->id,
        ])
            ->assertStatus(500)
            ->assertJsonPath('message', 'No se encontro el estado requerido: emitido.');

        $this->estado(Estado::EMITIDO_ID, 'Emitido');

        $this->postJson('/api/vendedor/tickets', [
            'venta_horario_id' => $ventaHorario->id,
            'cantidad' => 1,
            'tipo_envio_id' => $tipoEnvio->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No existe una plantilla de ticket predeterminada activa.');
    }

    public function test_ticket_endpoints_are_protected_for_seller_role_and_no_show_endpoint_is_registered(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $vendedor = $this->createUser('vendedor', 'seller@example.test');

        $this->getJson('/api/vendedor/tickets')
            ->assertUnauthorized();
        $this->getJson('/api/vendedor/tipo-envios')
            ->assertUnauthorized();

        Sanctum::actingAs($admin);

        $this->getJson('/api/vendedor/tickets')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permisos para acceder a este recurso.');
        $this->getJson('/api/vendedor/tipo-envios')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permisos para acceder a este recurso.');

        Sanctum::actingAs($vendedor);

        $this->getJson('/api/vendedor/tickets/1')
            ->assertNotFound();
    }

    private function createTicket(
        VentaHorario $ventaHorario,
        User $vendedor,
        TicketPlantilla $plantilla,
        TipoEnvio $tipoEnvio,
        string $codigo,
        ?string $ticketImagePath = null,
        ?ProcesamientoEstado $procesamientoEstado = null,
        ?string $processingError = null,
    ): Ticket {
        return Ticket::query()->create([
            'venta_horario_id' => $ventaHorario->id,
            'codigo_ticket' => $codigo,
            'vendedor_id' => $vendedor->id,
            'correo_destino' => null,
            'telefono_destino' => null,
            'numero_asiento' => null,
            'es_sobreventa' => false,
            'tipo_envio_id' => $tipoEnvio->id,
            'estado_id' => Estado::EMITIDO_ID,
            'qr_path' => null,
            'ticket_plantilla_id' => $plantilla->id,
            'ticket_image_path' => $ticketImagePath,
            'procesamiento_estado_id' => $procesamientoEstado?->id,
            'processing_error' => $processingError,
            'processed_at' => null,
            'processing_event_path' => $procesamientoEstado ? "ticket-events/pending/{$codigo}.json" : null,
        ]);
    }

    private function createTicketPlantilla(): TicketPlantilla
    {
        $image = imagecreatetruecolor(1000, 500);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        Storage::put(
            'ticket-plantillas/default.png',
            $png,
        );

        return TicketPlantilla::query()->create([
            'nombre' => 'Plantilla Predeterminada',
            'image_path' => 'ticket-plantillas/default.png',
            'qr_location' => ['x' => 700, 'y' => 160, 'width' => 120, 'height' => 120],
            'precio_location' => ['x' => 100, 'y' => 340, 'width' => 160, 'height' => 40],
            'fecha_hora_location' => ['x' => 380, 'y' => 380, 'width' => 220, 'height' => 40],
            'asiento_location' => ['x' => 390, 'y' => 270, 'width' => 160, 'height' => 40],
            'codigo_ticket_location' => ['x' => 720, 'y' => 60, 'width' => 220, 'height' => 60],
            'ruta_location' => ['x' => 320, 'y' => 150, 'width' => 340, 'height' => 70],
            'salida_location' => ['x' => 400, 'y' => 330, 'width' => 160, 'height' => 40],
            'operador_location' => ['x' => 420, 'y' => 70, 'width' => 220, 'height' => 40],
            'estado_id' => Estado::ACTIVO_ID,
            'es_predeterminada' => true,
        ]);
    }

    private function createHorarioContext(int $capacidad = 45, bool $sobreventaPermitida = false): Horario
    {
        $empresario = $this->createUser('empresario', 'empresario'.uniqid().'@example.test');
        $operador = $this->createOperador($empresario);
        $ruta = $this->createRuta('302'.uniqid(), 'Usulutan - San Salvador');
        $this->createOperadorRuta($operador, $ruta);
        $bus = $this->createBus($operador, $ruta, $this->createTipoBus('bus'), 'AB'.substr(uniqid(), -5), $capacidad);
        $dia = $this->createDia(1, 'Lunes');

        return Horario::query()->create([
            'ruta_id' => $ruta->id,
            'operador_id' => $operador->id,
            'bus_id' => $bus->id,
            'dia_id' => $dia->id,
            'hora_salida' => '08:00',
            'sobreventa_permitida' => $sobreventaPermitida,
            'estado_id' => Estado::ACTIVO_ID,
        ]);
    }

    private function createVentaHorario(
        Horario $horario,
        int $totalVendidos = 0,
        int $totalSobreventa = 0,
        bool $cerrada = false,
        int $estadoId = Estado::ACTIVO_ID,
        string $estadoName = 'Activo',
    ): VentaHorario {
        $estado = $this->estado($estadoId, $estadoName);

        return VentaHorario::query()->create([
            'horario_id' => $horario->id,
            'fecha_operacion' => '2026-06-30',
            'venta_cerrada' => $cerrada,
            'cerrada_por' => null,
            'fecha_cierre' => null,
            'motivo_cierre' => null,
            'total_tickets_vendidos' => $totalVendidos,
            'total_tickets_sobreventa' => $totalSobreventa,
            'estado_id' => $estado->id,
        ]);
    }

    private function createUser(
        string $roleName,
        string $email,
        bool $mustChangePassword = false,
    ): User {
        $role = Role::query()->firstOrCreate(['nombre' => $roleName]);
        $estado = $this->estado(Estado::ACTIVO_ID, 'Activo');
        $this->estado(Estado::EMITIDO_ID, 'Emitido');

        return User::query()->create([
            'role_id' => $role->id,
            'estado_id' => $estado->id,
            'name' => 'Usuario '.str_replace('@example.test', '', $email),
            'email' => $email,
            'password' => Hash::make('Temporal123'),
            'must_change_password' => $mustChangePassword,
        ]);
    }

    private function createOperador(User $user): Operador
    {
        $tipoPersona = TipoOperador::query()->firstOrCreate(['nombre' => 'persona']);
        $estado = $this->estado(Estado::ACTIVO_ID, 'Activo');

        return Operador::query()->create([
            'user_id' => $user->id,
            'tipo_operador_id' => $tipoPersona->id,
            'nombre_comercial' => 'Operador '.$user->id,
            'telefono' => '2222-3333',
            'dui' => sprintf('%08d-%d', $user->id, $user->id % 10),
            'correo_administrativo' => 'operador'.$user->id.'@example.test',
            'estado_id' => $estado->id,
        ]);
    }

    private function createRuta(string $ruta, string $denominacion): Ruta
    {
        $estado = $this->estado(Estado::ACTIVO_ID, 'Activo');

        return Ruta::query()->create([
            'ruta' => $ruta,
            'denominacion' => $denominacion,
            'tarifa' => 1.50,
            'estado_id' => $estado->id,
        ]);
    }

    private function createOperadorRuta(Operador $operador, Ruta $ruta): OperadorRuta
    {
        return OperadorRuta::query()->create([
            'operador_id' => $operador->id,
            'ruta_id' => $ruta->id,
            'estado_id' => Estado::ACTIVO_ID,
        ]);
    }

    private function createTipoBus(string $nombre): TipoBus
    {
        return TipoBus::query()->firstOrCreate(['nombre' => $nombre]);
    }

    private function createTipoEnvio(
        string $nombre,
        int $estadoId = Estado::ACTIVO_ID,
        string $estadoName = 'Activo',
    ): TipoEnvio {
        $estado = $this->estado($estadoId, $estadoName);

        return TipoEnvio::query()->updateOrCreate(
            ['nombre' => $nombre],
            [
                'descripcion' => ucfirst($nombre),
                'estado_id' => $estado->id,
            ],
        );
    }

    private function createProcesamientoEstado(
        string $nombre,
        int $estadoId = Estado::ACTIVO_ID,
        string $estadoName = 'Activo',
    ): ProcesamientoEstado {
        $estado = $this->estado($estadoId, $estadoName);

        return ProcesamientoEstado::query()->updateOrCreate(
            ['nombre' => $nombre],
            [
                'descripcion' => ucfirst($nombre),
                'estado_id' => $estado->id,
            ],
        );
    }

    private function createBus(Operador $operador, Ruta $ruta, TipoBus $tipoBus, string $placa, int $capacidad): Bus
    {
        return Bus::query()->create([
            'operador_id' => $operador->id,
            'ruta_id' => $ruta->id,
            'placa' => $placa,
            'marca' => 'Mercedes',
            'nombre_unidad' => 'Unidad '.$placa,
            'capacidad' => $capacidad,
            'tipo_bus_id' => $tipoBus->id,
            'estado_id' => Estado::ACTIVO_ID,
        ]);
    }

    private function createDia(int $orden, string $nombre): Dia
    {
        return Dia::query()->firstOrCreate(['orden' => $orden], ['nombre' => $nombre]);
    }

    private function estado(int $id, string $nombre): Estado
    {
        return Estado::query()->firstOrCreate(['id' => $id], ['nombre' => $nombre]);
    }
}
