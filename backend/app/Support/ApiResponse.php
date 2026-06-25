<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    /**
     * @param  class-string<JsonResource>  $resourceClass
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        string $dataKey,
        string $resourceClass,
    ): JsonResponse {
        return response()->json([
            $dataKey => $resourceClass::collection($paginator->getCollection()),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
