<?php

namespace Database\Seeders;

use App\Models\Estado;
use App\Models\MenuRuta;
use App\Models\Role;
use Illuminate\Database\Seeder;

class MenuRutaSeeder extends Seeder
{
    public function run(): void
    {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            $this->command?->error('No se encontro el estado requerido: activo.');

            return;
        }

        collect($this->menu())->each(function (array $sections, string $roleName) use ($activeStatus): void {
            $role = Role::query()->where('nombre', $roleName)->first();

            if (! $role) {
                $this->command?->warn("No se encontro el rol requerido para menu_rutas: {$roleName}.");

                return;
            }

            collect($sections)->each(function (array $section) use ($role, $activeStatus): void {
                $parent = $this->upsertMenuRuta(
                    role: $role,
                    attributes: [
                        'titulo' => $section['titulo'],
                        'ruta' => '',
                        'orden' => $section['orden'],
                        'icono' => $section['icono'],
                        'dependencia' => null,
                    ],
                    activeStatusId: $activeStatus->id,
                    lookup: [
                        'titulo' => $section['titulo'],
                        'ruta' => '',
                        'dependencia' => null,
                    ],
                );

                collect($section['dependencias'])->each(function (array $child) use ($role, $parent, $activeStatus): void {
                    $this->upsertMenuRuta(
                        role: $role,
                        attributes: [
                            'titulo' => $child['titulo'],
                            'ruta' => $child['ruta'],
                            'orden' => $child['orden'],
                            'icono' => $child['icono'],
                            'dependencia' => $parent->id,
                        ],
                        activeStatusId: $activeStatus->id,
                        lookup: [
                            'ruta' => $child['ruta'],
                        ],
                    );
                });
            });
        });
    }

    /**
     * @param  array{titulo?: string, ruta?: string, dependencia?: int|null}  $lookup
     * @param  array{titulo: string, ruta: string, orden: string, icono: string, dependencia: int|null}  $attributes
     */
    private function upsertMenuRuta(Role $role, array $attributes, int $activeStatusId, array $lookup): MenuRuta
    {
        $query = MenuRuta::query()->where('role_id', $role->id);

        if (array_key_exists('titulo', $lookup)) {
            $query->where('titulo', $lookup['titulo']);
        }

        if (array_key_exists('ruta', $lookup)) {
            $query->where('ruta', $lookup['ruta']);
        }

        if (array_key_exists('dependencia', $lookup)) {
            $lookup['dependencia'] === null
                ? $query->whereNull('dependencia')
                : $query->where('dependencia', $lookup['dependencia']);
        }

        $menuRuta = $query->first() ?? new MenuRuta([
            'role_id' => $role->id,
        ]);

        $menuRuta->fill([
            ...$attributes,
            'visible' => true,
            'requiere_autenticacion' => true,
            'role_id' => $role->id,
            'base_url' => null,
            'estado_id' => $activeStatusId,
        ]);
        $menuRuta->save();

        return $menuRuta;
    }

    /**
     * @return array<string, array<int, array{
     *     titulo: string,
     *     orden: string,
     *     icono: string,
     *     dependencias: array<int, array{titulo: string, ruta: string, orden: string, icono: string}>
     * }>>
     */
    private function menu(): array
    {
        return [
            'administrador' => [
                [
                    'titulo' => 'Dashboard',
                    'orden' => '1.00',
                    'icono' => 'mdi-monitor-dashboard',
                    'ruta' => '/dashboard',
                    'dependencias' => [],
                ],
                [
                    'titulo' => 'Catalogos',
                    'orden' => '2.00',
                    'icono' => 'mdi-shape',
                    'dependencias' => [
                        ['titulo' => 'Rutas', 'ruta' => '/admin/catalogos/rutas', 'orden' => '2.10', 'icono' => 'mdi-bus-marker'],
                    ],
                ],
                [
                    'titulo' => 'Gestiones',
                    'orden' => '3.00',
                    'icono' => 'mdi-account-cog',
                    'dependencias' => [
                        ['titulo' => 'Usuarios', 'ruta' => '/admin/gestiones/usuarios', 'orden' => '3.10', 'icono' => 'mdi-account-group'],
                        ['titulo' => 'Operadores', 'ruta' => '/admin/gestiones/operadores', 'orden' => '3.20', 'icono' => 'mdi-account-tie-hat-outline'],
                        ['titulo' => 'Horarios', 'ruta' => '/admin/gestiones/horarios', 'orden' => '3.30', 'icono' => 'mdi-calendar-clock'],
                    ],
                ],
                [
                    'titulo' => 'Configuracion',
                    'orden' => '4.00',
                    'icono' => 'mdi-cog',
                    'dependencias' => [
                        ['titulo' => 'Plantilla de tickets', 'ruta' => '/admin/configuracion/plantilla', 'orden' => '4.10', 'icono' => 'mdi-ticket-confirmation'],
                    ],
                ],
            ],
            'empresario' => [
                [
                    'titulo' => 'Dashboard',
                    'orden' => '1.00',
                    'icono' => 'mdi-monitor-dashboard',
                    'ruta' => '/dashboard',
                    'dependencias' => [],
                ],
                [
                    'titulo' => 'Rutas de buses',
                    'orden' => '2.00',
                    'icono' => 'mdi-bus-stop',
                    'ruta' => '/operador/rutas',
                    'dependencias' => [],
                ],
                [
                    'titulo' => 'Unidades de transporte',
                    'orden' => '3.00',
                    'icono' => 'mdi-bus-multiple',
                    'ruta' => '/operador/unidades',
                    'dependencias' => [],
                ],
                [
                    'titulo' => 'Empleados validadores',
                    'orden' => '4.00',
                    'icono' => 'mdi-account-hard-hat',
                    'ruta' => '/operador/empleados',
                    'dependencias' => [],
                ],
                [
                    'titulo' => 'Horarios de rutas',
                    'orden' => '5.00',
                    'icono' => 'mdi-calendar-clock',
                    'ruta' => '/operador/horarios',
                    'dependencias' => [],
                ],
            ],
            'vendedor' => [
                [
                    'titulo' => 'Vender tickets',
                    'orden' => '1.00',
                    'icono' => 'mdi-ticket',
                    'ruta' => '/vendedor/tickets',
                    'dependencias' => [],
                ],
                [
                    'titulo' => 'Estados de envios digitales',
                    'orden' => '2.00',
                    'icono' => 'mdi-email-fast-outline',
                    'ruta' => '/vendedor/tickets/entregas',
                    'dependencias' => [],
                ],
                [
                    'titulo' => 'Historial de ventas',
                    'orden' => '3.00',
                    'icono' => 'mdi-clipboard-text-clock-outline',
                    'ruta' => '/vendedor/historial',
                    'dependencias' => [],
                ],
            ],
            'validador' => [
                [
                    'titulo' => 'Validar tickets',
                    'orden' => '1.00',
                    'icono' => 'mdi-qrcode-scan',
                    'ruta' => '/validador/tickets',
                    'dependencias' => [],
                ],
            ],
        ];
    }
}
