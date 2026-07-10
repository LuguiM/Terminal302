<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\PassengerFlowDashboardRequest;
use App\Services\PassengerFlowDashboardService;
use Illuminate\Http\JsonResponse;

class OperadorDashboardController extends Controller
{
    public function flujoPasajeros(
        PassengerFlowDashboardRequest $request,
        PassengerFlowDashboardService $dashboardService,
    ): JsonResponse {
        $operador = $request->user()?->operador()->first();

        if (! $operador) {
            return response()->json([
                'message' => 'El empresario autenticado no tiene operador registrado.',
            ], 404);
        }

        return response()->json(
            $dashboardService->operador($request->dashboardFilters(), $operador),
        );
    }
}
