<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDigitalDeliveryMachineToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('digital_delivery.internal_token');
        $receivedToken = (string) $request->header('X-Internal-Token', '');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $receivedToken)) {
            return new JsonResponse(['message' => 'No autorizado.'], 401);
        }

        return $next($request);
    }
}
