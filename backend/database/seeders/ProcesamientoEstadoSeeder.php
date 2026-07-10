<?php

namespace Database\Seeders;

use App\Models\Estado;
use App\Models\ProcesamientoEstado;
use Illuminate\Database\Seeder;

class ProcesamientoEstadoSeeder extends Seeder
{
    public function run(): void
    {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            $this->command?->error('No se encontro el estado requerido: activo.');

            return;
        }

        collect([
            ['nombre' => ProcesamientoEstado::PENDING, 'descripcion' => 'Procesamiento digital pendiente.'],
            ['nombre' => ProcesamientoEstado::PROCESSING, 'descripcion' => 'Procesamiento digital en ejecucion.'],
            ['nombre' => ProcesamientoEstado::COMPLETED, 'descripcion' => 'Procesamiento digital completado.'],
            ['nombre' => ProcesamientoEstado::FAILED, 'descripcion' => 'Procesamiento digital fallido.'],
        ])->each(fn (array $estado): ProcesamientoEstado => ProcesamientoEstado::query()->updateOrCreate(
            ['nombre' => $estado['nombre']],
            [
                'descripcion' => $estado['descripcion'],
                'estado_id' => $activeStatus->id,
            ],
        ));
    }
}
