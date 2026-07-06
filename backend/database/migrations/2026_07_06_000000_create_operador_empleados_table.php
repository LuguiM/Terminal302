<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operador_empleados', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operador_id')->constrained('operadores')->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('estado_id')->constrained('estados')->restrictOnDelete();
            $table->text('motivo_desactivacion')->nullable();
            $table->timestamps();

            $table->index('operador_id');
            $table->index('estado_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operador_empleados');
    }
};
