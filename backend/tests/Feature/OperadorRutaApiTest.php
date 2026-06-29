<?php

namespace Tests\Feature;

use App\Models\Estado;
use App\Models\Operador;
use App\Models\OperadorRuta;
use App\Models\Role;
use App\Models\Ruta;
use App\Models\TipoOperador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperadorRutaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_empresario_without_operator_cannot_manage_operator_routes(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');

        Sanctum::actingAs($empresario);

        $this->getJson('/api/operador/rutas')
            ->assertNotFound()
            ->assertJsonPath('message', 'El empresario autenticado no tiene operador registrado.');
    }

    public function test_empresario_can_list_own_operator_routes_paginated(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $this->createOperadorRuta($operador, $ruta);

        Sanctum::actingAs($empresario);

        $this->getJson('/api/operador/rutas')
            ->assertOk()
            ->assertJsonStructure([
                'operador_rutas' => [
                    [
                        'id',
                        'ruta',
                        'denominacion',
                        'tarifa',
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
            ->assertJsonPath('operador_rutas.0.ruta', '302');
    }

    public function test_empresario_can_assign_active_route_to_operator(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $this->createOperador($empresario);
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador/rutas', [
            'ruta_id' => $ruta->id,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Ruta asignada al operador correctamente.')
            ->assertJsonPath('operador_ruta.ruta', $ruta->ruta)
            ->assertJsonPath('operador_ruta.estado.nombre', 'Activo');
    }

    public function test_empresario_cannot_assign_duplicate_or_inactive_or_missing_route(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $inactiveRuta = $this->createRuta(
            ruta: '312',
            denominacion: 'San Miguel - San Salvador',
            estadoId: Estado::DESACTIVADO_ID,
            estadoName: 'Desactivado',
        );
        $this->createOperadorRuta($operador, $ruta);

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador/rutas', [
            'ruta_id' => $ruta->id,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'La ruta ya esta asignada al operador.');

        $this->postJson('/api/operador/rutas', [
            'ruta_id' => $inactiveRuta->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La ruta seleccionada no esta activa.');

        $this->postJson('/api/operador/rutas', [
            'ruta_id' => 999999,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La ruta seleccionada no existe.');
    }

    public function test_operator_route_request_rejects_forbidden_fields(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador/rutas', [
            'ruta_id' => $ruta->id,
            'operador_id' => $operador->id,
            'estado_id' => Estado::ACTIVO_ID,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['operador_id', 'estado_id']);
    }

    public function test_empresario_can_toggle_own_operator_route_status(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $operadorRuta = $this->createOperadorRuta($operador, $ruta);
        $this->estado(Estado::DESACTIVADO_ID, 'Desactivado');

        Sanctum::actingAs($empresario);

        $this->patchJson("/api/operador/rutas/{$operadorRuta->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('message', 'Estado de la ruta asignada actualizado correctamente.')
            ->assertJsonPath('operador_ruta.estado.nombre', 'Desactivado');

        $this->patchJson("/api/operador/rutas/{$operadorRuta->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('operador_ruta.estado.nombre', 'Activo');
    }

    public function test_empresario_can_physically_delete_own_operator_route(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $operadorRuta = $this->createOperadorRuta($operador, $ruta);

        Sanctum::actingAs($empresario);

        $this->deleteJson("/api/operador/rutas/{$operadorRuta->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Ruta asignada eliminada correctamente.');

        $this->assertDatabaseMissing('operador_rutas', [
            'id' => $operadorRuta->id,
        ]);
    }

    public function test_empresario_cannot_toggle_or_delete_operator_route_from_another_operator(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $otherEmpresario = $this->createUser('empresario', 'other@example.test');
        $this->createOperador($empresario);
        $otherOperador = $this->createOperador($otherEmpresario);
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $otherOperadorRuta = $this->createOperadorRuta($otherOperador, $ruta);

        Sanctum::actingAs($empresario);

        $this->patchJson("/api/operador/rutas/{$otherOperadorRuta->id}/toggle-status")
            ->assertNotFound()
            ->assertJsonPath('message', 'La asignacion de ruta no pertenece al operador autenticado.');

        $this->deleteJson("/api/operador/rutas/{$otherOperadorRuta->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'La asignacion de ruta no pertenece al operador autenticado.');
    }

    public function test_security_rules_for_operator_route_endpoints(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $inactiveOperatorOwner = $this->createUser('empresario', 'inactive@example.test');
        $this->createOperador(
            user: $inactiveOperatorOwner,
            estadoId: Estado::DESACTIVADO_ID,
            estadoName: 'Desactivado',
        );

        $this->getJson('/api/operador/rutas')
            ->assertUnauthorized();

        Sanctum::actingAs($admin);

        $this->getJson('/api/operador/rutas')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permisos para acceder a este recurso.');

        Sanctum::actingAs($inactiveOperatorOwner);

        $this->getJson('/api/operador/rutas')
            ->assertForbidden()
            ->assertJsonPath('message', 'El operador esta desactivado. No puede realizar acciones operativas.');

        Sanctum::actingAs($empresario);

        $this->getJson('/api/operador/rutas/1')
            ->assertMethodNotAllowed();

        $this->putJson('/api/operador/rutas/1')
            ->assertMethodNotAllowed();
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

    private function estado(int $id, string $nombre): Estado
    {
        return Estado::query()->firstOrCreate(['id' => $id], ['nombre' => $nombre]);
    }
}
