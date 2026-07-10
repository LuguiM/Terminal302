<?php

namespace App\Http\Middleware;

use App\Models\Estado;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOperadorIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $operador = $request->user()?->operador;

        if (! $operador) {
            return $next($request);
        }

        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return response()->json([
                'message' => 'No se encontro el estado requerido: activo.',
            ], 500);
        }

        if ((int) $operador->estado_id !== (int) $activeStatus->id) {
            return response()->json([
                'message' => 'El operador esta desactivado. No puede realizar acciones operativas.',
            ], 403);
        }

        return $next($request);
    }
}
