<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Operador extends Model
{
    protected $table = 'operadores';

    protected $fillable = [
        'user_id',
        'tipo_operador_id',
        'nombre_comercial',
        'razon_social',
        'representante_legal',
        'telefono_opcional',
        'correo_administrativo',
        'nit',
        'dui',
        'telefono',
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

    public function operadorRutas(): HasMany
    {
        return $this->hasMany(OperadorRuta::class);
    }

    public function buses(): HasMany
    {
        return $this->hasMany(Bus::class);
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(OperadorEmpleado::class);
    }
}
