<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoBus extends Model
{
    protected $table = 'tipo_buses';

    protected $fillable = [
        'nombre',
    ];

    public function buses(): HasMany
    {
        return $this->hasMany(Bus::class);
    }
}
