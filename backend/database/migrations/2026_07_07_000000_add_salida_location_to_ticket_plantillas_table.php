<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_plantillas', function (Blueprint $table): void {
            $table->json('salida_location')->nullable()->after('ruta_location');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_plantillas', function (Blueprint $table): void {
            $table->dropColumn('salida_location');
        });
    }
};
