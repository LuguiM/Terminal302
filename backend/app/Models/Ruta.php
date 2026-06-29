<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ruta extends Model
{
    protected $fillable = [
        'ruta',
        'denominacion',
        'tarifa',
        'estado_id',
    ];

    protected function casts(): array
    {
        return [
            'tarifa' => 'decimal:2',
        ];
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

    public function scopeActivas(Builder $query): Builder
    {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('estado_id', $activeStatus->id);
    }
}
