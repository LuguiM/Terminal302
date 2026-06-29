<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Horario extends Model
{
    protected $fillable = [
        'ruta_id',
        'operador_id',
        'bus_id',
        'dia_id',
        'hora_salida',
        'sobreventa_permitida',
        'estado_id',
    ];

    protected function casts(): array
    {
        return [
            'sobreventa_permitida' => 'boolean',
        ];
    }

    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class);
    }

    public function operador(): BelongsTo
    {
        return $this->belongsTo(Operador::class);
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    public function dia(): BelongsTo
    {
        return $this->belongsTo(Dia::class);
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }
}
