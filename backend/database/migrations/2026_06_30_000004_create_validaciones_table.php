<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->restrictOnDelete();
            $table->foreignId('validador_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('fecha_validacion');
            $table->string('resultado', 50);
            $table->string('observacion', 1000)->nullable();
            $table->timestamps();

            $table->unique('ticket_id');
            $table->index('validador_id');
            $table->index('fecha_validacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validaciones');
    }
};
