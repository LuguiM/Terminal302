<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicHorarioResource;
use App\Http\Resources\PublicRutaResource;
use App\Models\Estado;
use App\Models\Horario;
use App\Models\Ruta;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicRutaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 50);

        $rutas = Ruta::query()
            ->where('estado_id', $activeStatus->id)
            ->whereHas('horarios', fn ($query) => $query->where('estado_id', $activeStatus->id))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = mb_strtolower($request->string('search')->toString());

                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->whereRaw('LOWER(ruta) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(denominacion) LIKE ?', ["%{$search}%"]);
                });
            })
            ->orderBy('ruta')
            ->paginate($perPage);

        return ApiResponse::paginated($rutas, 'rutas', PublicRutaResource::class);
    }

    public function horariosPorRuta(Request $request, int|string $ruta): JsonResponse
    {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $ruta = Ruta::query()->find($ruta);

        if (! $ruta) {
            return response()->json([
                'message' => 'La ruta solicitada no existe.',
            ], 404);
        }

        if ((int) $ruta->estado_id !== (int) $activeStatus->id) {
            return response()->json([
                'message' => 'La ruta solicitada no esta activa.',
            ], 422);
        }

        $horarios = Horario::query()
            ->with(['ruta', 'dia', 'operador', 'bus'])
            ->where('ruta_id', $ruta->id)
            ->where('estado_id', $activeStatus->id)
            ->when($request->filled('dia_id'), fn ($query) => $query->where('dia_id', $request->integer('dia_id')))
            ->join('dias', 'horarios.dia_id', '=', 'dias.id')
            ->orderBy('dias.orden')
            ->orderBy('horarios.hora_salida')
            ->select('horarios.*')
            ->get();

        if ($horarios->isEmpty()) {
            return response()->json([
                'message' => 'No existen horarios activos para esta ruta.',
            ], 404);
        }

        return response()->json([
            'ruta' => new PublicRutaResource($ruta),
            'horarios' => PublicHorarioResource::collection($horarios),
        ]);
    }

    private function missingStatusResponse(string $statusName): JsonResponse
    {
        return response()->json([
            'message' => "No se encontro el estado requerido: {$statusName}.",
        ], 500);
    }
}
