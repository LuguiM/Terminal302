<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Operador extends Model
{
    protected $table = 'operadores';

    protected $fillable = [
        'user_id',
        'tipo_operador_id',
        'nombre',
        'razon_social',
        'representante_legal',
        'documento',
        'telefono',
        'correo',
        'direccion',
        'estado_id',
        'motivo_desactivacion',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tipoOperador(): BelongsTo
    {
        return $this->belongsTo(TipoOperador::class);
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }
}
