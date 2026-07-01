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
            if (! Schema::hasColumn('tickets', 'procesamiento_estado_id')) {
                $table->foreignId('procesamiento_estado_id')
                    ->nullable()
                    ->after('ticket_image_path')
                    ->constrained('procesamiento_estados')
                    ->restrictOnDelete();
            }
        });

        $this->ensureInitialProcessingStatesWhenPossible();
        $this->migrateExistingProcessingStatuses();
        $this->dropProcessingStatusIndexIfPresent();

        Schema::table('tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('tickets', 'processing_status')) {
                $table->dropColumn('processing_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'processing_status')) {
                $table->string('processing_status', 20)->nullable()->after('ticket_image_path');
                $table->index('processing_status');
            }
        });

        $this->restoreProcessingStatusWhenPossible();

        Schema::table('tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('tickets', 'procesamiento_estado_id')) {
                $table->dropConstrainedForeignId('procesamiento_estado_id');
            }
        });
    }

    private function ensureInitialProcessingStatesWhenPossible(): void
    {
        $activeStatusId = Estado::activo()?->id;

        if (! $activeStatusId) {
            return;
        }

        collect([
            ['nombre' => 'pending', 'descripcion' => 'Procesamiento digital pendiente.'],
            ['nombre' => 'processing', 'descripcion' => 'Procesamiento digital en ejecucion.'],
            ['nombre' => 'completed', 'descripcion' => 'Procesamiento digital completado.'],
            ['nombre' => 'failed', 'descripcion' => 'Procesamiento digital fallido.'],
        ])->each(fn (array $estado): bool => DB::table('procesamiento_estados')->updateOrInsert(
            ['nombre' => $estado['nombre']],
            [
                'descripcion' => $estado['descripcion'],
                'estado_id' => $activeStatusId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ));
    }

    private function migrateExistingProcessingStatuses(): void
    {
        if (! Schema::hasColumn('tickets', 'processing_status') || ! Schema::hasColumn('tickets', 'procesamiento_estado_id')) {
            return;
        }

        foreach (['pending', 'processing', 'completed', 'failed'] as $nombre) {
            $procesamientoEstadoId = DB::table('procesamiento_estados')
                ->whereRaw('LOWER(nombre) = ?', [$nombre])
                ->value('id');

            if (! $procesamientoEstadoId) {
                continue;
            }

            DB::table('tickets')
                ->whereRaw('LOWER(processing_status) = ?', [$nombre])
                ->update(['procesamiento_estado_id' => $procesamientoEstadoId]);
        }
    }

    private function restoreProcessingStatusWhenPossible(): void
    {
        if (! Schema::hasColumn('tickets', 'processing_status') || ! Schema::hasColumn('tickets', 'procesamiento_estado_id')) {
            return;
        }

        DB::table('tickets')
            ->join('procesamiento_estados', 'tickets.procesamiento_estado_id', '=', 'procesamiento_estados.id')
            ->update(['processing_status' => DB::raw('procesamiento_estados.nombre')]);
    }

    private function dropProcessingStatusIndexIfPresent(): void
    {
        try {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->dropIndex(['processing_status']);
            });
        } catch (\Throwable) {
            //
        }
    }
};
