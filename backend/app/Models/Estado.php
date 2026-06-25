<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estado extends Model
{
    public const ACTIVO_ID = 1;
    public const DESACTIVADO_ID = 2;
    public const EMITIDO_ID = 3;
    public const VALIDADO_ID = 4;
    public const CANCELADO_ID = 5;
    public const PROGRAMADO_ID = 6;

    protected $fillable = [
        'id',
        'nombre',
    ];

    public static function activo(): ?self
    {
        return self::query()
            ->whereRaw('LOWER(nombre) = ?', ['activo'])
            ->first();
    }

    public static function inactivo(): ?self
    {
        return self::query()
            ->whereRaw('LOWER(nombre) IN (?, ?)', ['inactivo', 'desactivado'])
            ->first();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function operadores(): HasMany
    {
        return $this->hasMany(Operador::class);
    }
}
