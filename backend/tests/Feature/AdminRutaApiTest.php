<?php

namespace Tests\Feature;

use App\Models\Estado;
use App\Models\Role;
use App\Models\Ruta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRutaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_routes(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $this->createRuta('302', 'Usulutan - San Salvador');
        $this->createRuta('312', 'San Miguel - San Salvador');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/rutas')
            ->assertOk()
            ->assertJsonStructure([
                'rutas' => [
                    [
                        'id',
                        'ruta',
                        'denominacion',
                        'tarifa',
                        'estado',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'pagination' => [
                    'page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ])
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('pagination.per_page', 15);
    }

    public function test_admin_can_create_route_and_active_status_is_assigned(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $active = $this->estado(Estado::ACTIVO_ID, 'Activo');

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/rutas', [
            'ruta' => '301-A',
            'denominacion' => 'Usulutan - San Salvador',
            'tarifa' => 1.50,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Ruta creada correctamente.')
            ->assertJsonPath('ruta.ruta', '301-A')
            ->assertJsonPath('ruta.estado.id', $active->id);

        $this->assertDatabaseHas('rutas', [
            'ruta' => '301-A',
            'estado_id' => $active->id,
        ]);
    }

    public function test_admin_cannot_create_duplicate_route_or_invalid_fare_or_estado_id(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/rutas', [
            'ruta' => $ruta->ruta,
            'denominacion' => 'Duplicada',
            'tarifa' => 1.25,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ruta']);

        $this->postJson('/api/admin/rutas', [
            'ruta' => '303',
            'denominacion' => 'Tarifa invalida',
            'tarifa' => -1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tarifa']);

        $this->postJson('/api/admin/rutas', [
            'ruta' => '304',
            'denominacion' => 'Con estado',
            'tarifa' => 1.25,
            'estado_id' => Estado::ACTIVO_ID,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estado_id']);
    }

    public function test_admin_can_update_route_and_unique_rule_ignores_current_route(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $otherRuta = $this->createRuta('312', 'San Miguel - San Salvador');

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/rutas/{$ruta->id}", [
            'ruta' => $ruta->ruta,
            'denominacion' => 'Usulutan - Terminal Nuevo Amanecer',
            'tarifa' => 1.75,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Ruta actualizada correctamente.')
            ->assertJsonPath('ruta.ruta', $ruta->ruta)
            ->assertJsonPath('ruta.denominacion', 'Usulutan - Terminal Nuevo Amanecer')
            ->assertJsonPath('ruta.tarifa', '1.75');

        $this->putJson("/api/admin/rutas/{$ruta->id}", [
            'ruta' => $otherRuta->ruta,
            'denominacion' => 'Duplicada',
            'tarifa' => 1.50,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ruta']);

        $this->putJson("/api/admin/rutas/{$ruta->id}", [
            'ruta' => '305',
            'denominacion' => 'Con estado',
            'tarifa' => 1.50,
            'estado_id' => Estado::DESACTIVADO_ID,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estado_id']);
    }

    public function test_admin_can_toggle_route_status_without_body(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');
        $this->estado(Estado::DESACTIVADO_ID, 'Desactivado');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/rutas/{$ruta->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('message', 'Estado de la ruta actualizado correctamente.')
            ->assertJsonPath('ruta.estado.nombre', 'Desactivado');

        $this->patchJson("/api/admin/rutas/{$ruta->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('ruta.estado.nombre', 'Activo');
    }

    public function test_admin_can_physically_delete_route(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/rutas/{$ruta->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Ruta eliminada correctamente.');

        $this->assertDatabaseMissing('rutas', [
            'id' => $ruta->id,
        ]);
    }

    public function test_route_mutation_endpoints_return_friendly_message_when_route_does_not_exist(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');

        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/rutas/999999', [
            'ruta' => '302',
            'denominacion' => 'Usulutan - San Salvador',
            'tarifa' => 1.50,
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'La ruta solicitada no existe.');

        $this->patchJson('/api/admin/rutas/999999/toggle-status')
            ->assertNotFound()
            ->assertJsonPath('message', 'La ruta solicitada no existe.');

        $this->deleteJson('/api/admin/rutas/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'La ruta solicitada no existe.');
    }

    public function test_admin_route_show_endpoint_is_not_registered(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $ruta = $this->createRuta('302', 'Usulutan - San Salvador');

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/rutas/{$ruta->id}")
            ->assertMethodNotAllowed();
    }

    public function test_non_admin_guest_and_initial_password_user_cannot_manage_routes(): void
    {
        $seller = $this->createUser('vendedor', 'seller@example.test');
        $adminMustChangePassword = $this->createUser(
            roleName: 'administrador',
            email: 'must-change@example.test',
            mustChangePassword: true,
        );

        $this->getJson('/api/admin/rutas')
            ->assertUnauthorized();

        Sanctum::actingAs($seller);

        $this->getJson('/api/admin/rutas')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permisos para acceder a este recurso.');

        Sanctum::actingAs($adminMustChangePassword);

        $this->getJson('/api/admin/rutas')
            ->assertForbidden()
            ->assertJsonPath('message', 'Debe cambiar la contrasena inicial antes de continuar.');
    }

    private function createUser(
        string $roleName,
        string $email,
        bool $mustChangePassword = false,
    ): User {
        $role = Role::query()->firstOrCreate(['nombre' => $roleName]);
        $estado = $this->estado(Estado::ACTIVO_ID, 'Activo');

        return User::query()->create([
            'role_id' => $role->id,
            'estado_id' => $estado->id,
            'name' => 'Usuario '.str_replace('@example.test', '', $email),
            'email' => $email,
            'password' => Hash::make('Temporal123'),
            'must_change_password' => $mustChangePassword,
        ]);
    }

    private function createRuta(string $ruta, string $denominacion): Ruta
    {
        $estado = $this->estado(Estado::ACTIVO_ID, 'Activo');

        return Ruta::query()->create([
            'ruta' => $ruta,
            'denominacion' => $denominacion,
            'tarifa' => 1.50,
            'estado_id' => $estado->id,
        ]);
    }

    private function estado(int $id, string $nombre): Estado
    {
        return Estado::query()->firstOrCreate(['id' => $id], ['nombre' => $nombre]);
    }
}
