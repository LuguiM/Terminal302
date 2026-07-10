<?php

use App\Models\Estado;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'tipo_envio_id')) {
                $table->foreignId('tipo_envio_id')
                    ->nullable()
                    ->after('es_sobreventa')
                    ->constrained('tipo_envios')
                    ->restrictOnDelete();
            }
        });

        $this->ensureInitialTypesWhenPossible();
        $this->migrateExistingDeliveryTypes();

        if (Schema::hasColumn('tickets', 'tipo_envio_id') && $this->ticketsWithoutTipoEnvio() === 0) {
            DB::statement('ALTER TABLE tickets ALTER COLUMN tipo_envio_id SET NOT NULL');
        }

        Schema::table('tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('tickets', 'tipo_entrega')) {
                $table->dropColumn('tipo_entrega');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'tipo_entrega')) {
                $table->string('tipo_entrega', 20)->nullable()->after('es_sobreventa');
            }
        });

        $this->restoreTipoEntregaWhenPossible();

        Schema::table('tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('tickets', 'tipo_envio_id')) {
                $table->dropConstrainedForeignId('tipo_envio_id');
            }
        });
    }

    private function ensureInitialTypesWhenPossible(): void
    {
        $activeStatusId = Estado::activo()?->id;

        if (! $activeStatusId) {
            return;
        }

        foreach (['impreso', 'digital'] as $nombre) {
            DB::table('tipo_envios')->updateOrInsert(
                ['nombre' => $nombre],
                [
                    'descripcion' => ucfirst($nombre),
                    'estado_id' => $activeStatusId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function migrateExistingDeliveryTypes(): void
    {
        if (! Schema::hasColumn('tickets', 'tipo_entrega')) {
            return;
        }

        foreach (['impreso', 'digital'] as $nombre) {
            $tipoEnvioId = DB::table('tipo_envios')
                ->whereRaw('LOWER(nombre) = ?', [$nombre])
                ->value('id');

            if (! $tipoEnvioId) {
                continue;
            }

            DB::table('tickets')
                ->whereRaw('LOWER(tipo_entrega) = ?', [$nombre])
                ->update(['tipo_envio_id' => $tipoEnvioId]);
        }
    }

    private function restoreTipoEntregaWhenPossible(): void
    {
        if (! Schema::hasColumn('tickets', 'tipo_entrega') || ! Schema::hasColumn('tickets', 'tipo_envio_id')) {
            return;
        }

        DB::table('tickets')
            ->join('tipo_envios', 'tickets.tipo_envio_id', '=', 'tipo_envios.id')
            ->update(['tipo_entrega' => DB::raw('tipo_envios.nombre')]);
    }

    private function ticketsWithoutTipoEnvio(): int
    {
        return DB::table('tickets')
            ->whereNull('tipo_envio_id')
            ->count();
    }
};
