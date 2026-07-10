<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_rutas', function (Blueprint $table): void {
            $table->id();
            $table->string('titulo');
            $table->string('ruta')->default('');
            $table->decimal('orden', 8, 2);
            $table->string('icono')->nullable();
            $table->boolean('visible')->default(true);
            $table->boolean('requiere_autenticacion')->default(true);
            $table->foreignId('dependencia')->nullable()->constrained('menu_rutas')->nullOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->string('base_url')->nullable();
            $table->foreignId('estado_id')->constrained('estados')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'ruta', 'dependencia']);
            $table->index('orden');
            $table->index('visible');
            $table->index('estado_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_rutas');
    }
};
