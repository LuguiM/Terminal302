<?php

namespace Tests\Feature;

use App\Models\Estado;
use App\Models\MenuRuta;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MenuRutaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuRutaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_ruta_seeder_creates_standard_menu_tree_without_avisos(): void
    {
        $this->seed(DatabaseSeeder::class);

        $expectedRoutes = [
            'administrador' => [
                '/admin/dashboard' => null,
                '/admin/gestiones/usuarios' => 'Gestiones',
                '/admin/gestiones/operadores' => 'Gestiones',
                '/admin/gestiones/horarios' => 'Gestiones',
                '/admin/catalogos/rutas' => 'Catalogos',
                '/admin/configuracion/plantilla' => 'Configuracion',
            ],
            'empresario' => [
                '/operador/dashboard' => null,
                '/operador/rutas' => null,
                '/operador/unidades' => null,
                '/operador/empleados' => null,
                '/operador/horarios' => null,
            ],
            'vendedor' => [
                '/vendedor/tickets' => null,
                '/vendedor/tickets/entregas' => null,
                '/vendedor/historial' => null,
            ],
            'validador' => [
                '/validador/tickets' => null,
            ],
        ];

        foreach ($expectedRoutes as $roleName => $routes) {
            $role = Role::query()->where('nombre', $roleName)->firstOrFail();

            foreach ($routes as $route => $parentTitle) {
                $child = MenuRuta::query()
                    ->where('role_id', $role->id)
                    ->where('ruta', $route)
                    ->firstOrFail();

                $this->assertTrue((bool) $child->visible);
                $this->assertTrue((bool) $child->requiere_autenticacion);
                $this->assertSame(Estado::ACTIVO_ID, (int) $child->estado_id);
                $this->assertNull($child->base_url);

                $parent = $child->padre()->first();

                if ($parentTitle === null) {
                    $this->assertNull($parent);

                    continue;
                }

                $this->assertNotNull($parent);
                $this->assertSame($parentTitle, $parent->titulo);
                $this->assertSame('', $parent->ruta);
                $this->assertNull($parent->dependencia);
                $this->assertSame($role->id, $parent->role_id);
            }
        }

        $this->assertDatabaseMissing('menu_rutas', [
            'ruta' => '/avisos',
        ]);
    }

    public function test_menu_ruta_seeder_is_idempotent_and_updates_standard_values(): void
    {
        $this->seed(DatabaseSeeder::class);

        $initialCount = MenuRuta::query()->count();
        $adminRoute = MenuRuta::query()->where('ruta', '/admin/gestiones/usuarios')->firstOrFail();
        $adminParent = $adminRoute->padre()->firstOrFail();
        $operatorDashboard = MenuRuta::query()->where('ruta', '/operador/dashboard')->firstOrFail();

        $adminRoute->update([
            'titulo' => 'Usuarios viejo',
            'orden' => '9.90',
            'icono' => 'mdi-alert',
            'visible' => false,
            'requiere_autenticacion' => false,
            'base_url' => 'http://localhost:5173',
            'estado_id' => Estado::DESACTIVADO_ID,
        ]);
        $adminParent->update([
            'orden' => '8.80',
            'icono' => 'mdi-alert',
            'visible' => false,
            'estado_id' => Estado::DESACTIVADO_ID,
        ]);
        $operatorDashboard->update([
            'titulo' => 'Panel viejo',
            'dependencia' => $adminParent->id,
        ]);

        $this->seed(MenuRutaSeeder::class);

        $this->assertSame($initialCount, MenuRuta::query()->count());

        $adminRoute->refresh();
        $adminParent->refresh();
        $operatorDashboard->refresh();

        $this->assertSame('Usuarios', $adminRoute->titulo);
        $this->assertSame('3.10', $adminRoute->orden);
        $this->assertSame('mdi-account-group', $adminRoute->icono);
        $this->assertTrue((bool) $adminRoute->visible);
        $this->assertTrue((bool) $adminRoute->requiere_autenticacion);
        $this->assertNull($adminRoute->base_url);
        $this->assertSame(Estado::ACTIVO_ID, (int) $adminRoute->estado_id);
        $this->assertSame($adminParent->id, $adminRoute->dependencia);

        $this->assertSame('3.00', $adminParent->orden);
        $this->assertSame('mdi-account-cog', $adminParent->icono);
        $this->assertTrue((bool) $adminParent->visible);
        $this->assertSame(Estado::ACTIVO_ID, (int) $adminParent->estado_id);

        $this->assertSame('Dashboard', $operatorDashboard->titulo);
        $this->assertNull($operatorDashboard->dependencia);
    }
}
