<?php

namespace Database\Seeders;

use App\Models\Estado;
use App\Models\TipoEnvio;
use Illuminate\Database\Seeder;

class TipoEnvioSeeder extends Seeder
{
    public function run(): void
    {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            $this->command?->error('No se encontro el estado requerido: activo.');

            return;
        }

        collect([
            ['nombre' => TipoEnvio::IMPRESO, 'descripcion' => 'Entrega impresa del ticket.'],
            ['nombre' => TipoEnvio::DIGITAL, 'descripcion' => 'Entrega digital del ticket.'],
        ])->each(fn (array $tipoEnvio): TipoEnvio => TipoEnvio::query()->updateOrCreate(
            ['nombre' => $tipoEnvio['nombre']],
            [
                'descripcion' => $tipoEnvio['descripcion'],
                'estado_id' => $activeStatus->id,
            ],
        ));
    }
}
