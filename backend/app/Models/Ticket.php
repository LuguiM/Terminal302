<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = [
        'venta_horario_id',
        'codigo_ticket',
        'vendedor_id',
        'correo_destino',
        'telefono_destino',
        'numero_asiento',
        'es_sobreventa',
        'tipo_envio_id',
        'estado_id',
        'qr_path',
        'ticket_plantilla_id',
        'ticket_image_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero_asiento' => 'integer',
            'es_sobreventa' => 'boolean',
        ];
    }

    public function ventaHorario(): BelongsTo
    {
        return $this->belongsTo(VentaHorario::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }

    public function tipoEnvio(): BelongsTo
    {
        return $this->belongsTo(TipoEnvio::class);
    }

    public function ticketPlantilla(): BelongsTo
    {
        return $this->belongsTo(TicketPlantilla::class);
    }
}
