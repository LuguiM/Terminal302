<?php

namespace Tests\Feature;

use App\Mail\InitialUserCredentialsMail;
use App\Models\Estado;
use App\Models\Operador;
use App\Models\OperadorEmpleado;
use App\Models\Role;
use App\Models\TipoOperador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperadorEmpleadoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_empresario_can_list_own_validator_employees_paginated_with_search(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $employee = $this->createEmpleado($operador, 'Juan Perez', 'juan@example.test');
        $this->createEmpleado($operador, 'Maria Garcia', 'maria@example.test');
        $otherEmpresario = $this->createUser('empresario', 'other-owner@example.test');
        $otherOperador = $this->createOperador($otherEmpresario);
        $this->createEmpleado($otherOperador, 'Juan Otro', 'juan-otro@example.test');

        Sanctum::actingAs($empresario);

        $this->getJson('/api/operador/empleados?search=juan')
            ->assertOk()
            ->assertJsonStructure([
                'empleados' => [
                    [
                        'id',
                        'name',
                        'email',
                        'estado',
                        'motivo_desactivacion',
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
            ->assertJsonPath('empleados.0.id', $employee->id)
            ->assertJsonPath('empleados.0.email', 'juan@example.test');
    }

    public function test_empresario_can_create_validator_employee_and_credentials_email_is_sent(): void
    {
        Mail::fake();

        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $validatorRole = Role::query()->firstOrCreate(['nombre' => 'validador']);

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador/empleados', [
            'name' => 'Nuevo Validador',
            'email' => 'nuevo-validador@example.test',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Empleado creado correctamente.')
            ->assertJsonPath('empleado.email', 'nuevo-validador@example.test')
            ->assertJsonPath('empleado.estado.nombre', 'Activo');

        $user = User::query()->where('email', 'nuevo-validador@example.test')->firstOrFail();

        $this->assertSame($validatorRole->id, $user->role_id);
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertDatabaseHas('operador_empleados', [
            'operador_id' => $operador->id,
            'user_id' => $user->id,
            'estado_id' => Estado::ACTIVO_ID,
        ]);

        Mail::assertSent(InitialUserCredentialsMail::class, function (InitialUserCredentialsMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && $mail->user->is($user)
                && $mail->purpose === InitialUserCredentialsMail::PURPOSE_INITIAL
                && strlen($mail->temporaryPassword) === 14
                && Hash::check($mail->temporaryPassword, $user->password);
        });
    }

    public function test_employee_create_rejects_forbidden_fields(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $this->createOperador($empresario);
        $role = Role::query()->firstOrCreate(['nombre' => 'validador']);

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador/empleados', [
            'name' => 'Con Campos',
            'email' => 'campos@example.test',
            'role_id' => $role->id,
            'estado_id' => Estado::ACTIVO_ID,
            'password' => 'Temporal123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id', 'estado_id', 'password']);
    }

    public function test_empresario_can_update_own_employee(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $employee = $this->createEmpleado($operador, 'Juan Perez', 'juan@example.test');

        Sanctum::actingAs($empresario);

        $this->putJson("/api/operador/empleados/{$employee->id}", [
            'name' => 'Juan Editado',
            'email' => 'juan-editado@example.test',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Empleado actualizado correctamente.')
            ->assertJsonPath('empleado.name', 'Juan Editado')
            ->assertJsonPath('empleado.email', 'juan-editado@example.test');
    }

    public function test_empresario_cannot_update_or_toggle_employee_from_other_operator(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $this->createOperador($empresario);
        $otherEmpresario = $this->createUser('empresario', 'other-owner@example.test');
        $otherOperador = $this->createOperador($otherEmpresario);
        $otherEmployee = $this->createEmpleado($otherOperador, 'Otro Validador', 'otro@example.test');

        Sanctum::actingAs($empresario);

        $this->putJson("/api/operador/empleados/{$otherEmployee->id}", [
            'name' => 'No permitido',
            'email' => 'no-permitido@example.test',
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'El empleado no pertenece al operador autenticado.');

        $this->patchJson("/api/operador/empleados/{$otherEmployee->id}/toggle-status", [
            'motivo_desactivacion' => 'Fuera de alcance',
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'El empleado no pertenece al operador autenticado.');
    }

    public function test_empresario_can_toggle_employee_status_and_reason_is_required_when_deactivating(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $employee = $this->createEmpleado($operador, 'Juan Perez', 'juan@example.test');
        $this->estado(Estado::DESACTIVADO_ID, 'Desactivado');

        Sanctum::actingAs($empresario);

        $this->patchJson("/api/operador/empleados/{$employee->id}/toggle-status")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo_desactivacion']);

        $this->patchJson("/api/operador/empleados/{$employee->id}/toggle-status", [
            'motivo_desactivacion' => 'Salida del equipo',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Estado del empleado actualizado correctamente.')
            ->assertJsonPath('empleado.estado.nombre', 'Desactivado')
            ->assertJsonPath('empleado.motivo_desactivacion', 'Salida del equipo');

        $employee->refresh();
        $this->assertSame(Estado::DESACTIVADO_ID, $employee->estado_id);
        $this->assertSame(Estado::DESACTIVADO_ID, $employee->user?->estado_id);

        $this->patchJson("/api/operador/empleados/{$employee->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('empleado.estado.nombre', 'Activo')
            ->assertJsonPath('empleado.motivo_desactivacion', null);
    }

    public function test_employee_delete_endpoint_is_not_registered(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $employee = $this->createEmpleado($operador, 'Juan Perez', 'juan@example.test');

        Sanctum::actingAs($empresario);

        $this->deleteJson("/api/operador/empleados/{$employee->id}")
            ->assertMethodNotAllowed();
    }

    public function test_security_rules_for_employee_endpoints(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $inactiveOwner = $this->createUser('empresario', 'inactive@example.test');
        $this->createOperador(
            user: $inactiveOwner,
            estadoId: Estado::DESACTIVADO_ID,
            estadoName: 'Desactivado',
        );

        $this->getJson('/api/operador/empleados')
            ->assertUnauthorized();

        Sanctum::actingAs($admin);

        $this->getJson('/api/operador/empleados')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permisos para acceder a este recurso.');

        Sanctum::actingAs($empresario);

        $this->getJson('/api/operador/empleados')
            ->assertNotFound()
            ->assertJsonPath('message', 'El empresario autenticado no tiene operador registrado.');

        Sanctum::actingAs($inactiveOwner);

        $this->getJson('/api/operador/empleados')
            ->assertForbidden()
            ->assertJsonPath('message', 'El operador esta desactivado. No puede realizar acciones operativas.');
    }

    private function createUser(
        string $roleName,
        string $email,
        int $estadoId = Estado::ACTIVO_ID,
        string $estadoName = 'Activo',
    ): User {
        $role = Role::query()->firstOrCreate(['nombre' => $roleName]);
        $estado = $this->estado($estadoId, $estadoName);

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

    private function createEmpleado(
        Operador $operador,
        string $name,
        string $email,
        int $estadoId = Estado::ACTIVO_ID,
        string $estadoName = 'Activo',
    ): OperadorEmpleado {
        $user = $this->createUser('validador', $email, $estadoId, $estadoName);
        $user->forceFill(['name' => $name])->save();
        $estado = $this->estado($estadoId, $estadoName);

        return OperadorEmpleado::query()->create([
            'operador_id' => $operador->id,
            'user_id' => $user->id,
            'estado_id' => $estado->id,
        ]);
    }

    private function estado(int $id, string $nombre): Estado
    {
        return Estado::query()->firstOrCreate(['id' => $id], ['nombre' => $nombre]);
    }
}
