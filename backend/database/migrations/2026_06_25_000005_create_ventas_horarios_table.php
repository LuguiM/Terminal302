<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horario_id')->constrained('horarios')->restrictOnDelete();
            $table->date('fecha_operacion');
            $table->boolean('venta_cerrada')->default(false);
            $table->foreignId('cerrada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_cierre')->nullable();
            $table->string('motivo_cierre', 1000)->nullable();
            $table->unsignedInteger('total_tickets_vendidos')->default(0);
            $table->unsignedInteger('total_tickets_sobreventa')->default(0);
            $table->foreignId('estado_id')->constrained('estados')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['horario_id', 'fecha_operacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_horarios');
    }
};
