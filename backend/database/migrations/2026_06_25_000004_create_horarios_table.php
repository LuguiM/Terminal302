<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruta_id')->constrained('rutas')->restrictOnDelete();
            $table->foreignId('operador_id')->constrained('operadores')->restrictOnDelete();
            $table->foreignId('bus_id')->constrained('buses')->restrictOnDelete();
            $table->foreignId('dia_id')->constrained('dias')->restrictOnDelete();
            $table->time('hora_salida');
            $table->boolean('sobreventa_permitida');
            $table->foreignId('estado_id')->constrained('estados')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['ruta_id', 'operador_id', 'bus_id', 'dia_id', 'hora_salida'],
                'horarios_unique_programacion'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios');
    }
};
