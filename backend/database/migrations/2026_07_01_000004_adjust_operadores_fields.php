<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operadores', function (Blueprint $table) {
            $table->string('nombre_comercial')->nullable()->after('tipo_operador_id');
            $table->string('telefono_opcional', 50)->nullable()->after('telefono');
            $table->string('correo_administrativo')->nullable()->after('telefono_opcional');
            $table->string('nit', 17)->nullable()->after('correo_administrativo');
            $table->string('dui', 10)->nullable()->after('nit');
        });

        DB::table('operadores')
            ->orderBy('id')
            ->select(['id', 'nombre', 'correo', 'documento'])
            ->chunk(100, function ($operadores): void {
                foreach ($operadores as $operador) {
                    $documento = preg_replace('/\D/', '', (string) $operador->documento);

                    DB::table('operadores')
                        ->where('id', $operador->id)
                        ->update([
                            'nombre_comercial' => $operador->nombre,
                            'correo_administrativo' => $operador->correo,
                            'nit' => strlen($documento) === 14 ? $this->formatNit($documento) : null,
                            'dui' => strlen($documento) === 9 ? $this->formatDui($documento) : null,
                        ]);
                }
            });

        DB::statement('ALTER TABLE operadores ALTER COLUMN nombre_comercial SET NOT NULL');
        DB::statement('ALTER TABLE operadores ALTER COLUMN telefono DROP NOT NULL');
        DB::statement('ALTER TABLE operadores ALTER COLUMN direccion DROP NOT NULL');

        Schema::table('operadores', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'documento', 'correo']);
            $table->unique('nit');
            $table->unique('dui');
        });
    }

    public function down(): void
    {
        Schema::table('operadores', function (Blueprint $table) {
            $table->string('nombre')->nullable()->after('tipo_operador_id');
            $table->string('documento')->nullable()->after('representante_legal');
            $table->string('correo')->nullable()->after('telefono');
        });

        DB::table('operadores')
            ->orderBy('id')
            ->select(['id', 'nombre_comercial', 'correo_administrativo', 'nit', 'dui'])
            ->chunk(100, function ($operadores): void {
                foreach ($operadores as $operador) {
                    DB::table('operadores')
                        ->where('id', $operador->id)
                        ->update([
                            'nombre' => $operador->nombre_comercial,
                            'correo' => $operador->correo_administrativo ?? 'sin-correo-'.$operador->id.'@terminal302.local',
                            'documento' => $operador->nit ?? $operador->dui,
                        ]);
                }
            });

        DB::table('operadores')->whereNull('telefono')->update(['telefono' => '']);
        DB::table('operadores')->whereNull('direccion')->update(['direccion' => '']);

        DB::statement('ALTER TABLE operadores ALTER COLUMN nombre SET NOT NULL');
        DB::statement('ALTER TABLE operadores ALTER COLUMN correo SET NOT NULL');
        DB::statement('ALTER TABLE operadores ALTER COLUMN telefono SET NOT NULL');
        DB::statement('ALTER TABLE operadores ALTER COLUMN direccion SET NOT NULL');

        Schema::table('operadores', function (Blueprint $table) {
            $table->dropUnique(['nit']);
            $table->dropUnique(['dui']);
            $table->dropColumn([
                'nombre_comercial',
                'telefono_opcional',
                'correo_administrativo',
                'nit',
                'dui',
            ]);
        });
    }

    private function formatDui(string $digits): string
    {
        return substr($digits, 0, 8).'-'.substr($digits, 8, 1);
    }

    private function formatNit(string $digits): string
    {
        return substr($digits, 0, 4).'-'.substr($digits, 4, 6).'-'.substr($digits, 10, 3).'-'.substr($digits, 13, 1);
    }
};
