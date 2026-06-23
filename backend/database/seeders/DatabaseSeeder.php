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

        collect(['activo', 'inactivo', 'emitido', 'validado', 'cancelado', 'programado'])
            ->each(fn (string $nombre) => Estado::query()->firstOrCreate(['nombre' => $nombre]));

        collect(['empresa', 'persona'])
            ->each(fn (string $nombre) => TipoOperador::query()->firstOrCreate(['nombre' => $nombre]));

        collect(['bus', 'microbus', 'coaster'])
            ->each(fn (string $nombre) => TipoBus::query()->firstOrCreate(['nombre' => $nombre]));

        collect([
            ['nombre' => 'lunes', 'orden' => 1],
            ['nombre' => 'martes', 'orden' => 2],
            ['nombre' => 'miercoles', 'orden' => 3],
            ['nombre' => 'jueves', 'orden' => 4],
            ['nombre' => 'viernes', 'orden' => 5],
            ['nombre' => 'sabado', 'orden' => 6],
            ['nombre' => 'domingo', 'orden' => 7],
        ])->each(fn (array $dia) => Dia::query()->updateOrCreate(['orden' => $dia['orden']], $dia));

        $temporaryPassword = Str::password(length: 14, symbols: false);

        User::query()->updateOrCreate(
            ['email' => env('INITIAL_ADMIN_EMAIL', 'admin@terminal302.local')],
            [
                'name' => env('INITIAL_ADMIN_NAME', 'Administrador Terminal302'),
                'role_id' => Role::query()->where('nombre', 'administrador')->value('id'),
                'estado_id' => Estado::query()->where('nombre', 'activo')->value('id'),
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
