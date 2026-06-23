<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoOperador extends Model
{
    protected $table = 'tipo_operadores';

    protected $fillable = [
        'nombre',
    ];
}
