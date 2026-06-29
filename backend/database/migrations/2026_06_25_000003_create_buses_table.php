<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operador_id')->constrained('operadores')->restrictOnDelete();
            $table->foreignId('ruta_id')->constrained('rutas')->restrictOnDelete();
            $table->string('placa', 50)->unique();
            $table->string('marca', 100);
            $table->string('nombre_unidad', 100)->nullable();
            $table->unsignedInteger('capacidad');
            $table->foreignId('tipo_bus_id')->constrained('tipo_buses')->restrictOnDelete();
            $table->foreignId('estado_id')->constrained('estados')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buses');
    }
};
