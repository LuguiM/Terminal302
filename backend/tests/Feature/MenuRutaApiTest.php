<?php

namespace Tests\Feature;

use App\Models\Estado;
use App\Models\MenuRuta;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MenuRutaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_menu_routes_paginated_with_filters(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $adminRole = $admin->role;
        $sellerRole = Role::query()->firstOrCreate(['nombre' => 'vendedor']);
        $parent = $this->createMenuRuta($adminRole, 'Administracion', '', '1.00');
        $child = $this->createMenuRuta($adminRole, 'Usuarios', '/admin/usuarios', '1.10', dependencia: $parent->id);
        $this->createMenuRuta($sellerRole, 'Ventas', '/vendedor/ventas', '1.00');

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/menu-rutas?role_id={$adminRole->id}&estado_id=".Estado::ACTIVO_ID."&dependencia={$parent->id}&visible=1&search=usuarios")
            ->assertOk()
            ->assertJsonStructure([
                'menu_rutas' => [
                    [
                        'id',
                        'titulo',
                        'ruta',
                        'orden',
                        'icono',
                        'visible',
                        'requiere_autenticacion',
                        'dependencia',
                        'role_id',
                        'base_url',
                        'estado',
                        'role',
                        'dependencias',
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
            ->assertJsonPath('menu_rutas.0.id', $child->id);

        $this->getJson("/api/admin/menu-rutas?role_id={$adminRole->id}&estado_id=".Estado::ACTIVO_ID)
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('menu_rutas.0.id', $parent->id)
            ->assertJsonPath('menu_rutas.0.dependencias.0.id', $child->id);
    }

    public function test_admin_can_create_update_and_toggle_menu_route(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $adminRole = $admin->role;

        Sanctum::actingAs($admin);

        $parentResponse = $this->postJson('/api/admin/menu-rutas', [
            'titulo' => 'Administracion',
            'ruta' => '',
            'orden' => 1,
            'icono' => 'mdi-cog',
            'role_id' => $adminRole->id,
            'base_url' => 'http://localhost:8083',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Ruta de menu creada correctamente.')
            ->assertJsonPath('menu_ruta.estado.nombre', 'Activo')
            ->assertJsonPath('menu_ruta.visible', true)
            ->assertJsonPath('menu_ruta.requiere_autenticacion', true);

        $parentId = $parentResponse->json('menu_ruta.id');

        $this->putJson("/api/admin/menu-rutas/{$parentId}", [
            'titulo' => 'Administracion general',
            'ruta' => '',
            'orden' => 2,
            'visible' => false,
            'role_id' => $adminRole->id,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Ruta de menu actualizada correctamente.')
            ->assertJsonPath('menu_ruta.titulo', 'Administracion general')
            ->assertJsonPath('menu_ruta.visible', false);

        $this->patchJson("/api/admin/menu-rutas/{$parentId}/toggle-status")
            ->assertOk()
            ->assertJsonPath('message', 'Estado de la ruta de menu actualizado correctamente.')
            ->assertJsonPath('menu_ruta.estado.nombre', 'Desactivado');

        $this->assertDatabaseHas('menu_rutas', [
            'id' => $parentId,
            'estado_id' => Estado::DESACTIVADO_ID,
        ]);
    }

    public function test_admin_menu_route_business_validation_errors_are_clear(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $adminRole = $admin->role;
        $sellerRole = Role::query()->firstOrCreate(['nombre' => 'vendedor']);
        $parent = $this->createMenuRuta($adminRole, 'Admin', '', '1.00');
        $sellerParent = $this->createMenuRuta($sellerRole, 'Ventas', '', '1.00');
        $child = $this->createMenuRuta($adminRole, 'Usuarios', '/usuarios', '1.10', dependencia: $parent->id);
        $grandChild = $this->createMenuRuta($adminRole, 'Editar usuarios', '/usuarios/editar', '1.20', dependencia: $child->id);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/menu-rutas', [
            'titulo' => 'Usuarios repetido',
            'ruta' => '/usuarios',
            'orden' => 1.30,
            'dependencia' => $parent->id,
            'role_id' => $adminRole->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La ruta ya existe para ese rol y dependencia.');

        $this->postJson('/api/admin/menu-rutas', [
            'titulo' => 'Gestiones',
            'ruta' => '',
            'orden' => 3,
            'icono' => '',
            'visible' => true,
            'requiere_autenticacion' => true,
            'dependencia' => null,
            'role_id' => $adminRole->id,
            'base_url' => 'http://localhost:5173',
        ])
            ->assertCreated()
            ->assertJsonPath('menu_ruta.titulo', 'Gestiones');

        $this->postJson('/api/admin/menu-rutas', [
            'titulo' => 'Gestiones',
            'ruta' => '',
            'orden' => 4,
            'dependencia' => null,
            'role_id' => $adminRole->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El titulo ya existe para ese rol y dependencia.');

        $this->postJson('/api/admin/menu-rutas', [
            'titulo' => 'Hijo cruzado',
            'ruta' => '/cruzado',
            'orden' => 1.40,
            'dependencia' => $sellerParent->id,
            'role_id' => $adminRole->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La dependencia pertenece a otro rol.');

        $this->putJson("/api/admin/menu-rutas/{$child->id}", [
            'dependencia' => $child->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Una ruta de menu no puede depender de si misma.');

        $this->putJson("/api/admin/menu-rutas/{$parent->id}", [
            'dependencia' => $grandChild->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Se detecto un ciclo de dependencia en las rutas de menu.');

        $this->postJson('/api/admin/menu-rutas', [
            'titulo' => 'URL mala',
            'ruta' => '/mala',
            'orden' => 9,
            'role_id' => $adminRole->id,
            'base_url' => 'no-url',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['base_url']);
    }

    public function test_authenticated_user_receives_only_active_visible_menu_tree_for_own_role(): void
    {
        $seller = $this->createUser('vendedor', 'seller@example.test');
        $adminRole = Role::query()->firstOrCreate(['nombre' => 'administrador']);
        $sellerRole = $seller->role;
        $parent = $this->createMenuRuta($sellerRole, 'Ventas', '', '2.00', icono: 'mdi-ticket');
        $child = $this->createMenuRuta($sellerRole, 'Vender tickets', '/vendedor/tickets', '1.00', dependencia: $parent->id);
        $this->createMenuRuta($sellerRole, 'Oculta', '/oculta', '3.00', visible: false);
        $this->createMenuRuta($sellerRole, 'Inactiva', '/inactiva', '4.00', estadoId: Estado::DESACTIVADO_ID);
        $this->createMenuRuta($adminRole, 'Usuarios', '/admin/usuarios', '1.00');

        Sanctum::actingAs($seller);

        $this->getJson('/api/me/menu-rutas')
            ->assertOk()
            ->assertJsonPath('menu_rutas.0.id', $parent->id)
            ->assertJsonPath('menu_rutas.0.titulo', 'Ventas')
            ->assertJsonPath('menu_rutas.0.dependencias.0.id', $child->id)
            ->assertJsonPath('menu_rutas.0.dependencias.0.ruta', '/vendedor/tickets')
            ->assertJsonMissing(['titulo' => 'Oculta'])
            ->assertJsonMissing(['titulo' => 'Inactiva'])
            ->assertJsonMissing(['ruta' => '/admin/usuarios']);
    }

    public function test_menu_route_endpoints_are_protected_and_no_show_or_delete_is_registered(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $seller = $this->createUser('vendedor', 'seller@example.test');
        $menuRuta = $this->createMenuRuta($admin->role, 'Admin', '', '1.00');

        $this->getJson('/api/me/menu-rutas')
            ->assertUnauthorized();

        $this->getJson('/api/admin/menu-rutas')
            ->assertUnauthorized();

        Sanctum::actingAs($seller);

        $this->getJson('/api/admin/menu-rutas')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permisos para acceder a este recurso.');

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/menu-rutas/{$menuRuta->id}")
            ->assertStatus(405);

        $this->deleteJson("/api/admin/menu-rutas/{$menuRuta->id}")
            ->assertStatus(405);
    }

    private function createMenuRuta(
        Role $role,
        string $titulo,
        string $ruta,
        string $orden,
        ?int $dependencia = null,
        bool $visible = true,
        int $estadoId = Estado::ACTIVO_ID,
        ?string $icono = null,
    ): MenuRuta {
        $this->estado($estadoId, $estadoId === Estado::ACTIVO_ID ? 'Activo' : 'Desactivado');

        return MenuRuta::query()->create([
            'titulo' => $titulo,
            'ruta' => $ruta,
            'orden' => $orden,
            'icono' => $icono,
            'visible' => $visible,
            'requiere_autenticacion' => true,
            'dependencia' => $dependencia,
            'role_id' => $role->id,
            'base_url' => 'http://localhost:8083',
            'estado_id' => $estadoId,
        ]);
    }

    private function createUser(string $roleName, string $email): User
    {
        $role = Role::query()->firstOrCreate(['nombre' => $roleName]);
        $estado = $this->estado(Estado::ACTIVO_ID, 'Activo');
        $this->estado(Estado::DESACTIVADO_ID, 'Desactivado');

        return User::query()->create([
            'role_id' => $role->id,
            'estado_id' => $estado->id,
            'name' => 'Usuario '.str_replace('@example.test', '', $email),
            'email' => $email,
            'password' => Hash::make('Temporal123'),
            'must_change_password' => false,
        ]);
    }

    private function estado(int $id, string $nombre): Estado
    {
        return Estado::query()->firstOrCreate(['id' => $id], ['nombre' => $nombre]);
    }
}
