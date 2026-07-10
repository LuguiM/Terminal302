<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInitialPasswordChanged
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        if ($request->user()?->must_change_password) {
            return response()->json([
                'message' => 'Debe cambiar la contrasena inicial antes de continuar.',
            ], 403);
        }

        return $next($request);
    }
}
