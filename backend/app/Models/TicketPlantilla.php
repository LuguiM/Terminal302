<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketPlantilla extends Model
{
    protected $table = 'ticket_plantillas';

    protected $fillable = [
        'nombre',
        'image_path',
        'qr_location',
        'precio_location',
        'fecha_hora_location',
        'asiento_location',
        'codigo_ticket_location',
        'ruta_location',
        'salida_location',
        'operador_location',
        'estado_id',
        'es_predeterminada',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qr_location' => 'array',
            'precio_location' => 'array',
            'fecha_hora_location' => 'array',
            'asiento_location' => 'array',
            'codigo_ticket_location' => 'array',
            'ruta_location' => 'array',
            'salida_location' => 'array',
            'operador_location' => 'array',
            'es_predeterminada' => 'boolean',
        ];
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
