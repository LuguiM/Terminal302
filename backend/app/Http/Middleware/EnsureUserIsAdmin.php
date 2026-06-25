<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $roleName = mb_strtolower((string) $request->user()?->role?->nombre);

        if ($roleName !== 'administrador') {
            return response()->json([
                'message' => 'No tiene permisos para gestionar usuarios.',
            ], 403);
        }

        return $next($request);
    }
}
