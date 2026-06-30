<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_plantillas', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('image_path');
            $table->json('qr_location')->nullable();
            $table->json('precio_location')->nullable();
            $table->json('fecha_hora_location')->nullable();
            $table->json('asiento_location')->nullable();
            $table->json('codigo_ticket_location')->nullable();
            $table->json('ruta_location')->nullable();
            $table->json('operador_location')->nullable();
            $table->foreignId('estado_id')->constrained('estados')->restrictOnDelete();
            $table->boolean('es_predeterminada')->default(false);
            $table->timestamps();

            $table->index('estado_id');
            $table->index('es_predeterminada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_plantillas');
    }
};
