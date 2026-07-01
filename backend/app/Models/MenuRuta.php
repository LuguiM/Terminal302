<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuRuta extends Model
{
    protected $table = 'menu_rutas';

    protected $fillable = [
        'titulo',
        'ruta',
        'orden',
        'icono',
        'visible',
        'requiere_autenticacion',
        'dependencia',
        'role_id',
        'base_url',
        'estado_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'decimal:2',
            'visible' => 'boolean',
            'requiere_autenticacion' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'dependencia');
    }

    public function dependencias(): HasMany
    {
        return $this->hasMany(self::class, 'dependencia')->orderBy('orden')->orderBy('id');
    }
}
