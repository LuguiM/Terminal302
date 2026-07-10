<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicTicketResource;
use App\Services\PublicTicketLookupService;
use Illuminate\Http\JsonResponse;

class PublicTicketController extends Controller
{
    public function showByCode(string $codigoTicket, PublicTicketLookupService $publicTicketLookupService): JsonResponse
    {
        $ticket = $publicTicketLookupService->findByCode($codigoTicket);

        if (! $ticket) {
            return response()->json([
                'message' => 'El ticket solicitado no existe.',
            ], 404);
        }

        if (! $publicTicketLookupService->hasRequiredRelations($ticket)) {
            return response()->json([
                'message' => 'El ticket no tiene la informacion publica necesaria para ser consultado.',
            ], 422);
        }

        return response()->json([
            'ticket' => new PublicTicketResource($ticket),
        ]);
    }
}
