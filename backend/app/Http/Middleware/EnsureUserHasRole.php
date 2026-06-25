<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response|JsonResponse
    {
        $roleName = mb_strtolower((string) $request->user()?->role?->nombre);
        $allowedRoles = collect($roles)
            ->map(fn (string $role): string => mb_strtolower($role))
            ->all();

        if (! in_array($roleName, $allowedRoles, true)) {
            return response()->json([
                'message' => 'No tiene permisos para acceder a este recurso.',
            ], 403);
        }

        return $next($request);
    }
}
