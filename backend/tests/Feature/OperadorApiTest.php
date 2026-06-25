<?php

namespace Tests\Feature;

use App\Models\Estado;
use App\Models\Operador;
use App\Models\Role;
use App\Models\TipoOperador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperadorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_empresario_without_operator_gets_clear_not_found_response(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');

        Sanctum::actingAs($empresario);

        $this->getJson('/api/operador/me')
            ->assertNotFound()
            ->assertJsonPath('message', 'El empresario autenticado no tiene operador registrado.');
    }

    public function test_empresario_can_register_empresa_operator_and_read_it(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $tipoEmpresa = $this->tipoOperador('empresa');

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador', [
            'tipo_operador_id' => $tipoEmpresa->id,
            'nombre' => 'Transportes Central',
            'razon_social' => 'Transportes Central S.A.',
            'representante_legal' => 'Maria Lopez',
            'documento' => null,
            'telefono' => '2222-3333',
            'correo' => 'contacto@central.test',
            'direccion' => 'Terminal central',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Operador registrado correctamente.')
            ->assertJsonPath('operador.nombre', 'Transportes Central')
            ->assertJsonPath('operador.tipo_operador.nombre', 'empresa')
            ->assertJsonPath('operador.estado.nombre', 'Activo');

        $this->getJson('/api/operador/me')
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Transportes Central')
            ->assertJsonPath('data.razon_social', 'Transportes Central S.A.');
    }

    public function test_empresario_can_register_persona_operator_without_company_fields(): void
    {
        $empresario = $this->createUser('empresario', 'persona@example.test');
        $tipoPersona = $this->tipoOperador('persona');

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador', [
            'tipo_operador_id' => $tipoPersona->id,
            'nombre' => 'Juan Perez',
            'telefono' => '7777-8888',
            'correo' => 'juan@example.test',
            'direccion' => 'Barrio El Centro',
        ])
            ->assertCreated()
            ->assertJsonPath('operador.nombre', 'Juan Perez')
            ->assertJsonPath('operador.tipo_operador.nombre', 'persona')
            ->assertJsonPath('operador.razon_social', null)
            ->assertJsonPath('operador.representante_legal', null);
    }

    public function test_empresario_cannot_register_second_operator(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $this->createOperador($empresario);
        $tipoPersona = $this->tipoOperador('persona');

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador', [
            'tipo_operador_id' => $tipoPersona->id,
            'nombre' => 'Otro operador',
            'telefono' => '7777-8888',
            'correo' => 'otro@example.test',
            'direccion' => 'San Salvador',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'El empresario ya tiene un operador registrado.');
    }

    public function test_non_empresario_cannot_access_empresario_operator_routes(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');

        Sanctum::actingAs($admin);

        $this->getJson('/api/operador/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permisos para acceder a este recurso.');
    }

    public function test_empresario_cannot_update_another_empresario_operator(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $otherEmpresario = $this->createUser('empresario', 'other@example.test');
        $otherOperador = $this->createOperador($otherEmpresario);
        $tipoPersona = $this->tipoOperador('persona');

        Sanctum::actingAs($empresario);

        $this->putJson("/api/operador/{$otherOperador->id}", [
            'tipo_operador_id' => $tipoPersona->id,
            'nombre' => 'Intento ajeno',
            'telefono' => '7777-8888',
            'correo' => 'ajeno@example.test',
            'direccion' => 'San Salvador',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'El operador no pertenece al empresario autenticado.');
    }

    public function test_admin_can_list_and_show_operators(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/operadores')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('operadores.0.nombre', $operador->nombre)
            ->assertJsonPath('operadores.0.user.email', $empresario->email);

        $this->getJson("/api/admin/operadores/{$operador->id}")
            ->assertOk()
            ->assertJsonPath('data.nombre', $operador->nombre)
            ->assertJsonPath('data.user.email', $empresario->email);
    }

    public function test_admin_deactivates_operator_with_required_reason_and_empresario_keeps_login(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $this->estado(Estado::DESACTIVADO_ID, 'Desactivado');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/operadores/{$operador->id}/toggle-status")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo_desactivacion']);

        $this->patchJson("/api/admin/operadores/{$operador->id}/toggle-status", [
            'motivo_desactivacion' => 'Documentacion vencida',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Estado del operador actualizado correctamente.')
            ->assertJsonPath('operador.estado.nombre', 'Desactivado')
            ->assertJsonPath('operador.motivo_desactivacion', 'Documentacion vencida');

        $this->postJson('/api/login', [
            'email' => $empresario->email,
            'password' => 'Temporal123',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', $empresario->email);

        Sanctum::actingAs($empresario);

        $this->getJson('/api/operador/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'El operador esta desactivado. No puede realizar acciones operativas.');
    }

    public function test_admin_reactivates_operator_and_clears_deactivation_reason(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador(
            user: $empresario,
            estadoId: Estado::DESACTIVADO_ID,
            estadoName: 'Desactivado',
            motivoDesactivacion: 'Documentacion vencida',
        );

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/operadores/{$operador->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('operador.estado.nombre', 'Activo')
            ->assertJsonPath('operador.motivo_desactivacion', null);
    }

    public function test_operator_requests_validate_type_specific_and_forbidden_fields(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $tipoEmpresa = $this->tipoOperador('empresa');

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador', [
            'tipo_operador_id' => $tipoEmpresa->id,
            'nombre' => 'Empresa incompleta',
            'telefono' => '2222-3333',
            'correo' => 'empresa@example.test',
            'direccion' => 'San Salvador',
            'estado_id' => Estado::ACTIVO_ID,
            'user_id' => $empresario->id,
            'motivo_desactivacion' => 'No permitido',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'razon_social',
                'representante_legal',
                'estado_id',
                'user_id',
                'motivo_desactivacion',
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
        ?string $motivoDesactivacion = null,
    ): Operador {
        $tipoPersona = $this->tipoOperador('persona');
        $estado = $this->estado($estadoId, $estadoName);

        return Operador::query()->create([
            'user_id' => $user->id,
            'tipo_operador_id' => $tipoPersona->id,
            'nombre' => 'Operador '.$user->id,
            'telefono' => '2222-3333',
            'correo' => 'operador'.$user->id.'@example.test',
            'direccion' => 'San Salvador',
            'estado_id' => $estado->id,
            'motivo_desactivacion' => $motivoDesactivacion,
        ]);
    }

    private function tipoOperador(string $nombre): TipoOperador
    {
        return TipoOperador::query()->firstOrCreate(['nombre' => $nombre]);
    }

    private function estado(int $id, string $nombre): Estado
    {
        return Estado::query()->firstOrCreate(['id' => $id], ['nombre' => $nombre]);
    }
}
