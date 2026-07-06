<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperadorEmpleado extends Model
{
    protected $table = 'operador_empleados';

    protected $fillable = [
        'operador_id',
        'user_id',
        'estado_id',
        'motivo_desactivacion',
    ];

    public function operador(): BelongsTo
    {
        return $this->belongsTo(Operador::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }
}
