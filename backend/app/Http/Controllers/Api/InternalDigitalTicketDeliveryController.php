<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\ProcessDigitalTicketDeliveryRequest;
use App\Services\TicketDigitalDeliveryService;
use Illuminate\Http\JsonResponse;

class InternalDigitalTicketDeliveryController extends Controller
{
    public function process(
        ProcessDigitalTicketDeliveryRequest $request,
        TicketDigitalDeliveryService $deliveryService,
    ): JsonResponse {
        $eventId = $request->validated('event_id');
        $status = $deliveryService->processEvent(
            "ticket-events/pending/{$eventId}.json",
            archiveEvent: false,
        );

        $httpStatus = $status === TicketDigitalDeliveryService::PROCESSING ? 409 : 200;

        return response()->json([
            'message' => $status === TicketDigitalDeliveryService::PROCESSING
                ? 'La entrega digital ya esta siendo procesada.'
                : 'Evento de entrega digital atendido.',
            'event_id' => $eventId,
            'status' => $status,
        ], $httpStatus);
    }
}
