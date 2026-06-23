<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_operadores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::create('tipo_buses', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::create('dias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->unsignedTinyInteger('orden')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dias');
        Schema::dropIfExists('tipo_buses');
        Schema::dropIfExists('tipo_operadores');
    }
};
