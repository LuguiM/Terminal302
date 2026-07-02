<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Dia;
use App\Models\Estado;
use App\Models\Horario;
use App\Models\Operador;
use App\Models\OperadorRuta;
use App\Models\Role;
use App\Models\Ruta;
use App\Models\Ticket;
use App\Models\TicketPlantilla;
use App\Models\TipoBus;
use App\Models\TipoEnvio;
use App\Models\TipoOperador;
use App\Models\User;
use App\Models\VentaHorario;
use App\Services\PublicTicketLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_routes_list_only_active_routes_with_active_schedules_paginated_and_searchable(): void
    {
        $context = $this->createHorarioContext('302', 'Usulutan - San Salvador');
        $inactiveRuta = $this->createRuta('404', 'Ruta Inactiva', Estado::DESACTIVADO_ID);
        $this->createHorarioContext('500', 'Sin horarios activos', horarioEstadoId: Estado::DESACTIVADO_ID);

        $this->getJson('/api/public/rutas?search=Usulutan')
            ->assertOk()
            ->assertJsonStructure([
                'rutas' => [
                    [
                        'id',
                        'ruta',
                        'denominacion',
                        'tarifa',
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
            ->assertJsonPath('rutas.0.id', $context['ruta']->id)
            ->assertJsonMissing(['id' => $inactiveRuta->id])
            ->assertJsonMissing(['denominacion' => 'Sin horarios activos']);
    }

    public function test_public_route_schedules_are_active_filterable_and_limited(): void
    {
        $context = $this->createHorarioContext('302', 'Usulutan - San Salvador', diaOrden: 1, diaNombre: 'Lunes');
        $martes = $this->createDia(2, 'Martes');
        Horario::query()->create([
            'ruta_id' => $context['ruta']->id,
            'operador_id' => $context['operador']->id,
            'bus_id' => $context['bus']->id,
            'dia_id' => $martes->id,
            'hora_salida' => '10:00',
            'sobreventa_permitida' => false,
            'estado_id' => Estado::ACTIVO_ID,
        ]);
        Horario::query()->create([
            'ruta_id' => $context['ruta']->id,
            'operador_id' => $context['operador']->id,
            'bus_id' => $context['bus']->id,
            'dia_id' => $martes->id,
            'hora_salida' => '11:00',
            'sobreventa_permitida' => false,
            'estado_id' => Estado::DESACTIVADO_ID,
        ]);

        $this->getJson("/api/public/rutas/{$context['ruta']->id}/horarios?dia_id={$martes->id}")
            ->assertOk()
            ->assertJsonPath('ruta.id', $context['ruta']->id)
            ->assertJsonPath('horarios.0.dia.nombre', 'Martes')
            ->assertJsonPath('horarios.0.hora_salida', '10:00')
            ->assertJsonPath('horarios.0.operador.nombre_comercial', $context['operador']->nombre_comercial)
            ->assertJsonPath('horarios.0.bus.placa', $context['bus']->placa)
            ->assertJsonPath('horarios.0.tarifa', '1.50')
            ->assertJsonMissing(['hora_salida' => '11:00']);
    }

    public function test_public_route_schedules_return_clear_errors(): void
    {
        $inactiveRuta = $this->createRuta('999', 'Ruta Inactiva', Estado::DESACTIVADO_ID);
        $routeWithoutSchedules = $this->createRuta('888', 'Sin horarios');

        $this->getJson('/api/public/rutas/999999/horarios')
            ->assertNotFound()
            ->assertJsonPath('message', 'La ruta solicitada no existe.');

        $this->getJson("/api/public/rutas/{$inactiveRuta->id}/horarios")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La ruta solicitada no esta activa.');

        $this->getJson("/api/public/rutas/{$routeWithoutSchedules->id}/horarios")
            ->assertNotFound()
            ->assertJsonPath('message', 'No existen horarios activos para esta ruta.');
    }

    public function test_public_ticket_lookup_returns_limited_data_without_auth_or_validation_side_effects(): void
    {
        $context = $this->createHorarioContext('302', 'Usulutan - San Salvador');
        $ventaHorario = $this->createVentaHorario($context['horario']);
        $ticket = $this->createTicket($ventaHorario, 'TKT-PUBLIC-001');

        $this->getJson('/api/public/tickets/TKT-PUBLIC-001')
            ->assertOk()
            ->assertJsonPath('ticket.codigo_ticket', 'TKT-PUBLIC-001')
            ->assertJsonPath('ticket.estado.nombre', 'Emitido')
            ->assertJsonPath('ticket.ruta', '302')
            ->assertJsonPath('ticket.denominacion', 'Usulutan - San Salvador')
            ->assertJsonPath('ticket.operador.nombre_comercial', $context['operador']->nombre_comercial)
            ->assertJsonPath('ticket.dia.nombre', $context['dia']->nombre)
            ->assertJsonPath('ticket.hora_salida', '08:00')
            ->assertJsonPath('ticket.fecha_operacion', '2026-06-30')
            ->assertJsonPath('ticket.es_sobreventa', false)
            ->assertJsonPath('ticket.tipo_envio.nombre', TipoEnvio::IMPRESO)
            ->assertJsonMissingPath('ticket.correo_destino')
            ->assertJsonMissingPath('ticket.telefono_destino')
            ->assertJsonMissingPath('ticket.vendedor')
            ->assertJsonMissingPath('ticket.processing_error')
            ->assertJsonMissingPath('ticket.processing_event_path');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'estado_id' => Estado::EMITIDO_ID,
        ]);
        $this->assertDatabaseCount('validaciones', 0);
    }

    public function test_public_ticket_lookup_returns_clear_errors(): void
    {
        $this->getJson('/api/public/tickets/NO-EXISTE')
            ->assertNotFound()
            ->assertJsonPath('message', 'El ticket solicitado no existe.');

        $this->app->instance(PublicTicketLookupService::class, new class extends PublicTicketLookupService
        {
            public function findByCode(string $codigoTicket): ?Ticket
            {
                return new Ticket(['codigo_ticket' => $codigoTicket]);
            }

            public function hasRequiredRelations(Ticket $ticket): bool
            {
                return false;
            }
        });

        $this->getJson('/api/public/tickets/TKT-BROKEN-001')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El ticket no tiene la informacion publica necesaria para ser consultado.');
    }

    private function createHorarioContext(
        string $rutaCode,
        string $denominacion,
        int $rutaEstadoId = Estado::ACTIVO_ID,
        int $horarioEstadoId = Estado::ACTIVO_ID,
        int $diaOrden = 1,
        string $diaNombre = 'Lunes',
    ): array {
        $owner = $this->createUser('empresario', 'owner'.uniqid().'@example.test');
        $operador = $this->createOperador($owner);
        $ruta = $this->createRuta($rutaCode, $denominacion, $rutaEstadoId);
        $this->createOperadorRuta($operador, $ruta);
        $bus = $this->createBus($operador, $ruta, 'AB'.substr(uniqid(), -5));
        $dia = $this->createDia($diaOrden, $diaNombre);
        $horario = Horario::query()->create([
            'ruta_id' => $ruta->id,
            'operador_id' => $operador->id,
            'bus_id' => $bus->id,
            'dia_id' => $dia->id,
            'hora_salida' => '08:00',
            'sobreventa_permitida' => false,
            'estado_id' => $horarioEstadoId,
        ]);

        return compact('ruta', 'operador', 'bus', 'dia', 'horario');
    }

    private function createUser(string $roleName, string $email): User
    {
        $role = Role::query()->firstOrCreate(['nombre' => $roleName]);
        $estado = $this->estado(Estado::ACTIVO_ID, 'Activo');
        $this->estado(Estado::DESACTIVADO_ID, 'Desactivado');
        $this->estado(Estado::EMITIDO_ID, 'Emitido');

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

    private function createRuta(string $ruta, string $denominacion, int $estadoId = Estado::ACTIVO_ID): Ruta
    {
        $this->estado($estadoId, $estadoId === Estado::ACTIVO_ID ? 'Activo' : 'Desactivado');

        return Ruta::query()->create([
            'ruta' => $ruta,
            'denominacion' => $denominacion,
            'tarifa' => 1.50,
            'estado_id' => $estadoId,
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

    private function createBus(Operador $operador, Ruta $ruta, string $placa): Bus
    {
        return Bus::query()->create([
            'operador_id' => $operador->id,
            'ruta_id' => $ruta->id,
            'placa' => $placa,
            'marca' => 'Mercedes',
            'nombre_unidad' => 'Unidad '.$placa,
            'capacidad' => 45,
            'tipo_bus_id' => TipoBus::query()->firstOrCreate(['nombre' => 'bus'])->id,
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

    private function createTicket(VentaHorario $ventaHorario, string $codigo): Ticket
    {
        return Ticket::query()->create([
            'venta_horario_id' => $ventaHorario->id,
            'codigo_ticket' => $codigo,
            'vendedor_id' => $this->createUser('vendedor', 'seller'.uniqid().'@example.test')->id,
            'correo_destino' => 'cliente@example.test',
            'telefono_destino' => '77777777',
            'numero_asiento' => null,
            'es_sobreventa' => false,
            'tipo_envio_id' => $this->createTipoEnvio()->id,
            'estado_id' => Estado::EMITIDO_ID,
            'qr_path' => null,
            'ticket_plantilla_id' => $this->createTicketPlantilla()->id,
            'ticket_image_path' => null,
            'processing_error' => 'No publico',
            'processing_event_path' => 'ticket-events/pending/private.json',
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

    private function createDia(int $orden, string $nombre): Dia
    {
        return Dia::query()->firstOrCreate(['orden' => $orden], ['nombre' => $nombre]);
    }

    private function estado(int $id, string $nombre): Estado
    {
        return Estado::query()->firstOrCreate(['id' => $id], ['nombre' => $nombre]);
    }
}
