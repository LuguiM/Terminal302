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
use App\Models\TipoBus;
use App\Models\TipoOperador;
use App\Models\User;
use App\Models\VentaHorario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VentaHorarioApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_vendedor_can_list_available_active_routes_with_active_schedules(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-29 10:00:00', 'America/El_Salvador'));

        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        [$ruta, $operador, $bus, $dia] = $this->createSchedulableContext();
        $this->createHorario($ruta, $operador, $bus, $dia, '10:30');
        $inactiveRuta = $this->createRuta('312', 'San Miguel - San Salvador', Estado::DESACTIVADO_ID, 'Desactivado');
        $withoutSchedules = $this->createRuta('301', 'Jiquilisco - Usulutan');
        $differentDay = $this->createRuta('303', 'Santa Elena - San Salvador');
        $this->createScheduleForRuta($inactiveRuta, '08:00');
        $this->createScheduleForRuta($differentDay, '09:00', $this->createDia(2, 'Martes'));

        Sanctum::actingAs($vendedor);

        $this->getJson('/api/vendedor/rutas-disponibles')
            ->assertOk()
            ->assertJsonPath('rutas.0.id', $ruta->id)
            ->assertJsonCount(1, 'rutas');

        $this->assertDatabaseHas('rutas', [
            'id' => $withoutSchedules->id,
        ]);
        $this->assertDatabaseHas('rutas', [
            'id' => $differentDay->id,
        ]);
    }

    public function test_vendedor_can_read_available_schedules_and_operational_sales_are_created_once(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-29 10:00:00', 'America/El_Salvador'));

        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        [$ruta, $operador, $bus, $dia] = $this->createSchedulableContext();
        $this->createHorario($ruta, $operador, $bus, $dia, '08:00');
        $enMeta = $this->createHorario($ruta, $operador, $bus, $dia, '10:30', true);
        $proximo = $this->createHorario($ruta, $operador, $bus, $dia, '12:00');

        Sanctum::actingAs($vendedor);

        $this->getJson("/api/vendedor/rutas/{$ruta->id}/horarios-disponibles")
            ->assertOk()
            ->assertJsonPath('fecha_operacion', '2026-06-29')
            ->assertJsonPath('en_meta.horario_id', $enMeta->id)
            ->assertJsonPath('en_meta.hora_salida', '10:30')
            ->assertJsonPath('en_meta.capacidad', 45)
            ->assertJsonPath('en_meta.total_tickets_vendidos', 0)
            ->assertJsonPath('en_meta.total_tickets_sobreventa', 0)
            ->assertJsonPath('en_meta.sobreventa_permitida', true)
            ->assertJsonPath('en_meta.venta_cerrada', false)
            ->assertJsonPath('en_meta.puede_vender', true)
            ->assertJsonPath('proximo_a_salir.horario_id', $proximo->id)
            ->assertJsonPath('proximo_a_salir.hora_salida', '12:00');

        $this->assertDatabaseCount('ventas_horarios', 2);

        $this->getJson("/api/vendedor/rutas/{$ruta->id}/horarios-disponibles")
            ->assertOk();

        $this->assertDatabaseCount('ventas_horarios', 2);
    }

    public function test_available_schedules_returns_null_next_when_there_is_no_later_schedule_today(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-29 17:00:00', 'America/El_Salvador'));

        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        [$ruta, $operador, $bus, $dia] = $this->createSchedulableContext();
        $horario = $this->createHorario($ruta, $operador, $bus, $dia, '18:00');

        Sanctum::actingAs($vendedor);

        $this->getJson("/api/vendedor/rutas/{$ruta->id}/horarios-disponibles")
            ->assertOk()
            ->assertJsonPath('en_meta.horario_id', $horario->id)
            ->assertJsonPath('proximo_a_salir', null);
    }

    public function test_expired_sale_schedule_is_closed_and_hidden_from_available_schedules(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-29 10:00:00', 'America/El_Salvador'));

        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        [$ruta, $operador, $bus, $dia] = $this->createSchedulableContext();
        $horario = $this->createHorario($ruta, $operador, $bus, $dia, '09:00');
        $ventaHorario = $this->createVentaHorario($horario, '2026-06-29');

        Sanctum::actingAs($vendedor);

        $this->getJson('/api/vendedor/rutas-disponibles')
            ->assertOk()
            ->assertJsonCount(0, 'rutas');

        $this->getJson("/api/vendedor/rutas/{$ruta->id}/horarios-disponibles")
            ->assertNotFound()
            ->assertJsonPath('message', 'No existen horarios activos vigentes para esta ruta.');

        $this->assertDatabaseHas('ventas_horarios', [
            'id' => $ventaHorario->id,
            'venta_cerrada' => true,
            'motivo_cierre' => 'Hora de salida alcanzada.',
        ]);
    }

    public function test_vendedor_can_close_sale_schedule_cycle(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-29 10:00:00', 'America/El_Salvador'));

        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        [$ruta, $operador, $bus, $dia] = $this->createSchedulableContext();
        $horario = $this->createHorario($ruta, $operador, $bus, $dia, '10:30');
        $ventaHorario = $this->createVentaHorario($horario, '2026-06-29');

        Sanctum::actingAs($vendedor);

        $this->patchJson("/api/vendedor/ventas-horarios/{$ventaHorario->id}/cerrar", [
            'motivo_cierre' => 'Unidad completa',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Venta de horario cerrada correctamente.')
            ->assertJsonPath('venta_horario.venta_cerrada', true)
            ->assertJsonPath('venta_horario.cerrada_por.name', $vendedor->name)
            ->assertJsonMissingPath('venta_horario.cerrada_por.id')
            ->assertJsonPath('venta_horario.motivo_cierre', 'Unidad completa');

        $this->assertDatabaseHas('ventas_horarios', [
            'id' => $ventaHorario->id,
            'venta_cerrada' => true,
            'cerrada_por' => $vendedor->id,
            'motivo_cierre' => 'Unidad completa',
        ]);

        $this->getJson("/api/vendedor/rutas/{$ruta->id}/horarios-disponibles")
            ->assertOk()
            ->assertJsonPath('en_meta.puede_vender', false);
    }

    public function test_vendedor_cannot_close_already_closed_sale_schedule_cycle(): void
    {
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        [$ruta, $operador, $bus, $dia] = $this->createSchedulableContext();
        $horario = $this->createHorario($ruta, $operador, $bus, $dia, '10:30');
        $ventaHorario = $this->createVentaHorario($horario, now()->toDateString(), true, $vendedor);

        Sanctum::actingAs($vendedor);

        $this->patchJson("/api/vendedor/ventas-horarios/{$ventaHorario->id}/cerrar")
            ->assertStatus(409)
            ->assertJsonPath('message', 'La venta de horario ya esta cerrada.');
    }

    public function test_vendedor_gets_clear_errors_for_invalid_route_or_missing_active_schedules(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-29 10:00:00', 'America/El_Salvador'));

        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');
        $inactiveRuta = $this->createRuta('312', 'San Miguel - San Salvador', Estado::DESACTIVADO_ID, 'Desactivado');
        $ruta = $this->createRuta('301', 'Jiquilisco - Usulutan');
        $this->createScheduleForRuta($ruta, '08:00', $this->createDia(2, 'Martes'));

        Sanctum::actingAs($vendedor);

        $this->getJson('/api/vendedor/rutas/999999/horarios-disponibles')
            ->assertNotFound()
            ->assertJsonPath('message', 'La ruta seleccionada no existe.');

        $this->getJson("/api/vendedor/rutas/{$inactiveRuta->id}/horarios-disponibles")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La ruta seleccionada no esta activa.');

        $this->getJson("/api/vendedor/rutas/{$ruta->id}/horarios-disponibles")
            ->assertNotFound()
            ->assertJsonPath('message', 'No existen horarios activos vigentes para esta ruta.');
    }

    public function test_security_and_unregistered_vendor_sale_schedule_endpoints(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $vendedor = $this->createUser('vendedor', 'vendedor@example.test');

        $this->getJson('/api/vendedor/rutas-disponibles')
            ->assertUnauthorized();

        Sanctum::actingAs($admin);

        $this->getJson('/api/vendedor/rutas-disponibles')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permisos para acceder a este recurso.');

        Sanctum::actingAs($vendedor);

        $this->postJson('/api/vendedor/ventas-horarios')
            ->assertNotFound();

        $this->getJson('/api/vendedor/ventas-horarios/1')
            ->assertNotFound();

        $this->deleteJson('/api/vendedor/ventas-horarios/1')
            ->assertNotFound();
    }

    /**
     * @return array{0: Ruta, 1: Operador, 2: Bus, 3: Dia}
     */
    private function createSchedulableContext(): array
    {
        $empresario = $this->createUser('empresario', 'empresario'.uniqid().'@example.test');
        $operador = $this->createOperador($empresario);
        $ruta = $this->createRuta('302'.uniqid(), 'Usulutan - San Salvador');
        $this->createOperadorRuta($operador, $ruta);
        $bus = $this->createBus($operador, $ruta, $this->createTipoBus('bus'), 'AB'.substr(uniqid(), -5));
        $dia = $this->createDia(1, 'Lunes');

        return [$ruta, $operador, $bus, $dia];
    }

    private function createScheduleForRuta(
        Ruta $ruta,
        string $horaSalida,
        ?Dia $dia = null,
        int $estadoId = Estado::ACTIVO_ID,
        string $estadoName = 'Activo',
    ): Horario {
        $empresario = $this->createUser('empresario', 'ctx'.uniqid().'@example.test');
        $operador = $this->createOperador($empresario);
        $this->createOperadorRuta($operador, $ruta);
        $bus = $this->createBus($operador, $ruta, $this->createTipoBus('bus'), 'PL'.substr(uniqid(), -5));

        return $this->createHorario($ruta, $operador, $bus, $dia ?? $this->createDia(1, 'Lunes'), $horaSalida, false, $estadoId, $estadoName);
    }

    private function createHorario(
        Ruta $ruta,
        Operador $operador,
        Bus $bus,
        Dia $dia,
        string $horaSalida,
        bool $sobreventaPermitida = false,
        int $estadoId = Estado::ACTIVO_ID,
        string $estadoName = 'Activo',
    ): Horario {
        $estado = $this->estado($estadoId, $estadoName);

        return Horario::query()->create([
            'ruta_id' => $ruta->id,
            'operador_id' => $operador->id,
            'bus_id' => $bus->id,
            'dia_id' => $dia->id,
            'hora_salida' => $horaSalida,
            'sobreventa_permitida' => $sobreventaPermitida,
            'estado_id' => $estado->id,
        ]);
    }

    private function createVentaHorario(
        Horario $horario,
        string $fechaOperacion,
        bool $cerrada = false,
        ?User $cerradaPor = null,
    ): VentaHorario {
        return VentaHorario::query()->create([
            'horario_id' => $horario->id,
            'fecha_operacion' => $fechaOperacion,
            'venta_cerrada' => $cerrada,
            'cerrada_por' => $cerradaPor?->id,
            'fecha_cierre' => $cerrada ? now() : null,
            'motivo_cierre' => $cerrada ? 'Cerrada previamente' : null,
            'total_tickets_vendidos' => 0,
            'total_tickets_sobreventa' => 0,
            'estado_id' => Estado::ACTIVO_ID,
        ]);
    }

    private function createUser(string $roleName, string $email): User
    {
        $role = Role::query()->firstOrCreate(['nombre' => $roleName]);
        $estado = $this->estado(Estado::ACTIVO_ID, 'Activo');

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

    private function createRuta(
        string $ruta,
        string $denominacion,
        int $estadoId = Estado::ACTIVO_ID,
        string $estadoName = 'Activo',
    ): Ruta {
        $estado = $this->estado($estadoId, $estadoName);

        return Ruta::query()->create([
            'ruta' => $ruta,
            'denominacion' => $denominacion,
            'tarifa' => 1.50,
            'estado_id' => $estado->id,
        ]);
    }

    private function createOperadorRuta(Operador $operador, Ruta $ruta): OperadorRuta
    {
        $estado = $this->estado(Estado::ACTIVO_ID, 'Activo');

        return OperadorRuta::query()->create([
            'operador_id' => $operador->id,
            'ruta_id' => $ruta->id,
            'estado_id' => $estado->id,
        ]);
    }

    private function createTipoBus(string $nombre): TipoBus
    {
        return TipoBus::query()->firstOrCreate(['nombre' => $nombre]);
    }

    private function createBus(Operador $operador, Ruta $ruta, TipoBus $tipoBus, string $placa): Bus
    {
        $estado = $this->estado(Estado::ACTIVO_ID, 'Activo');

        return Bus::query()->create([
            'operador_id' => $operador->id,
            'ruta_id' => $ruta->id,
            'placa' => $placa,
            'marca' => 'Mercedes',
            'nombre_unidad' => 'Unidad '.$placa,
            'capacidad' => 45,
            'tipo_bus_id' => $tipoBus->id,
            'estado_id' => $estado->id,
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
