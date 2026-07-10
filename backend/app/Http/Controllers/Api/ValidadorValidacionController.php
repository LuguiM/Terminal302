<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ValidacionResource;
use App\Models\Estado;
use App\Models\Validacion;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValidadorValidacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ((int) $request->user()?->estado_id !== Estado::ACTIVO_ID) {
            return response()->json([
                'message' => 'El usuario no esta activo.',
            ], 403);
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 50);

        $validaciones = Validacion::query()
            ->with(['ticket.estado', 'validador'])
            ->where('validador_id', $request->user()?->id)
            ->when($request->filled('ticket_id'), fn ($query) => $query->where('ticket_id', $request->integer('ticket_id')))
            ->when($request->filled('fecha_validacion'), function ($query) use ($request): void {
                $query->whereDate('fecha_validacion', CarbonImmutable::parse($request->string('fecha_validacion')->toString())->toDateString());
            })
            ->orderByDesc('fecha_validacion')
            ->paginate($perPage);

        return ApiResponse::paginated($validaciones, 'validaciones', ValidacionResource::class);
    }
}
