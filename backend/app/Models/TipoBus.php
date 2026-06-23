<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoBus extends Model
{
    protected $table = 'tipo_buses';

    protected $fillable = [
        'nombre',
    ];
}
