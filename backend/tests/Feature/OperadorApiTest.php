<?php

namespace Tests\Feature;

use App\Models\Estado;
use App\Models\Bus;
use App\Models\Operador;
use App\Models\OperadorEmpleado;
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
            'nombre_comercial' => 'Transportes Central',
            'razon_social' => 'Transportes Central S.A.',
            'representante_legal' => 'Maria Lopez',
            'nit' => '0614-290695-101-0',
            'telefono' => '2222-3333',
            'correo_administrativo' => 'contacto@central.test',
            'direccion' => 'Terminal central',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Operador registrado correctamente.')
            ->assertJsonPath('operador.nombre_comercial', 'Transportes Central')
            ->assertJsonPath('operador.tipo_operador.nombre', 'empresa')
            ->assertJsonPath('operador.estado.nombre', 'Activo')
            ->assertJsonPath('operador.nit', '0614-290695-101-0')
            ->assertJsonPath('operador.dui', null)
            ->assertJsonPath('operador.telefono_opcional', null);

        $this->getJson('/api/operador/me')
            ->assertOk()
            ->assertJsonPath('data.nombre_comercial', 'Transportes Central')
            ->assertJsonPath('data.razon_social', 'Transportes Central S.A.');
    }

    public function test_empresario_can_register_persona_operator_without_company_fields(): void
    {
        $empresario = $this->createUser('empresario', 'persona@example.test');
        $tipoPersona = $this->tipoOperador('persona');

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador', [
            'tipo_operador_id' => $tipoPersona->id,
            'nombre_comercial' => 'Juan Perez',
            'dui' => '12345678-4',
            'telefono' => '7777-8888',
            'telefono_opcional' => '2222-3333',
        ])
            ->assertCreated()
            ->assertJsonPath('operador.nombre_comercial', 'Juan Perez')
            ->assertJsonPath('operador.tipo_operador.nombre', 'persona')
            ->assertJsonPath('operador.dui', '12345678-4')
            ->assertJsonPath('operador.razon_social', null)
            ->assertJsonPath('operador.representante_legal', null)
            ->assertJsonPath('operador.nit', null)
            ->assertJsonPath('operador.correo_administrativo', null)
            ->assertJsonPath('operador.direccion', null);
    }

    public function test_empresario_cannot_register_second_operator(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $this->createOperador($empresario);
        $tipoPersona = $this->tipoOperador('persona');

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador', [
            'tipo_operador_id' => $tipoPersona->id,
            'nombre_comercial' => 'Otro operador',
            'dui' => '12345678-4',
            'telefono' => '7777-8888',
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
            'nombre_comercial' => 'Intento ajeno',
            'dui' => '12345678-4',
            'telefono' => '7777-8888',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'El operador no pertenece al empresario autenticado.');
    }

    public function test_admin_can_list_and_show_operators(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario);
        $ruta = $this->createRuta('R-001');
        $this->createOperadorRuta($operador, $ruta);
        $this->createBus($operador, $ruta);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/operadores')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('operadores.0.nombre_comercial', $operador->nombre_comercial)
            ->assertJsonPath('operadores.0.rutas_count', 1)
            ->assertJsonPath('operadores.0.buses_count', 1)
            ->assertJsonMissingPath('operadores.0.user')
            ->assertJsonMissingPath('operadores.0.dui')
            ->assertJsonMissingPath('operadores.0.nit')
            ->assertJsonMissingPath('operadores.0.telefono')
            ->assertJsonMissingPath('operadores.0.direccion');

        $this->getJson("/api/admin/operadores/{$operador->id}")
            ->assertOk()
            ->assertJsonPath('data.nombre_comercial', $operador->nombre_comercial)
            ->assertJsonPath('data.user.email', $empresario->email)
            ->assertJsonMissingPath('data.empleados')
            ->assertJsonMissingPath('data.buses')
            ->assertJsonMissingPath('data.operador_rutas');
    }

    public function test_admin_can_search_operators(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $keniaUser = $this->createUser('empresario', 'kenia@example.test');
        $otherUser = $this->createUser('empresario', 'other@example.test');
        $kenia = $this->createOperador($keniaUser);
        $kenia->forceFill([
            'nombre_comercial' => 'Transportes Kenia',
            'representante_legal' => 'Oscar Mauricio',
            'telefono' => '1555-0101',
        ])->save();
        $this->createOperador($otherUser);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/operadores?search=kenia')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('operadores.0.nombre_comercial', 'Transportes Kenia');

        $this->getJson('/api/admin/operadores?search=oscar')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('operadores.0.id', $kenia->id);
    }

    public function test_admin_can_load_operator_detail_lists_on_demand(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $otherEmpresario = $this->createUser('empresario', 'other@example.test');
        $operador = $this->createOperador($empresario);
        $otherOperador = $this->createOperador($otherEmpresario);
        $employeeUser = $this->createUser('validador', 'validator@example.test');
        $ruta = $this->createRuta('R-010');
        $otherRuta = $this->createRuta('R-020');
        $this->createEmployee($operador, $employeeUser);
        $this->createOperadorRuta($operador, $ruta);
        $this->createBus($operador, $ruta);
        $this->createOperadorRuta($otherOperador, $otherRuta);
        $this->createBus($otherOperador, $otherRuta, 'AB-222');

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/operadores/{$operador->id}/empleados")
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('empleados.0.email', 'validator@example.test');

        $this->getJson("/api/admin/operadores/{$operador->id}/buses")
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('buses.0.placa', 'AB-111')
            ->assertJsonPath('buses.0.ruta.ruta', 'R-010');

        $this->getJson("/api/admin/operadores/{$operador->id}/rutas")
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('operador_rutas.0.ruta', 'R-010');

        $this->deleteJson("/api/admin/operadores/{$operador->id}")
            ->assertMethodNotAllowed();
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
            ->assertJsonMissingPath('operador.motivo_desactivacion');

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
            'documento' => '0614-290695-101-3',
            'direccion' => 'San Salvador',
            'estado_id' => Estado::ACTIVO_ID,
            'user_id' => $empresario->id,
            'motivo_desactivacion' => 'No permitido',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'razon_social',
                'representante_legal',
                'nit',
                'estado_id',
                'user_id',
                'motivo_desactivacion',
                'nombre',
                'documento',
                'correo',
            ]);
    }

    public function test_operator_requests_validate_document_format_and_uniqueness(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $tipoPersona = $this->tipoOperador('persona');
        $tipoEmpresa = $this->tipoOperador('empresa');
        $this->createOperador($this->createUser('empresario', 'dui-used@example.test'), dui: '12345678-4');

        Sanctum::actingAs($empresario);

        $this->postJson('/api/operador', [
            'tipo_operador_id' => $tipoPersona->id,
            'nombre_comercial' => 'Persona sin guion',
            'dui' => '123456789',
            'telefono' => '7777-8888',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dui']);

        $this->postJson('/api/operador', [
            'tipo_operador_id' => $tipoPersona->id,
            'nombre_comercial' => 'Persona duplicada',
            'dui' => '12345678-4',
            'telefono' => '7777-8888',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dui']);

        $this->postJson('/api/operador', [
            'tipo_operador_id' => $tipoEmpresa->id,
            'nombre_comercial' => 'Empresa sin guiones',
            'razon_social' => 'Empresa sin guiones S.A.',
            'representante_legal' => 'Maria Lopez',
            'nit' => '06142906951013',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nit']);
    }

    public function test_operator_requests_reject_invalid_checksums_phones_and_incompatible_fields(): void
    {
        $tipoPersona = $this->tipoOperador('persona');
        $tipoEmpresa = $this->tipoOperador('empresa');

        $persona = $this->createUser('empresario', 'invalid-persona@example.test');
        Sanctum::actingAs($persona);

        $this->postJson('/api/operador', [
            'tipo_operador_id' => $tipoPersona->id,
            'nombre_comercial' => 'Persona invalida',
            'dui' => '88888888-6',
            'telefono' => '7777-7777',
            'nit' => '0614-290695-101-0',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dui', 'telefono', 'nit']);

        $empresa = $this->createUser('empresario', 'invalid-empresa@example.test');
        Sanctum::actingAs($empresa);

        $this->postJson('/api/operador', [
            'tipo_operador_id' => $tipoEmpresa->id,
            'nombre_comercial' => 'Empresa invalida',
            'razon_social' => 'Empresa Invalida S.A.',
            'representante_legal' => 'Maria Lopez',
            'nit' => '0614-290695-101-3',
            'telefono' => '5123-4567',
            'dui' => '12345678-4',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nit', 'telefono', 'dui']);
    }

    public function test_operator_update_ignores_current_document_for_unique_validation(): void
    {
        $empresario = $this->createUser('empresario', 'empresario@example.test');
        $operador = $this->createOperador($empresario, dui: '12345678-4');
        $tipoPersona = $this->tipoOperador('persona');

        Sanctum::actingAs($empresario);

        $this->putJson("/api/operador/{$operador->id}", [
            'tipo_operador_id' => $tipoPersona->id,
            'nombre_comercial' => 'Operador actualizado',
            'dui' => '12345678-4',
            'telefono' => '7777-8888',
        ])
            ->assertOk()
            ->assertJsonPath('operador.nombre_comercial', 'Operador actualizado')
            ->assertJsonPath('operador.dui', '12345678-4');
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
        ?string $dui = null,
    ): Operador {
        $tipoPersona = $this->tipoOperador('persona');
        $estado = $this->estado($estadoId, $estadoName);

        return Operador::query()->create([
            'user_id' => $user->id,
            'tipo_operador_id' => $tipoPersona->id,
            'nombre_comercial' => 'Operador '.$user->id,
            'dui' => $dui ?? sprintf('%08d-%d', $user->id, $user->id % 10),
            'telefono' => '2222-3333',
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

    private function createRuta(string $code): Ruta
    {
        return Ruta::query()->create([
            'ruta' => $code,
            'denominacion' => "Ruta {$code}",
            'tarifa' => 1.25,
            'estado_id' => $this->estado(Estado::ACTIVO_ID, 'Activo')->id,
        ]);
    }

    private function createOperadorRuta(Operador $operador, Ruta $ruta): OperadorRuta
    {
        return OperadorRuta::query()->create([
            'operador_id' => $operador->id,
            'ruta_id' => $ruta->id,
            'estado_id' => $this->estado(Estado::ACTIVO_ID, 'Activo')->id,
        ]);
    }

    private function createBus(Operador $operador, Ruta $ruta, string $placa = 'AB-111'): Bus
    {
        $tipoBus = TipoBus::query()->firstOrCreate(['nombre' => 'bus']);

        return Bus::query()->create([
            'operador_id' => $operador->id,
            'ruta_id' => $ruta->id,
            'placa' => $placa,
            'marca' => 'Mercedes-Benz',
            'nombre_unidad' => 'Unidad '.$placa,
            'capacidad' => 50,
            'tipo_bus_id' => $tipoBus->id,
            'estado_id' => $this->estado(Estado::ACTIVO_ID, 'Activo')->id,
        ]);
    }

    private function createEmployee(Operador $operador, User $user): OperadorEmpleado
    {
        return OperadorEmpleado::query()->create([
            'operador_id' => $operador->id,
            'user_id' => $user->id,
            'estado_id' => $this->estado(Estado::ACTIVO_ID, 'Activo')->id,
        ]);
    }
}
