<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Estado;
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

class BusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_empresario_without_operator_cannot_manage_buses(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');

        Sanctum::actingAs($empresario);

        $this->getJson('/api/operador/buses')
            ->assertNotFound()
            ->assertJsonPath('message', 'El empresario autenticado no tiene operador registrado.');
    }

    public function test_empresario_can_list_bus_types_for_form_selectors(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $this->createOperador($empresario);
        $this->createTipoBus('bus');
        $this->createTipoBus('microbus');

        Sanctum::actingAs($empresario);

        $this->getJson('/api/operador/tipo-buses')
            ->assertOk()
            ->assertJsonMissingPath('pagination')
            ->assertJsonStructure([
                'tipo_buses' => [
                    [
                        'id',
                        'nombre',
                    ],
                ],
            ])
            ->assertJsonCount(2, 'tipo_buses');
    }

    public function test_empresario_can_list_own_buses_paginated_with_filters(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $tipoBus = $this->createTipoBus('bus');
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $otherRuta = $this->createRuta('312', 'San Miguel - San Salvador');
        $this->createOperadorRuta($operador, $ruta);
        $this->createOperadorRuta($operador, $otherRuta);
        $bus = $this->createBus($operador, $ruta, $tipoBus, placa: 'AB-123', marca: 'Mercedes', nombreUnidad: 'Unidad Centro');
        $this->createBus($operador, $otherRuta, $tipoBus, placa: 'CD-456', marca: 'Toyota', nombreUnidad: 'Unidad Oriente');

        Sanctum::actingAs($empresario);

        $this->getJson("/api/operador/buses?search=Mercedes&ruta_id={$ruta->id}&estado_id=".Estado::ACTIVO_ID)
            ->assertOk()
            ->assertJsonStructure([
                'buses' => [
                    [
                        'id',
                        'ruta',
                        'placa',
                        'marca',
                        'nombre_unidad',
                        'capacidad',
                        'tipo_bus',
                        'estado',
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
            ->assertJsonPath('buses.0.id', $bus->id)
            ->assertJsonPath('buses.0.placa', 'AB-123');
    }

    public function test_empresario_can_create_bus_with_active_assigned_route(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $tipoBus = $this->createTipoBus('bus');
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $this->createOperadorRuta($operador, $ruta);

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador/buses', [
            'ruta_id' => $ruta->id,
            'placa' => 'AB-123',
            'marca' => 'Mercedes',
            'nombre_unidad' => 'Unidad Centro',
            'capacidad' => 45,
            'tipo_bus_id' => $tipoBus->id,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Bus registrado correctamente.')
            ->assertJsonPath('bus.placa', 'AB-123')
            ->assertJsonPath('bus.estado.nombre', 'Activo');

        $this->assertDatabaseHas('buses', [
            'operador_id' => $operador->id,
            'ruta_id' => $ruta->id,
            'placa' => 'AB-123',
            'estado_id' => Estado::ACTIVO_ID,
        ]);
    }

    public function test_bus_request_rejects_forbidden_fields(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $tipoBus = $this->createTipoBus('bus');
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $this->createOperadorRuta($operador, $ruta);

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador/buses', [
            'operador_id' => $operador->id,
            'ruta_id' => $ruta->id,
            'placa' => 'AB-123',
            'marca' => 'Mercedes',
            'capacidad' => 45,
            'tipo_bus_id' => $tipoBus->id,
            'estado_id' => Estado::ACTIVO_ID,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['operador_id', 'estado_id']);
    }

    public function test_empresario_cannot_create_bus_with_missing_inactive_or_unassigned_route(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $tipoBus = $this->createTipoBus('bus');
        $inactiveRuta = $this->createRuta(
            ruta: '312',
            denominacion: 'San Miguel - San Salvador',
            estadoId: Estado::DESACTIVADO_ID,
            estadoName: 'Desactivado',
        );
        $unassignedRuta = $this->createRuta('301', 'Jiquilisco - Usulutan');

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador/buses', $this->busPayload([
            'ruta_id' => 999999,
            'tipo_bus_id' => $tipoBus->id,
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La ruta seleccionada no existe.');

        $this->postJson('/api/operador/buses', $this->busPayload([
            'ruta_id' => $inactiveRuta->id,
            'placa' => 'AB-456',
            'tipo_bus_id' => $tipoBus->id,
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La ruta seleccionada no esta activa.');

        $this->postJson('/api/operador/buses', $this->busPayload([
            'ruta_id' => $unassignedRuta->id,
            'placa' => 'AB-789',
            'tipo_bus_id' => $tipoBus->id,
        ]))
            ->assertForbidden()
            ->assertJsonPath('message', 'La ruta no pertenece al operador autenticado.');

        $this->assertDatabaseMissing('buses', [
            'operador_id' => $operador->id,
        ]);
    }

    public function test_empresario_cannot_create_bus_with_inactive_operator_route(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $tipoBus = $this->createTipoBus('bus');
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $this->createOperadorRuta($operador, $ruta, Estado::DESACTIVADO_ID, 'Desactivado');

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador/buses', $this->busPayload([
            'ruta_id' => $ruta->id,
            'tipo_bus_id' => $tipoBus->id,
        ]))
            ->assertForbidden()
            ->assertJsonPath('message', 'La ruta no pertenece al operador autenticado.');
    }

    public function test_bus_validates_type_capacity_and_unique_plate(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $tipoBus = $this->createTipoBus('bus');
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $this->createOperadorRuta($operador, $ruta);
        $this->createBus($operador, $ruta, $tipoBus, placa: 'AB-123');

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador/buses', $this->busPayload([
            'ruta_id' => $ruta->id,
            'placa' => 'CD-456',
            'capacidad' => 0,
            'tipo_bus_id' => 999999,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo_bus_id', 'capacidad']);

        $this->postJson('/api/operador/buses', $this->busPayload([
            'ruta_id' => $ruta->id,
            'placa' => 'AB-123',
            'tipo_bus_id' => $tipoBus->id,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['placa']);
    }

    public function test_bus_validates_salvadoran_public_transport_plate_by_bus_type(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $busType = $this->createTipoBus('bus');
        $microbusType = $this->createTipoBus('microbus');
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $this->createOperadorRuta($operador, $ruta);
        $bus = $this->createBus($operador, $ruta, $busType, placa: 'AB-123');

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador/buses', $this->busPayload([
            'ruta_id' => $ruta->id,
            'placa' => 'MB-456',
            'tipo_bus_id' => $busType->id,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['placa']);

        $this->postJson('/api/operador/buses', $this->busPayload([
            'ruta_id' => $ruta->id,
            'placa' => 'mb-a12',
            'tipo_bus_id' => $microbusType->id,
        ]))
            ->assertCreated()
            ->assertJsonPath('bus.placa', 'MB-A12');

        $this->putJson("/api/operador/buses/{$bus->id}", [
            'ruta_id' => $ruta->id,
            'placa' => 'AB-1',
            'marca' => 'Volvo',
            'nombre_unidad' => 'Unidad Centro',
            'capacidad' => 50,
            'tipo_bus_id' => $busType->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['placa']);
    }

    public function test_empresario_can_update_own_bus(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $tipoBus = $this->createTipoBus('bus');
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $this->createOperadorRuta($operador, $ruta);
        $bus = $this->createBus($operador, $ruta, $tipoBus, placa: 'AB-123');

        Sanctum::actingAs($empresario);

        $this->putJson("/api/operador/buses/{$bus->id}", [
            'ruta_id' => $ruta->id,
            'placa' => 'AB-123',
            'marca' => 'Volvo',
            'nombre_unidad' => null,
            'capacidad' => 50,
            'tipo_bus_id' => $tipoBus->id,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Bus actualizado correctamente.')
            ->assertJsonPath('bus.marca', 'Volvo')
            ->assertJsonPath('bus.capacidad', 50);
    }

    public function test_empresario_cannot_update_bus_from_another_operator(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $otherEmpresario = $this->createUser('empresario', 'other@example.test');
        $operador = $this->createOperador($empresario);
        $otherOperador = $this->createOperador($otherEmpresario);
        $tipoBus = $this->createTipoBus('bus');
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $this->createOperadorRuta($operador, $ruta);
        $this->createOperadorRuta($otherOperador, $ruta);
        $otherBus = $this->createBus($otherOperador, $ruta, $tipoBus, placa: 'ZZ-999');

        Sanctum::actingAs($empresario);

        $this->putJson("/api/operador/buses/{$otherBus->id}", $this->busPayload([
            'ruta_id' => $ruta->id,
            'tipo_bus_id' => $tipoBus->id,
        ]))
            ->assertNotFound()
            ->assertJsonPath('message', 'El bus no pertenece al operador autenticado.');
    }

    public function test_empresario_can_toggle_own_bus_status(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $tipoBus = $this->createTipoBus('bus');
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $this->createOperadorRuta($operador, $ruta);
        $bus = $this->createBus($operador, $ruta, $tipoBus);
        $this->estado(Estado::DESACTIVADO_ID, 'Desactivado');

        Sanctum::actingAs($empresario);

        $this->patchJson("/api/operador/buses/{$bus->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('message', 'Estado del bus actualizado correctamente.')
            ->assertJsonPath('bus.estado.nombre', 'Desactivado');

        $this->patchJson("/api/operador/buses/{$bus->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('bus.estado.nombre', 'Activo');
    }

    public function test_bus_show_and_delete_endpoints_are_not_registered(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $tipoBus = $this->createTipoBus('bus');
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $this->createOperadorRuta($operador, $ruta);
        $bus = $this->createBus($operador, $ruta, $tipoBus);

        Sanctum::actingAs($empresario);

        $this->getJson("/api/operador/buses/{$bus->id}")
            ->assertMethodNotAllowed();

        $this->deleteJson("/api/operador/buses/{$bus->id}")
            ->assertMethodNotAllowed();
    }

    public function test_security_rules_for_bus_endpoints(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $inactiveOperatorOwner = $this->createUser('empresario', 'inactive@example.test');
        $this->createOperador(
            user: $inactiveOperatorOwner,
            estadoId: Estado::DESACTIVADO_ID,
            estadoName: 'Desactivado',
        );

        $this->getJson('/api/operador/buses')
            ->assertUnauthorized();

        Sanctum::actingAs($admin);

        $this->getJson('/api/operador/buses')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permisos para acceder a este recurso.');

        Sanctum::actingAs($inactiveOperatorOwner);

        $this->getJson('/api/operador/buses')
            ->assertForbidden()
            ->assertJsonPath('message', 'El operador esta desactivado. No puede realizar acciones operativas.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function busPayload(array $overrides = []): array
    {
        return [
            'ruta_id' => 1,
            'placa' => 'AB-123',
            'marca' => 'Mercedes',
            'nombre_unidad' => 'Unidad Centro',
            'capacidad' => 45,
            'tipo_bus_id' => 1,
            ...$overrides,
        ];
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
        string $placa = 'AB-123',
        string $marca = 'Mercedes',
        ?string $nombreUnidad = 'Unidad Centro',
        int $capacidad = 45,
        int $estadoId = Estado::ACTIVO_ID,
        string $estadoName = 'Activo',
    ): Bus {
        $estado = $this->estado($estadoId, $estadoName);

        return Bus::query()->create([
            'operador_id' => $operador->id,
            'ruta_id' => $ruta->id,
            'placa' => $placa,
            'marca' => $marca,
            'nombre_unidad' => $nombreUnidad,
            'capacidad' => $capacidad,
            'tipo_bus_id' => $tipoBus->id,
            'estado_id' => $estado->id,
        ]);
    }

    private function estado(int $id, string $nombre): Estado
    {
        return Estado::query()->firstOrCreate(['id' => $id], ['nombre' => $nombre]);
    }
}
