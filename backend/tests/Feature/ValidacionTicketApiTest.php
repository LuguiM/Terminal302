<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Dia;
use App\Models\Estado;
use App\Models\Horario;
use App\Models\Operador;
use App\Models\OperadorEmpleado;
use App\Models\OperadorRuta;
use App\Models\Role;
use App\Models\Ruta;
use App\Models\Ticket;
use App\Models\TicketPlantilla;
use App\Models\TipoBus;
use App\Models\TipoEnvio;
use App\Models\TipoOperador;
use App\Models\User;
use App\Models\Validacion;
use App\Models\VentaHorario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ValidacionTicketApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_validador_can_validate_issued_ticket_from_own_operator(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-30 09:15:00', 'America/El_Salvador'));

        $validador = $this->createUser('validador', 'validador@example.test');
        $operador = $this->createOperador($validador);
        $ticket = $this->createTicketForOperador($operador, 'TKT-VALID-001');

        Sanctum::actingAs($validador);

        $this->postJson('/api/validador/tickets/validar', [
            'codigo_ticket' => 'TKT-VALID-001',
            'observacion' => 'Abordaje confirmado',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Ticket validado correctamente.')
            ->assertJsonMissingPath('ticket')
            ->assertJsonMissingPath('validacion');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'estado_id' => Estado::VALIDADO_ID,
        ]);
        $this->assertDatabaseHas('validaciones', [
            'ticket_id' => $ticket->id,
            'validador_id' => $validador->id,
            'resultado' => 'valido',
            'observacion' => 'Abordaje confirmado',
        ]);
    }

    public function test_employee_validator_can_validate_ticket_from_assigned_operator(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-30 09:15:00', 'America/El_Salvador'));

        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $validador = $this->createUser('validador', 'empleado-validador@example.test');
        $this->createOperadorEmpleado($operador, $validador);
        $ticket = $this->createTicketForOperador($operador, 'TKT-EMPLOYEE-001');

        Sanctum::actingAs($validador);

        $this->postJson('/api/validador/tickets/validar', [
            'codigo_ticket' => 'TKT-EMPLOYEE-001',
            'observacion' => 'Validado en puerta',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Ticket validado correctamente.')
            ->assertJsonMissingPath('ticket')
            ->assertJsonMissingPath('validacion');
    }

    public function test_validator_is_temporarily_blocked_when_assigned_operator_is_inactive(): void
    {
        $this->estado(Estado::DESACTIVADO_ID, 'Desactivado');
        $empresario = $this->createUser('empresario', 'inactive-owner@example.test');
        $operador = $this->createOperador($empresario);
        $operador->forceFill([
            'estado_id' => Estado::DESACTIVADO_ID,
            'motivo_desactivacion' => 'Permiso de operacion vencido',
        ])->save();
        $validador = $this->createUser('validador', 'blocked-validator@example.test');
        $empleado = $this->createOperadorEmpleado($operador, $validador);

        $loginResponse = $this->postJson('/api/login', [
            'email' => $validador->email,
            'password' => 'Temporal123',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('operator_access.blocked', true)
            ->assertJsonPath('operator_access.reason', 'Permiso de operacion vencido');

        Sanctum::actingAs($validador);

        $this->postJson('/api/validador/tickets/validar', [
            'codigo_ticket' => 'TKT-BLOCKED',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'OPERATOR_DISABLED');

        $this->assertSame(Estado::ACTIVO_ID, $validador->fresh()->estado_id);
        $this->assertSame(Estado::ACTIVO_ID, $empleado->fresh()->estado_id);

        $operador->forceFill([
            'estado_id' => Estado::ACTIVO_ID,
            'motivo_desactivacion' => null,
        ])->save();
        $validador->unsetRelation('operadorEmpleado');

        $this->getJson('/api/validador/validaciones')
            ->assertOk();
    }

    public function test_validador_cannot_validate_same_ticket_twice(): void
    {
        $validador = $this->createUser('validador', 'validador@example.test');
        $operador = $this->createOperador($validador);
        $ticket = $this->createTicketForOperador($operador, 'TKT-DOUBLE-001', Estado::VALIDADO_ID);
        $this->createValidacion($ticket, $validador);

        Sanctum::actingAs($validador);

        $this->postJson('/api/validador/tickets/validar', [
            'codigo_ticket' => 'TKT-DOUBLE-001',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'El ticket ya fue validado.');
    }

    public function test_validador_cannot_validate_cancelled_ticket(): void
    {
        $validador = $this->createUser('validador', 'validador@example.test');
        $operador = $this->createOperador($validador);
        $this->createTicketForOperador($operador, 'TKT-CANCEL-001', Estado::CANCELADO_ID);

        Sanctum::actingAs($validador);

        $this->postJson('/api/validador/tickets/validar', [
            'codigo_ticket' => 'TKT-CANCEL-001',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'El ticket esta cancelado.');
    }

    public function test_validador_cannot_validate_ticket_from_other_operator(): void
    {
        $validador = $this->createUser('validador', 'validador@example.test');
        $this->createOperador($validador);
        $otherOwner = $this->createUser('empresario', 'other-owner@example.test');
        $otherOperador = $this->createOperador($otherOwner);
        $this->createTicketForOperador($otherOperador, 'TKT-OTHER-001');

        Sanctum::actingAs($validador);

        $this->postJson('/api/validador/tickets/validar', [
            'codigo_ticket' => 'TKT-OTHER-001',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'El ticket pertenece a otro operador.');
    }

    public function test_validation_returns_clear_errors_for_missing_ticket_or_validator_operator(): void
    {
        $validador = $this->createUser('validador', 'validador@example.test');

        Sanctum::actingAs($validador);

        $this->postJson('/api/validador/tickets/validar', [
            'codigo_ticket' => 'NO-EXISTE',
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'El validador autenticado no tiene operador asociado.');

        $this->createOperador($validador);

        $this->postJson('/api/validador/tickets/validar', [
            'codigo_ticket' => 'NO-EXISTE',
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'El codigo de ticket solicitado no existe.');
    }

    public function test_validation_returns_clear_errors_when_required_statuses_are_missing(): void
    {
        $validador = $this->createUser('validador', 'validador@example.test');
        $this->createOperador($validador);

        Sanctum::actingAs($validador);

        Estado::query()->where('id', Estado::EMITIDO_ID)->delete();
        $this->postJson('/api/validador/tickets/validar', ['codigo_ticket' => 'ANY'])
            ->assertStatus(500)
            ->assertJsonPath('message', 'No se encontro el estado requerido: emitido.');

        $this->estado(Estado::EMITIDO_ID, 'Emitido');
        Estado::query()->where('id', Estado::VALIDADO_ID)->delete();
        $this->postJson('/api/validador/tickets/validar', ['codigo_ticket' => 'ANY'])
            ->assertStatus(500)
            ->assertJsonPath('message', 'No se encontro el estado requerido: validado.');

        $this->estado(Estado::VALIDADO_ID, 'Validado');
        Estado::query()->where('id', Estado::CANCELADO_ID)->delete();
        $this->postJson('/api/validador/tickets/validar', ['codigo_ticket' => 'ANY'])
            ->assertStatus(500)
            ->assertJsonPath('message', 'No se encontro el estado requerido: cancelado.');
    }

    public function test_validador_can_list_own_validations_paginated_with_filters(): void
    {
        $validador = $this->createUser('validador', 'validador@example.test');
        $otherValidador = $this->createUser('validador', 'other-validador@example.test');
        $operador = $this->createOperador($validador);
        $otherOperador = $this->createOperador($otherValidador);
        $ticket = $this->createTicketForOperador($operador, 'TKT-LIST-001', Estado::VALIDADO_ID);
        $otherTicket = $this->createTicketForOperador($otherOperador, 'TKT-LIST-002', Estado::VALIDADO_ID);
        $validacion = $this->createValidacion($ticket, $validador, '2026-06-30 08:00:00');
        $this->createValidacion($otherTicket, $otherValidador, '2026-06-30 08:00:00');

        Sanctum::actingAs($validador);

        $this->getJson("/api/validador/validaciones?ticket_id={$ticket->id}&fecha_validacion=2026-06-30")
            ->assertOk()
            ->assertJsonStructure([
                'validaciones' => [
                    [
                        'ticket',
                        'validador',
                        'fecha_validacion',
                        'resultado',
                        'observacion',
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
            ->assertJsonPath('validaciones.0.ticket.codigo_ticket', 'TKT-LIST-001')
            ->assertJsonPath('validaciones.0.validador.name', $validador->name)
            ->assertJsonMissingPath('validaciones.0.validador.id');
    }

    public function test_validation_request_rejects_forbidden_fields(): void
    {
        $validador = $this->createUser('validador', 'validador@example.test');
        $this->createOperador($validador);

        Sanctum::actingAs($validador);

        $this->postJson('/api/validador/tickets/validar', [
            'codigo_ticket' => 'TKT-FORBIDDEN',
            'ticket_id' => 1,
            'validador_id' => $validador->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket_id', 'validador_id']);
    }

    public function test_validador_endpoints_are_protected_and_show_endpoint_is_not_registered(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $validador = $this->createUser('validador', 'validador@example.test');

        $this->postJson('/api/validador/tickets/validar', [
            'codigo_ticket' => 'ANY',
        ])
            ->assertUnauthorized();

        Sanctum::actingAs($vendedor);

        $this->getJson('/api/validador/validaciones')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permisos para acceder a este recurso.');

        Sanctum::actingAs($validador);

        $this->getJson('/api/validador/validaciones/1')
            ->assertNotFound();
    }

    private function createValidacion(Ticket $ticket, User $validador, string $fecha = '2026-06-30 09:00:00'): Validacion
    {
        return Validacion::query()->create([
            'ticket_id' => $ticket->id,
            'validador_id' => $validador->id,
            'fecha_validacion' => CarbonImmutable::parse($fecha, 'America/El_Salvador'),
            'resultado' => Validacion::RESULTADO_VALIDO,
            'observacion' => null,
        ]);
    }

    private function createTicketForOperador(
        Operador $operador,
        string $codigo,
        int $estadoId = Estado::EMITIDO_ID,
    ): Ticket {
        $ruta = $this->createRuta('302'.uniqid(), 'Usulutan - San Salvador');
        $this->createOperadorRuta($operador, $ruta);
        $bus = $this->createBus($operador, $ruta, $this->createTipoBus('bus'), 'AB'.substr(uniqid(), -5));
        $horario = $this->createHorario($ruta, $operador, $bus);
        $ventaHorario = $this->createVentaHorario($horario);
        $vendedor = $this->createUser('vendedor', 'seller'.uniqid().'@example.test');

        return Ticket::query()->create([
            'venta_horario_id' => $ventaHorario->id,
            'codigo_ticket' => $codigo,
            'vendedor_id' => $vendedor->id,
            'correo_destino' => null,
            'telefono_destino' => null,
            'numero_asiento' => null,
            'es_sobreventa' => false,
            'tipo_envio_id' => $this->createTipoEnvio()->id,
            'estado_id' => $estadoId,
            'qr_path' => null,
            'ticket_plantilla_id' => $this->createTicketPlantilla()->id,
            'ticket_image_path' => null,
        ]);
    }

    private function createUser(string $roleName, string $email): User
    {
        $role = Role::query()->firstOrCreate(['nombre' => $roleName]);
        $estado = $this->estado(Estado::ACTIVO_ID, 'Activo');
        $this->estado(Estado::EMITIDO_ID, 'Emitido');
        $this->estado(Estado::VALIDADO_ID, 'Validado');
        $this->estado(Estado::CANCELADO_ID, 'Cancelado');

        return User::query()->create([
            'role_id' => $role->id,
            'estado_id' => $estado->id,
            'name' => 'Usuario '.str_replace('@example.test', '', $email),
            'email' => $email,
            'password' => Hash::make('Temporal123'),
            'must_change_password' => false,
        ]);
    }

    private function createOperador(User $user): Operador
    {
        $tipoPersona = TipoOperador::query()->firstOrCreate(['nombre' => 'persona']);

        return Operador::query()->create([
            'user_id' => $user->id,
            'tipo_operador_id' => $tipoPersona->id,
            'nombre_comercial' => 'Operador '.$user->id,
            'telefono' => '2222-3333',
            'dui' => sprintf('%08d-%d', $user->id, $user->id % 10),
            'correo_administrativo' => 'operador'.$user->id.'@example.test',
            'estado_id' => Estado::ACTIVO_ID,
        ]);
    }

    private function createRuta(string $ruta, string $denominacion): Ruta
    {
        return Ruta::query()->create([
            'ruta' => $ruta,
            'denominacion' => $denominacion,
            'tarifa' => 1.50,
            'estado_id' => Estado::ACTIVO_ID,
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

    private function createOperadorEmpleado(Operador $operador, User $user): OperadorEmpleado
    {
        return OperadorEmpleado::query()->create([
            'operador_id' => $operador->id,
            'user_id' => $user->id,
            'estado_id' => Estado::ACTIVO_ID,
        ]);
    }

    private function createBus(Operador $operador, Ruta $ruta, TipoBus $tipoBus, string $placa): Bus
    {
        return Bus::query()->create([
            'operador_id' => $operador->id,
            'ruta_id' => $ruta->id,
            'placa' => $placa,
            'marca' => 'Mercedes',
            'nombre_unidad' => 'Unidad '.$placa,
            'capacidad' => 45,
            'tipo_bus_id' => $tipoBus->id,
            'estado_id' => Estado::ACTIVO_ID,
        ]);
    }

    private function createHorario(Ruta $ruta, Operador $operador, Bus $bus): Horario
    {
        return Horario::query()->create([
            'ruta_id' => $ruta->id,
            'operador_id' => $operador->id,
            'bus_id' => $bus->id,
            'dia_id' => $this->createDia(1, 'Lunes')->id,
            'hora_salida' => '08:00',
            'sobreventa_permitida' => false,
            'estado_id' => Estado::ACTIVO_ID,
        ]);
    }

    private function createVentaHorario(Horario $horario): VentaHorario
    {
        return VentaHorario::query()->create([
            'horario_id' => $horario->id,
            'fecha_operacion' => '2026-06-30',
            'venta_cerrada' => false,
            'total_tickets_vendidos' => 1,
            'total_tickets_sobreventa' => 0,
            'estado_id' => Estado::ACTIVO_ID,
        ]);
    }

    private function createTicketPlantilla(): TicketPlantilla
    {
        return TicketPlantilla::query()->create([
            'nombre' => 'Plantilla',
            'image_path' => 'ticket-plantillas/default.png',
            'estado_id' => Estado::ACTIVO_ID,
            'es_predeterminada' => true,
        ]);
    }

    private function createTipoEnvio(): TipoEnvio
    {
        return TipoEnvio::query()->firstOrCreate(
            ['nombre' => TipoEnvio::IMPRESO],
            [
                'descripcion' => 'Entrega impresa',
                'estado_id' => Estado::ACTIVO_ID,
            ],
        );
    }

    private function createTipoBus(string $nombre): TipoBus
    {
        return TipoBus::query()->firstOrCreate(['nombre' => $nombre]);
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
