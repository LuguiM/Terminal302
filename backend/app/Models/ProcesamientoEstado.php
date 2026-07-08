<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcesamientoEstado extends Model
{
    public const PENDING = 'Pendiente';
    public const PROCESSING = 'Procesando';
    public const COMPLETED = 'Completado';
    public const FAILED = 'Fallido';

    protected $table = 'procesamiento_estados';

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

    public function isPending(): bool
    {
        return mb_strtolower($this->nombre) === mb_strtolower(self::PENDING);
    }

    public function isFailed(): bool
    {
        return mb_strtolower($this->nombre) === mb_strtolower(self::FAILED);
    }
}
