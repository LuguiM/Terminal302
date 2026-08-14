<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operador\ToggleOperadorStatusRequest;
use App\Http\Resources\BusResource;
use App\Http\Resources\OperadorEmpleadoResource;
use App\Http\Resources\OperadorListResource;
use App\Http\Resources\OperadorRutaResource;
use App\Http\Resources\OperadorResource;
use App\Models\Estado;
use App\Models\Operador;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminOperadorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->perPage($request);
        $search = trim($request->string('search')->toString());

        $operadores = Operador::query()
            ->with('estado')
            ->withCount(['operadorRutas', 'buses'])
            ->when($search !== '', function ($query) use ($search): void {
                $searchTerm = '%'.mb_strtolower($search).'%';

                $query->where(function ($query) use ($searchTerm): void {
                    $query
                        ->whereRaw('LOWER(nombre_comercial) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(razon_social) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(representante_legal) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(dui) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(nit) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(telefono) LIKE ?', [$searchTerm])
                        ->orWhereHas('user', function ($query) use ($searchTerm): void {
                            $query
                                ->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                                ->orWhereRaw('LOWER(email) LIKE ?', [$searchTerm]);
                        });
                });
            })
            ->orderBy('id')
            ->paginate($perPage);

        return ApiResponse::paginated($operadores, 'operadores', OperadorListResource::class);
    }

    public function show(Operador $operador): OperadorResource
    {
        return new OperadorResource($operador->load(['user', 'tipoOperador', 'estado']));
    }

    public function empleados(Request $request, Operador $operador): JsonResponse
    {
        $empleados = $operador->empleados()
            ->with(['user', 'estado'])
            ->orderBy('id')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($empleados, 'empleados', OperadorEmpleadoResource::class);
    }

    public function buses(Request $request, Operador $operador): JsonResponse
    {
        $buses = $operador->buses()
            ->with(['ruta', 'tipoBus', 'estado'])
            ->orderBy('id')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($buses, 'buses', BusResource::class);
    }

    public function rutas(Request $request, Operador $operador): JsonResponse
    {
        $rutas = $operador->operadorRutas()
            ->with(['ruta', 'estado'])
            ->orderBy('id')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($rutas, 'operador_rutas', OperadorRutaResource::class);
    }

    public function toggleStatus(ToggleOperadorStatusRequest $request, Operador $operador): JsonResponse
    {
        $activeStatus = Estado::activo();
        $inactiveStatus = Estado::inactivo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        if (! $inactiveStatus) {
            return $this->missingStatusResponse('inactivo/desactivado');
        }

        if ((int) $operador->estado_id === (int) $activeStatus->id) {
            $motivo = trim((string) $request->input('motivo_desactivacion'));

            if ($motivo === '') {
                throw ValidationException::withMessages([
                    'motivo_desactivacion' => ['El motivo de desactivacion es obligatorio.'],
                ]);
            }

            $operador->forceFill([
                'estado_id' => $inactiveStatus->id,
                'motivo_desactivacion' => $motivo,
            ])->save();
        } else {
            $operador->forceFill([
                'estado_id' => $activeStatus->id,
                'motivo_desactivacion' => null,
            ])->save();
        }

        return response()->json([
            'message' => 'Estado del operador actualizado correctamente.',
            'operador' => new OperadorListResource(
                $operador->fresh('estado')->loadCount(['operadorRutas', 'buses']),
            ),
        ]);
    }

    private function missingStatusResponse(string $statusName): JsonResponse
    {
        return response()->json([
            'message' => "No se encontro el estado requerido: {$statusName}.",
        ], 500);
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 50);
    }
}
