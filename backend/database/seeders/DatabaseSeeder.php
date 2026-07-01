<?php

namespace Database\Seeders;

use App\Models\Dia;
use App\Models\Estado;
use App\Models\Role;
use App\Models\TipoBus;
use App\Models\TipoOperador;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        collect(['administrador', 'empresario', 'vendedor', 'validador'])
            ->each(fn (string $nombre) => Role::query()->firstOrCreate(['nombre' => $nombre]));

        collect([
            ['id' => Estado::ACTIVO_ID, 'nombre' => 'Activo'],
            ['id' => Estado::DESACTIVADO_ID, 'nombre' => 'Desactivado'],
            ['id' => Estado::EMITIDO_ID, 'nombre' => 'Emitido'],
            ['id' => Estado::VALIDADO_ID, 'nombre' => 'Validado'],
            ['id' => Estado::CANCELADO_ID, 'nombre' => 'Cancelado'],
            ['id' => Estado::PROGRAMADO_ID, 'nombre' => 'Programado'],
        ])->each(fn (array $estado) => Estado::query()->updateOrCreate(['id' => $estado['id']], $estado));

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('estados', 'id'), (SELECT MAX(id) FROM estados))");
        }

        collect(['empresa', 'persona'])
            ->each(fn (string $nombre) => TipoOperador::query()->firstOrCreate(['nombre' => $nombre]));

        collect(['bus', 'microbus', 'coaster'])
            ->each(fn (string $nombre) => TipoBus::query()->firstOrCreate(['nombre' => $nombre]));

        $this->call(TipoEnvioSeeder::class);
        $this->call(ProcesamientoEstadoSeeder::class);

        collect([
            ['nombre' => 'Lunes', 'orden' => 1],
            ['nombre' => 'Martes', 'orden' => 2],
            ['nombre' => 'Miércoles', 'orden' => 3],
            ['nombre' => 'Jueves', 'orden' => 4],
            ['nombre' => 'Viernes', 'orden' => 5],
            ['nombre' => 'Sábado', 'orden' => 6],
            ['nombre' => 'Domingo', 'orden' => 7],
        ])->each(fn (array $dia) => Dia::query()->updateOrCreate(['orden' => $dia['orden']], $dia));

        $temporaryPassword = Str::password(length: 14, symbols: false);

        User::query()->updateOrCreate(
            ['email' => env('INITIAL_ADMIN_EMAIL', 'admin@terminal302.local')],
            [
                'name' => env('INITIAL_ADMIN_NAME', 'Administrador Terminal302'),
                'role_id' => Role::query()->where('nombre', 'administrador')->value('id'),
                'estado_id' => Estado::ACTIVO_ID,
                'email_verified_at' => now(),
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
            ],
        );

        $this->command?->warn('Administrador inicial de Terminal302');
        $this->command?->line('Email: '.env('INITIAL_ADMIN_EMAIL', 'admin@terminal302.local'));
        $this->command?->line('Contrasena temporal: '.$temporaryPassword);
        $this->command?->warn('Guarda esta contrasena ahora. No se almacena en texto plano.');
    }
}
