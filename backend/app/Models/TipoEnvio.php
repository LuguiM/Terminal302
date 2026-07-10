<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoEnvio extends Model
{
    public const IMPRESO = 'impreso';
    public const DIGITAL = 'digital';

    protected $table = 'tipo_envios';

    protected $fillable = [
        'nombre',
        'descripcion',
        'estado_id',
    ];

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function isDigital(): bool
    {
        return mb_strtolower($this->nombre) === self::DIGITAL;
    }
}
