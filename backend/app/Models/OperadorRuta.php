<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperadorRuta extends Model
{
    protected $table = 'operador_rutas';

    protected $fillable = [
        'operador_id',
        'ruta_id',
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

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }
}
