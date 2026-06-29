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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HorarioApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_active_routes_for_schedules(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $this->createRuta('302', 'Usulutan - San Salvador');
        $this->createRuta('312', 'San Miguel - San Salvador', Estado::DESACTIVADO_ID, 'Desactivado');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/horarios/rutas?search=Usu')
            ->assertOk()
            ->assertJsonPath('rutas.0.ruta', '302')
            ->assertJsonCount(1, 'rutas');
    }

    public function test_admin_can_list_days_and_schedules_for_route_and_day(): void
    {
        [$admin, $ruta, $operador, $bus, $dia] = $this->createSchedulableContext();
        $horario = $this->createHorario($ruta, $operador, $bus, $dia, '08:30');
        $otherDia = $this->createDia(2, 'Martes');

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/horarios/rutas/{$ruta->id}")
            ->assertOk()
            ->assertJsonPath('ruta.id', $ruta->id)
            ->assertJsonPath('dias.0.id', $dia->id)
            ->assertJsonCount(1, 'dias');

        $this->getJson("/api/admin/horarios?ruta_id={$ruta->id}&dia_id={$dia->id}")
            ->assertOk()
            ->assertJsonPath('horarios.0.id', $horario->id)
            ->assertJsonPath('horarios.0.hora_salida', '08:30')
            ->assertJsonPath('horarios.0.sobreventa_permitida', false);

        $this->getJson("/api/admin/horarios?ruta_id={$ruta->id}&dia_id={$otherDia->id}")
            ->assertOk()
            ->assertJsonCount(0, 'horarios');
    }

    public function test_admin_can_list_available_operators_and_buses_for_schedule_selectors(): void
    {
        [$admin, $ruta, $operador, $bus] = $this->createSchedulableContext();

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/horarios/rutas/{$ruta->id}/operadores")
            ->assertOk()
            ->assertJsonPath('operadores.0.id', $operador->id)
            ->assertJsonCount(1, 'operadores');

        $this->getJson("/api/admin/horarios/buses?ruta_id={$ruta->id}&operador_id={$operador->id}")
            ->assertOk()
            ->assertJsonPath('buses.0.id', $bus->id)
            ->assertJsonCount(1, 'buses');
    }

    public function test_admin_can_create_schedule_and_active_status_is_assigned(): void
    {
        [$admin, $ruta, $operador, $bus, $dia] = $this->createSchedulableContext();

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/horarios', $this->horarioPayload($ruta, $operador, $bus, $dia))
            ->assertCreated()
            ->assertJsonPath('message', 'Horario creado correctamente.')
            ->assertJsonPath('horario.hora_salida', '08:30')
            ->assertJsonPath('horario.estado.nombre', 'Activo');

        $this->assertDatabaseHas('horarios', [
            'ruta_id' => $ruta->id,
            'operador_id' => $operador->id,
            'bus_id' => $bus->id,
            'dia_id' => $dia->id,
            'estado_id' => Estado::ACTIVO_ID,
        ]);
    }

    public function test_admin_cannot_create_schedule_with_estado_id_or_duplicate(): void
    {
        [$admin, $ruta, $operador, $bus, $dia] = $this->createSchedulableContext();
        $this->createHorario($ruta, $operador, $bus, $dia, '08:30');

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/horarios', [
            ...$this->horarioPayload($ruta, $operador, $bus, $dia, '09:00'),
            'estado_id' => Estado::ACTIVO_ID,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estado_id']);

        $this->postJson('/api/admin/horarios', $this->horarioPayload($ruta, $operador, $bus, $dia))
            ->assertStatus(409)
            ->assertJsonPath('message', 'El horario ya existe con la misma ruta, operador, bus, dia y hora de salida.');
    }

    public function test_admin_schedule_creation_validates_route_operator_assignment_and_bus_consistency(): void
    {
        [$admin, $ruta, $operador, $bus, $dia] = $this->createSchedulableContext();
        $inactiveRuta = $this->createRuta('312', 'San Miguel - San Salvador', Estado::DESACTIVADO_ID, 'Desactivado');
        $inactiveOperadorOwner = $this->createUser('empresario', 'inactive-operator@example.test');
        $inactiveOperador = $this->createOperador($inactiveOperadorOwner, Estado::DESACTIVADO_ID, 'Desactivado');
        $otherOwner = $this->createUser('empresario', 'other@example.test');
        $otherOperador = $this->createOperador($otherOwner);
        $this->createOperadorRuta($otherOperador, $ruta);
        $otherBus = $this->createBus($otherOperador, $ruta, $this->createTipoBus('microbus'), 'ZZ-999');
        $otherRuta = $this->createRuta('301', 'Jiquilisco - Usulutan');
        $this->createOperadorRuta($operador, $otherRuta);
        $busOtherRoute = $this->createBus($operador, $otherRuta, $this->createTipoBus('coaster'), 'YY-888');
        $inactiveBus = $this->createBus($operador, $ruta, $this->createTipoBus('bus'), 'XX-777', Estado::DESACTIVADO_ID, 'Desactivado');

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/horarios', $this->horarioPayload($inactiveRuta, $operador, $bus, $dia))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La ruta seleccionada no esta activa.');

        $this->postJson('/api/admin/horarios', $this->horarioPayload($ruta, $inactiveOperador, $bus, $dia))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El operador seleccionado no esta activo.');

        $this->postJson('/api/admin/horarios', $this->horarioPayload($ruta, $this->createOperador($this->createUser('empresario', 'no-route@example.test')), $bus, $dia))
            ->assertForbidden()
            ->assertJsonPath('message', 'El operador no tiene asignada la ruta seleccionada.');

        $this->postJson('/api/admin/horarios', $this->horarioPayload($ruta, $operador, $inactiveBus, $dia))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El bus seleccionado no esta activo.');

        $this->postJson('/api/admin/horarios', $this->horarioPayload($ruta, $operador, $otherBus, $dia))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El bus no pertenece al operador seleccionado.');

        $this->postJson('/api/admin/horarios', $this->horarioPayload($ruta, $operador, $busOtherRoute, $dia))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El bus no pertenece a la ruta seleccionada.');
    }

    public function test_admin_can_update_toggle_and_delete_schedule(): void
    {
        [$admin, $ruta, $operador, $bus, $dia] = $this->createSchedulableContext();
        $horario = $this->createHorario($ruta, $operador, $bus, $dia, '08:30');
        $this->estado(Estado::DESACTIVADO_ID, 'Desactivado');

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/horarios/{$horario->id}", $this->horarioPayload($ruta, $operador, $bus, $dia, '09:00', true))
            ->assertOk()
            ->assertJsonPath('message', 'Horario actualizado correctamente.')
            ->assertJsonPath('horario.hora_salida', '09:00')
            ->assertJsonPath('horario.sobreventa_permitida', true);

        $this->patchJson("/api/admin/horarios/{$horario->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('message', 'Estado del horario actualizado correctamente.')
            ->assertJsonPath('horario.estado.nombre', 'Desactivado');

        $this->patchJson("/api/admin/horarios/{$horario->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('horario.estado.nombre', 'Activo');

        $this->deleteJson("/api/admin/horarios/{$horario->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Horario eliminado correctamente.');

        $this->assertDatabaseMissing('horarios', [
            'id' => $horario->id,
        ]);
    }

    public function test_admin_schedule_mutation_endpoints_return_friendly_message_when_missing(): void
    {
        [$admin, $ruta, $operador, $bus, $dia] = $this->createSchedulableContext();

        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/horarios/999999', $this->horarioPayload($ruta, $operador, $bus, $dia))
            ->assertNotFound()
            ->assertJsonPath('message', 'El horario solicitado no existe.');

        $this->patchJson('/api/admin/horarios/999999/toggle-status')
            ->assertNotFound()
            ->assertJsonPath('message', 'El horario solicitado no existe.');

        $this->deleteJson('/api/admin/horarios/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'El horario solicitado no existe.');
    }

    public function test_empresario_can_list_own_active_routes_days_and_schedules_only(): void
    {
        [$admin, $ruta, $operador, $bus, $dia, $empresario] = $this->createSchedulableContext();
        $horario = $this->createHorario($ruta, $operador, $bus, $dia, '08:30');
        $inactiveHorario = $this->createHorario($ruta, $operador, $bus, $this->createDia(2, 'Martes'), '09:00', false, Estado::DESACTIVADO_ID, 'Desactivado');

        Sanctum::actingAs($empresario);

        $this->getJson('/api/operador/horarios/rutas')
            ->assertOk()
            ->assertJsonPath('rutas.0.id', $ruta->id)
            ->assertJsonCount(1, 'rutas');

        $this->getJson("/api/operador/horarios/rutas/{$ruta->id}")
            ->assertOk()
            ->assertJsonPath('dias.0.id', $dia->id)
            ->assertJsonCount(1, 'dias');

        $this->getJson("/api/operador/horarios?ruta_id={$ruta->id}&dia_id={$dia->id}")
            ->assertOk()
            ->assertJsonPath('horarios.0.id', $horario->id)
            ->assertJsonCount(1, 'horarios');

        $this->assertDatabaseHas('horarios', [
            'id' => $inactiveHorario->id,
            'estado_id' => Estado::DESACTIVADO_ID,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/horarios?ruta_id={$ruta->id}&dia_id={$inactiveHorario->dia_id}")
            ->assertOk()
            ->assertJsonPath('horarios.0.id', $inactiveHorario->id);
    }

    public function test_empresario_without_operator_or_with_foreign_route_cannot_read_schedules(): void
    {
        [$admin, $ruta] = $this->createSchedulableContext();
        $withoutOperator = $this->createUser('empresario', 'without-operator@example.test');
        $otherOwner = $this->createUser('empresario', 'foreign-route@example.test');
        $this->createOperador($otherOwner);

        Sanctum::actingAs($withoutOperator);

        $this->getJson('/api/operador/horarios/rutas')
            ->assertNotFound()
            ->assertJsonPath('message', 'El empresario autenticado no tiene operador registrado.');

        Sanctum::actingAs($otherOwner);

        $this->getJson("/api/operador/horarios/rutas/{$ruta->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'La ruta no pertenece al operador autenticado.');
    }

    public function test_horario_security_and_missing_show_endpoints(): void
    {
        [$admin, $ruta, $operador, $bus, $dia, $empresario] = $this->createSchedulableContext();
        $seller = $this->createUser('vendedor', 'seller@example.test');

        $this->getJson('/api/admin/horarios/rutas')
            ->assertUnauthorized();

        Sanctum::actingAs($seller);

        $this->getJson('/api/admin/horarios/rutas')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permisos para acceder a este recurso.');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/horarios/1')
            ->assertMethodNotAllowed();

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/horarios', $this->horarioPayload($ruta, $operador, $bus, $dia))
            ->assertCreated();

        Sanctum::actingAs($seller);

        $this->getJson('/api/operador/horarios/rutas')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permisos para acceder a este recurso.');

        Sanctum::actingAs($empresario);

        $this->getJson('/api/operador/horarios/1')
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Ruta, 2: Operador, 3: Bus, 4: Dia, 5: User}
     */
    private function createSchedulableContext(): array
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $this->createOperadorRuta($operador, $ruta);
        $tipoBus = $this->createTipoBus('bus');
        $bus = $this->createBus($operador, $ruta, $tipoBus, 'AB-123');
        $dia = $this->createDia(1, 'Lunes');

        return [$admin, $ruta, $operador, $bus, $dia, $empresario];
    }

    /**
     * @return array<string, mixed>
     */
    private function horarioPayload(
        Ruta $ruta,
        Operador $operador,
        Bus $bus,
        Dia $dia,
        string $horaSalida = '08:30',
        bool $sobreventaPermitida = false,
    ): array {
        return [
            'ruta_id' => $ruta->id,
            'operador_id' => $operador->id,
            'bus_id' => $bus->id,
            'dia_id' => $dia->id,
            'hora_salida' => $horaSalida,
            'sobreventa_permitida' => $sobreventaPermitida,
        ];
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

    private function createOperador(
        User $user,
        int $estadoId = Estado::ACTIVO_ID,
        string $estadoName = 'Activo',
    ): Operador {
        $tipoPersona = TipoOperador::query()->firstOrCreate(['nombre' => 'persona']);
        $estado = $this->estado($estadoId, $estadoName);

        return Operador::query()->create([
            'user_id' => $user->id,
            'tipo_operador_id' => $tipoPersona->id,
            'nombre' => 'Operador '.$user->id,
            'telefono' => '2222-3333',
            'correo' => 'operador'.$user->id.'@example.test',
            'direccion' => 'San Salvador',
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

    private function createOperadorRuta(
        Operador $operador,
        Ruta $ruta,
        int $estadoId = Estado::ACTIVO_ID,
        string $estadoName = 'Activo',
    ): OperadorRuta {
        $estado = $this->estado($estadoId, $estadoName);

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

    private function createBus(
        Operador $operador,
        Ruta $ruta,
        TipoBus $tipoBus,
        string $placa,
        int $estadoId = Estado::ACTIVO_ID,
        string $estadoName = 'Activo',
    ): Bus {
        $estado = $this->estado($estadoId, $estadoName);

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
