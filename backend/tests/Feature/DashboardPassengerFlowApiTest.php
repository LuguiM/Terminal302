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
use App\Models\Validacion;
use App\Models\VentaHorario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardPassengerFlowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_passenger_flow_with_date_range_month_route_and_operator_filters(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $firstContext = $this->createOperationContext('302', 'Usulutan - San Salvador', 'AB12345', 10);
        $secondContext = $this->createOperationContext('304', 'San Miguel - San Salvador', 'CD12345', 20);

        $firstSale = $this->createVentaHorario($firstContext['horario'], '2026-07-01', 3, 1);
        $secondSale = $this->createVentaHorario($secondContext['horario'], '2026-07-02', 2, 0);
        $outsideSale = $this->createVentaHorario($firstContext['horario'], '2026-08-01', 4, 0);

        $this->createTickets($firstSale, 3, validated: 2, overSold: 1);
        $this->createTickets($secondSale, 2, validated: 1);
        $this->createTickets($outsideSale, 4, validated: 4);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard/flujo-pasajeros?mes=2026-07')
            ->assertOk()
            ->assertJsonPath('filtros.modo', 'mes')
            ->assertJsonPath('filtros.fecha_desde', '2026-07-01')
            ->assertJsonPath('filtros.fecha_hasta', '2026-07-31')
            ->assertJsonPath('resumen.tickets_vendidos', 5)
            ->assertJsonPath('resumen.tickets_validados', 3)
            ->assertJsonPath('resumen.porcentaje_validacion', 60)
            ->assertJsonPath('resumen.tickets_sobreventa', 1)
            ->assertJsonPath('resumen.salidas_operadas', 2)
            ->assertJsonPath('resumen.ocupacion_promedio', 20)
            ->assertJsonPath('series.por_dia.0.fecha', '2026-07-01')
            ->assertJsonPath('rankings.rutas.0.ruta', '302')
            ->assertJsonPath('rankings.operadores.0.operador_id', $firstContext['operador']->id);

        $this->getJson("/api/admin/dashboard/flujo-pasajeros?fecha=2026-07-01&ruta_id={$firstContext['ruta']->id}")
            ->assertOk()
            ->assertJsonPath('resumen.tickets_vendidos', 3)
            ->assertJsonPath('resumen.tickets_validados', 2)
            ->assertJsonPath('resumen.tickets_sobreventa', 1)
            ->assertJsonPath('resumen.salidas_operadas', 1)
            ->assertJsonPath('resumen.ocupacion_promedio', 30);

        $this->getJson("/api/admin/dashboard/flujo-pasajeros?fecha_desde=2026-07-01&fecha_hasta=2026-07-31&operador_id={$secondContext['operador']->id}")
            ->assertOk()
            ->assertJsonPath('resumen.tickets_vendidos', 2)
            ->assertJsonPath('rankings.rutas.0.ruta', '304');
    }

    public function test_operator_reads_only_own_passenger_flow_and_can_filter_by_bus(): void
    {
        $owner = $this->createUser('empresario', 'owner@example.test');
        $otherOwner = $this->createUser('empresario', 'other-owner@example.test');
        $ownContext = $this->createOperationContext('302', 'Usulutan - San Salvador', 'AB12345', 10, $owner);
        $otherContext = $this->createOperationContext('304', 'San Miguel - San Salvador', 'CD12345', 20, $otherOwner);

        $ownSale = $this->createVentaHorario($ownContext['horario'], '2026-07-01', 3, 1);
        $otherSale = $this->createVentaHorario($otherContext['horario'], '2026-07-01', 5, 0);

        $this->createTickets($ownSale, 3, validated: 2, overSold: 1);
        $this->createTickets($otherSale, 5, validated: 5);

        Sanctum::actingAs($owner);

        $this->getJson("/api/operador/dashboard/flujo-pasajeros?fecha=2026-07-01&bus_id={$ownContext['bus']->id}")
            ->assertOk()
            ->assertJsonPath('resumen.tickets_vendidos', 3)
            ->assertJsonPath('resumen.tickets_validados', 2)
            ->assertJsonPath('resumen.tickets_sobreventa', 1)
            ->assertJsonPath('rankings.buses.0.bus_id', $ownContext['bus']->id)
            ->assertJsonPath('rankings.operadores', []);

        $this->getJson("/api/operador/dashboard/flujo-pasajeros?fecha=2026-07-01&bus_id={$otherContext['bus']->id}")
            ->assertOk()
            ->assertJsonPath('resumen.tickets_vendidos', 0)
            ->assertJsonPath('rankings.buses', []);
    }

    public function test_dashboard_filter_validation_errors_are_clear(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard/flujo-pasajeros?fecha=2026-07-01&mes=2026-07')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha']);

        $this->getJson('/api/admin/dashboard/flujo-pasajeros?fecha_desde=2025-01-01&fecha_hasta=2026-07-31')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_hasta']);
    }

    public function test_dashboard_endpoints_are_protected_by_auth_and_role(): void
    {
        $seller = $this->createUser('vendedor', 'seller@example.test');
        $admin = $this->createUser('administrador', 'admin@example.test', mustChangePassword: true);

        $this->getJson('/api/admin/dashboard/flujo-pasajeros')
            ->assertUnauthorized();

        Sanctum::actingAs($seller);

        $this->getJson('/api/admin/dashboard/flujo-pasajeros')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permisos para acceder a este recurso.');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard/flujo-pasajeros')
            ->assertForbidden()
            ->assertJsonPath('message', 'Debe cambiar la contrasena inicial antes de continuar.');
    }

    /**
     * @return array{operador: Operador, ruta: Ruta, bus: Bus, horario: Horario}
     */
    private function createOperationContext(
        string $rutaCode,
        string $denominacion,
        string $placa,
        int $capacidad,
        ?User $owner = null,
    ): array {
        $owner ??= $this->createUser('empresario', 'owner'.uniqid().'@example.test');
        $operador = $this->createOperador($owner);
        $ruta = $this->createRuta($rutaCode, $denominacion);
        $this->createOperadorRuta($operador, $ruta);
        $bus = $this->createBus($operador, $ruta, $placa, $capacidad);
        $horario = $this->createHorario($ruta, $operador, $bus);

        return compact('operador', 'ruta', 'bus', 'horario');
    }

    private function createVentaHorario(
        Horario $horario,
        string $fechaOperacion,
        int $totalVendidos,
        int $totalSobreventa,
    ): VentaHorario {
        return VentaHorario::query()->create([
            'horario_id' => $horario->id,
            'fecha_operacion' => $fechaOperacion,
            'venta_cerrada' => false,
            'cerrada_por' => null,
            'fecha_cierre' => null,
            'motivo_cierre' => null,
            'total_tickets_vendidos' => $totalVendidos,
            'total_tickets_sobreventa' => $totalSobreventa,
            'estado_id' => Estado::ACTIVO_ID,
        ]);
    }

    private function createTickets(VentaHorario $ventaHorario, int $count, int $validated = 0, int $overSold = 0): void
    {
        $seller = $this->createUser('vendedor', 'seller'.uniqid().'@example.test');
        $tipoEnvio = $this->createTipoEnvio();
        $plantilla = $this->createTicketPlantilla();

        for ($index = 1; $index <= $count; $index++) {
            $ticket = Ticket::query()->create([
                'venta_horario_id' => $ventaHorario->id,
                'codigo_ticket' => 'TKT-'.uniqid(),
                'vendedor_id' => $seller->id,
                'correo_destino' => null,
                'telefono_destino' => null,
                'numero_asiento' => $index,
                'es_sobreventa' => $index > ($count - $overSold),
                'tipo_envio_id' => $tipoEnvio->id,
                'estado_id' => $index <= $validated ? Estado::VALIDADO_ID : Estado::EMITIDO_ID,
                'qr_path' => null,
                'ticket_plantilla_id' => $plantilla->id,
                'ticket_image_path' => null,
            ]);

            if ($index <= $validated) {
                Validacion::query()->create([
                    'ticket_id' => $ticket->id,
                    'validador_id' => $this->createUser('validador', 'validator'.uniqid().'@example.test')->id,
                    'fecha_validacion' => CarbonImmutable::parse($ventaHorario->fecha_operacion->toDateString().' 08:30:00', 'America/El_Salvador'),
                    'resultado' => Validacion::RESULTADO_VALIDO,
                    'observacion' => null,
                ]);
            }
        }
    }

    private function createUser(string $roleName, string $email, bool $mustChangePassword = false): User
    {
        $role = Role::query()->firstOrCreate(['nombre' => $roleName]);
        $this->estado(Estado::ACTIVO_ID, 'Activo');
        $this->estado(Estado::DESACTIVADO_ID, 'Desactivado');
        $this->estado(Estado::EMITIDO_ID, 'Emitido');
        $this->estado(Estado::VALIDADO_ID, 'Validado');
        $this->estado(Estado::CANCELADO_ID, 'Cancelado');

        return User::query()->create([
            'role_id' => $role->id,
            'estado_id' => Estado::ACTIVO_ID,
            'name' => 'Usuario '.str_replace('@example.test', '', $email),
            'email' => $email,
            'password' => Hash::make('Temporal123'),
            'must_change_password' => $mustChangePassword,
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

    private function createBus(Operador $operador, Ruta $ruta, string $placa, int $capacidad): Bus
    {
        $tipoBus = TipoBus::query()->firstOrCreate(['nombre' => 'bus']);

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

    private function createHorario(Ruta $ruta, Operador $operador, Bus $bus): Horario
    {
        return Horario::query()->create([
            'ruta_id' => $ruta->id,
            'operador_id' => $operador->id,
            'bus_id' => $bus->id,
            'dia_id' => Dia::query()->firstOrCreate(['orden' => 1], ['nombre' => 'Lunes'])->id,
            'hora_salida' => '08:00',
            'sobreventa_permitida' => false,
            'estado_id' => Estado::ACTIVO_ID,
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

    private function createTicketPlantilla(): TicketPlantilla
    {
        return TicketPlantilla::query()->firstOrCreate(
            ['nombre' => 'Plantilla'],
            [
                'image_path' => 'ticket-plantillas/default.png',
                'estado_id' => Estado::ACTIVO_ID,
                'es_predeterminada' => true,
            ],
        );
    }

    private function estado(int $id, string $nombre): Estado
    {
        return Estado::query()->firstOrCreate(['id' => $id], ['nombre' => $nombre]);
    }
}
