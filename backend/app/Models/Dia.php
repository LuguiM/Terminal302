<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dia extends Model
{
    protected $table = 'dias';

    protected $fillable = [
        'nombre',
        'orden',
    ];

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }
}
