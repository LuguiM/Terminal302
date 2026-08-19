<?php

namespace App\Services;

use App\Models\Estado;
use App\Models\Operador;
use App\Models\User;

class OperatorAccessService
{
    /**
     * @return array{blocked: bool, reason: ?string}
     */
    public function forUser(?User $user): array
    {
        $operador = $this->operatorFor($user);
        $blocked = $operador !== null
            && (int) $operador->estado_id !== Estado::ACTIVO_ID;

        return [
            'blocked' => $blocked,
            'reason' => $blocked
                ? ($operador->motivo_desactivacion ?: 'Comunicate con el administrador de la terminal para restablecer tu acceso.')
                : null,
        ];
    }

    public function operatorFor(?User $user): ?Operador
    {
        if (! $user) {
            return null;
        }

        $role = mb_strtolower(trim((string) $user->role?->nombre));

        if ($role === 'empresario') {
            return $user->operador;
        }

        if ($role === 'validador') {
            return $user->operadorEmpleado?->operador;
        }

        return null;
    }
}
