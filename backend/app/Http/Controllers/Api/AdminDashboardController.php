<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\PassengerFlowDashboardRequest;
use App\Services\PassengerFlowDashboardService;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function flujoPasajeros(
        PassengerFlowDashboardRequest $request,
        PassengerFlowDashboardService $dashboardService,
    ): JsonResponse {
        return response()->json(
            $dashboardService->admin($request->dashboardFilters()),
        );
    }
}
