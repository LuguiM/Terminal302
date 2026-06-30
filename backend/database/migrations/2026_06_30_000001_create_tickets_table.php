<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venta_horario_id')->constrained('ventas_horarios')->restrictOnDelete();
            $table->string('codigo_ticket')->unique();
            $table->foreignId('vendedor_id')->constrained('users')->restrictOnDelete();
            $table->string('correo_destino')->nullable();
            $table->string('telefono_destino')->nullable();
            $table->unsignedInteger('numero_asiento')->nullable();
            $table->boolean('es_sobreventa')->default(false);
            $table->string('tipo_entrega', 20);
            $table->foreignId('estado_id')->constrained('estados')->restrictOnDelete();
            $table->string('qr_path')->nullable();
            $table->foreignId('ticket_plantilla_id')->nullable()->constrained('ticket_plantillas')->nullOnDelete();
            $table->string('ticket_image_path')->nullable();
            $table->timestamps();

            $table->index('venta_horario_id');
            $table->index('vendedor_id');
            $table->index('estado_id');
            $table->index('tipo_entrega');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
