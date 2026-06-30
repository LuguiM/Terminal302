<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Validacion extends Model
{
    public const RESULTADO_VALIDO = 'valido';

    protected $table = 'validaciones';

    protected $fillable = [
        'ticket_id',
        'validador_id',
        'fecha_validacion',
        'resultado',
        'observacion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_validacion' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function validador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validador_id');
    }
}
