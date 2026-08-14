<?php

namespace App\Http\Middleware;

use App\Services\OperatorAccessService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOperadorIsActive
{
    public function __construct(private readonly OperatorAccessService $operatorAccessService)
    {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $access = $this->operatorAccessService->forUser($request->user());

        if ($access['blocked']) {
            return response()->json([
                'message' => 'El operador esta desactivado. No puede realizar acciones operativas.',
                'code' => 'OPERATOR_DISABLED',
                'reason' => $access['reason'],
            ], 403);
        }

        return $next($request);
    }
}
