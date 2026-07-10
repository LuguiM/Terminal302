<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoOperador extends Model
{
    protected $table = 'tipo_operadores';

    protected $fillable = [
        'nombre',
    ];

    public function operadores(): HasMany
    {
        return $this->hasMany(Operador::class);
    }
}
