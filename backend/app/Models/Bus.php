<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bus extends Model
{
    protected $fillable = [
        'operador_id',
        'ruta_id',
        'placa',
        'marca',
        'nombre_unidad',
        'capacidad',
        'tipo_bus_id',
        'estado_id',
    ];

    public function operador(): BelongsTo
    {
        return $this->belongsTo(Operador::class);
    }

    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class);
    }

    public function tipoBus(): BelongsTo
    {
        return $this->belongsTo(TipoBus::class);
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }
}
