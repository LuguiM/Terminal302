<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bus\StoreBusRequest;
use App\Http\Requests\Bus\UpdateBusRequest;
use App\Http\Resources\BusResource;
use App\Models\Bus;
use App\Models\Estado;
use App\Models\Operador;
use App\Models\OperadorRuta;
use App\Models\Ruta;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $operador = $this->authenticatedOperador($request);

        if (! $operador) {
            return $this->missingOperadorResponse();
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 50);

        $buses = Bus::query()
            ->with(['ruta', 'tipoBus', 'estado'])
            ->where('operador_id', $operador->id)
            ->when($request->filled('ruta_id'), fn ($query) => $query->where('ruta_id', $request->integer('ruta_id')))
            ->when($request->filled('estado_id'), fn ($query) => $query->where('estado_id', $request->integer('estado_id')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('placa', 'like', $search)
                        ->orWhere('marca', 'like', $search)
                        ->orWhere('nombre_unidad', 'like', $search);
                });
            })
            ->orderBy('id')
            ->paginate($perPage);

        return ApiResponse::paginated($buses, 'buses', BusResource::class);
    }

    public function store(StoreBusRequest $request): JsonResponse
    {
        $operador = $this->authenticatedOperador($request);

        if (! $operador) {
            return $this->missingOperadorResponse();
        }

        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $routeValidationResponse = $this->validateActiveAssignedRoute(
            operador: $operador,
            rutaId: $request->integer('ruta_id'),
            activeStatus: $activeStatus,
        );

        if ($routeValidationResponse) {
            return $routeValidationResponse;
        }

        $bus = Bus::query()->create([
            ...$request->validated(),
            'operador_id' => $operador->id,
            'estado_id' => $activeStatus->id,
        ]);

        return response()->json([
            'message' => 'Bus registrado correctamente.',
            'bus' => new BusResource($bus->load(['ruta', 'tipoBus', 'estado'])),
        ], 201);
    }

    public function update(UpdateBusRequest $request, int|string $bus): JsonResponse
    {
        $operador = $this->authenticatedOperador($request);

        if (! $operador) {
            return $this->missingOperadorResponse();
        }

        $bus = $this->findOwnedBus($operador, $bus);

        if (! $bus) {
            return $this->missingOwnedBusResponse();
        }

        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $routeValidationResponse = $this->validateActiveAssignedRoute(
            operador: $operador,
            rutaId: $request->integer('ruta_id'),
            activeStatus: $activeStatus,
        );

        if ($routeValidationResponse) {
            return $routeValidationResponse;
        }

        $bus->update($request->validated());

        return response()->json([
            'message' => 'Bus actualizado correctamente.',
            'bus' => new BusResource($bus->fresh(['ruta', 'tipoBus', 'estado'])),
        ]);
    }

    public function toggleStatus(Request $request, int|string $bus): JsonResponse
    {
        $operador = $this->authenticatedOperador($request);

        if (! $operador) {
            return $this->missingOperadorResponse();
        }

        $bus = $this->findOwnedBus($operador, $bus);

        if (! $bus) {
            return $this->missingOwnedBusResponse();
        }

        $activeStatus = Estado::activo();
        $inactiveStatus = Estado::inactivo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        if (! $inactiveStatus) {
            return $this->missingStatusResponse('inactivo/desactivado');
        }

        $bus->forceFill([
            'estado_id' => (int) $bus->estado_id === (int) $activeStatus->id
                ? $inactiveStatus->id
                : $activeStatus->id,
        ])->save();

        return response()->json([
            'message' => 'Estado del bus actualizado correctamente.',
            'bus' => new BusResource($bus->fresh(['ruta', 'tipoBus', 'estado'])),
        ]);
    }

    private function authenticatedOperador(Request $request): ?Operador
    {
        return $request->user()
            ?->operador()
            ->first();
    }

    private function findOwnedBus(Operador $operador, int|string $bus): ?Bus
    {
        return Bus::query()
            ->where('operador_id', $operador->id)
            ->find($bus);
    }

    private function validateActiveAssignedRoute(
        Operador $operador,
        int $rutaId,
        Estado $activeStatus,
    ): ?JsonResponse {
        $ruta = Ruta::query()->find($rutaId);

        if (! $ruta) {
            return response()->json([
                'message' => 'La ruta seleccionada no existe.',
            ], 404);
        }

        if ((int) $ruta->estado_id !== (int) $activeStatus->id) {
            return response()->json([
                'message' => 'La ruta seleccionada no esta activa.',
            ], 422);
        }

        $assignedRouteExists = OperadorRuta::query()
            ->where('operador_id', $operador->id)
            ->where('ruta_id', $ruta->id)
            ->where('estado_id', $activeStatus->id)
            ->exists();

        if (! $assignedRouteExists) {
            return response()->json([
                'message' => 'La ruta no pertenece al operador autenticado.',
            ], 403);
        }

        return null;
    }

    private function missingOperadorResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'El empresario autenticado no tiene operador registrado.',
        ], 404);
    }

    private function missingOwnedBusResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'El bus no pertenece al operador autenticado.',
        ], 404);
    }

    private function missingStatusResponse(string $statusName): JsonResponse
    {
        return response()->json([
            'message' => "No se encontro el estado requerido: {$statusName}.",
        ], 500);
    }
}
