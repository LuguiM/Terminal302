<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procesamiento_estados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('descripcion')->nullable();
            $table->foreignId('estado_id')->constrained('estados')->restrictOnDelete();
            $table->timestamps();

            $table->index('estado_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procesamiento_estados');
    }
};
