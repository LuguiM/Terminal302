<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VentaHorario extends Model
{
    protected $table = 'ventas_horarios';

    protected $fillable = [
        'horario_id',
        'fecha_operacion',
        'venta_cerrada',
        'cerrada_por',
        'fecha_cierre',
        'motivo_cierre',
        'total_tickets_vendidos',
        'total_tickets_sobreventa',
        'estado_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_operacion' => 'date',
            'venta_cerrada' => 'boolean',
            'fecha_cierre' => 'datetime',
            'total_tickets_vendidos' => 'integer',
            'total_tickets_sobreventa' => 'integer',
        ];
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }

    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
