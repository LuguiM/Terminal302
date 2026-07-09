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
                '/admin/gestiones/usuarios' => 'Gestiones',
                '/admin/gestiones/operadores' => 'Gestiones',
                '/admin/gestiones/horarios' => 'Gestiones',
                '/admin/catalogos/rutas' => 'Catalogos',
                '/admin/configuracion/plantilla' => 'Configuracion',
            ],
            'empresario' => [
                '/operador/rutas' => 'Operacion',
                '/operador/unidades' => 'Operacion',
                '/operador/empleados' => 'Operacion',
                '/operador/horarios' => 'Operacion',
            ],
            'vendedor' => [
                '/vendedor/tickets' => 'Ventas',
                '/vendedor/tickets/entregas' => 'Ventas',
                '/vendedor/historial' => 'Ventas',
            ],
            'validador' => [
                '/validador/tickets' => 'Validacion',
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
        $sellerRoute = MenuRuta::query()->where('ruta', '/vendedor/tickets')->firstOrFail();
        $sellerParent = $sellerRoute->padre()->firstOrFail();

        $sellerRoute->update([
            'titulo' => 'Tickets viejo',
            'orden' => '9.90',
            'icono' => 'mdi-alert',
            'visible' => false,
            'requiere_autenticacion' => false,
            'base_url' => 'http://localhost:5173',
            'estado_id' => Estado::DESACTIVADO_ID,
        ]);
        $sellerParent->update([
            'orden' => '8.80',
            'icono' => 'mdi-alert',
            'visible' => false,
            'estado_id' => Estado::DESACTIVADO_ID,
        ]);

        $this->seed(MenuRutaSeeder::class);

        $this->assertSame($initialCount, MenuRuta::query()->count());

        $sellerRoute->refresh();
        $sellerParent->refresh();

        $this->assertSame('Vender tickets', $sellerRoute->titulo);
        $this->assertSame('1.10', $sellerRoute->orden);
        $this->assertSame('mdi-ticket-plus', $sellerRoute->icono);
        $this->assertTrue((bool) $sellerRoute->visible);
        $this->assertTrue((bool) $sellerRoute->requiere_autenticacion);
        $this->assertNull($sellerRoute->base_url);
        $this->assertSame(Estado::ACTIVO_ID, (int) $sellerRoute->estado_id);
        $this->assertSame($sellerParent->id, $sellerRoute->dependencia);

        $this->assertSame('1.00', $sellerParent->orden);
        $this->assertSame('mdi-ticket', $sellerParent->icono);
        $this->assertTrue((bool) $sellerParent->visible);
        $this->assertSame(Estado::ACTIVO_ID, (int) $sellerParent->estado_id);
    }
}
