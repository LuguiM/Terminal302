<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MenuRuta\StoreMenuRutaRequest;
use App\Http\Requests\MenuRuta\UpdateMenuRutaRequest;
use App\Http\Resources\MenuRutaResource;
use App\Models\Estado;
use App\Models\MenuRuta;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMenuRutaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 50);

        $menuRutas = MenuRuta::query()
            ->with(['estado', 'role', 'dependencias'])
            ->when($request->filled('role_id'), fn ($query) => $query->where('role_id', $request->integer('role_id')))
            ->when($request->filled('estado_id'), fn ($query) => $query->where('estado_id', $request->integer('estado_id')))
            ->when($request->has('dependencia'), function ($query) use ($request): void {
                $dependencia = $request->input('dependencia');

                if ($dependencia === null || $dependencia === '') {
                    $query->whereNull('dependencia');

                    return;
                }

                $query->where('dependencia', $request->integer('dependencia'));
            })
            ->when(! $request->has('dependencia'), fn ($query) => $query->whereNull('dependencia'))
            ->when($request->filled('visible'), fn ($query) => $query->where('visible', $request->boolean('visible')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = mb_strtolower($request->string('search')->toString());

                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->whereRaw('LOWER(titulo) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(ruta) LIKE ?', ["%{$search}%"]);
                });
            })
            ->orderBy('orden')
            ->orderBy('id')
            ->paginate($perPage);

        return ApiResponse::paginated($menuRutas, 'menu_rutas', MenuRutaResource::class);
    }

    public function store(StoreMenuRutaRequest $request): JsonResponse
    {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $validated = $request->validated();
        $validated['ruta'] = $validated['ruta'] ?? '';
        $validationResponse = $this->validateBusinessRules($validated);

        if ($validationResponse) {
            return $validationResponse;
        }

        $menuRuta = MenuRuta::query()->create([
            ...$validated,
            'visible' => $validated['visible'] ?? true,
            'requiere_autenticacion' => $validated['requiere_autenticacion'] ?? true,
            'dependencia' => $validated['dependencia'] ?? null,
            'estado_id' => $activeStatus->id,
        ]);

        return response()->json([
            'message' => 'Ruta de menu creada correctamente.',
            'menu_ruta' => new MenuRutaResource($menuRuta->load(['estado', 'role', 'dependencias'])),
        ], 201);
    }

    public function update(UpdateMenuRutaRequest $request, int|string $menuRuta): JsonResponse
    {
        $menuRuta = MenuRuta::query()->find($menuRuta);

        if (! $menuRuta) {
            return $this->missingMenuRutaResponse();
        }

        $validated = [
            ...$menuRuta->only([
                'titulo',
                'ruta',
                'orden',
                'icono',
                'visible',
                'requiere_autenticacion',
                'dependencia',
                'role_id',
                'base_url',
            ]),
            ...$request->validated(),
        ];
        $validated['ruta'] = $validated['ruta'] ?? '';
        $validationResponse = $this->validateBusinessRules($validated, $menuRuta);

        if ($validationResponse) {
            return $validationResponse;
        }

        $updateData = $request->validated();

        if (array_key_exists('ruta', $updateData)) {
            $updateData['ruta'] = $updateData['ruta'] ?? '';
        }

        $menuRuta->update($updateData);

        return response()->json([
            'message' => 'Ruta de menu actualizada correctamente.',
            'menu_ruta' => new MenuRutaResource($menuRuta->fresh(['estado', 'role', 'dependencias'])),
        ]);
    }

    public function toggleStatus(int|string $menuRuta): JsonResponse
    {
        $menuRuta = MenuRuta::query()->find($menuRuta);

        if (! $menuRuta) {
            return $this->missingMenuRutaResponse();
        }

        $activeStatus = Estado::activo();
        $inactiveStatus = Estado::inactivo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        if (! $inactiveStatus) {
            return $this->missingStatusResponse('inactivo/desactivado');
        }

        $menuRuta->forceFill([
            'estado_id' => (int) $menuRuta->estado_id === (int) $activeStatus->id
                ? $inactiveStatus->id
                : $activeStatus->id,
        ])->save();

        return response()->json([
            'message' => 'Estado de la ruta de menu actualizado correctamente.',
            'menu_ruta' => new MenuRutaResource($menuRuta->fresh(['estado', 'role', 'dependencias'])),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function validateBusinessRules(array $attributes, ?MenuRuta $current = null): ?JsonResponse
    {
        $dependencia = $attributes['dependencia'] ?? null;
        $roleId = (int) $attributes['role_id'];
        $ruta = trim((string) $attributes['ruta']);
        $titulo = mb_strtolower(trim((string) $attributes['titulo']));

        if ($dependencia !== null) {
            if ($current && (int) $dependencia === (int) $current->id) {
                return response()->json([
                    'message' => 'Una ruta de menu no puede depender de si misma.',
                ], 422);
            }

            $parent = MenuRuta::query()->find($dependencia);

            if (! $parent) {
                return response()->json([
                    'message' => 'La dependencia seleccionada no existe.',
                ], 422);
            }

            if ((int) $parent->role_id !== $roleId) {
                return response()->json([
                    'message' => 'La dependencia pertenece a otro rol.',
                ], 422);
            }

            if ($current && $this->createsDependencyCycle($current, (int) $dependencia)) {
                return response()->json([
                    'message' => 'Se detecto un ciclo de dependencia en las rutas de menu.',
                ], 422);
            }
        }

        $duplicateQuery = MenuRuta::query()
            ->where('role_id', $roleId)
            ->where(function ($query) use ($dependencia): void {
                $dependencia === null
                    ? $query->whereNull('dependencia')
                    : $query->where('dependencia', $dependencia);
            })
            ->when($current, fn ($query) => $query->where('id', '!=', $current->id));

        if ($ruta === '') {
            $duplicateExists = $duplicateQuery
                ->whereRaw('LOWER(titulo) = ?', [$titulo])
                ->exists();

            if ($duplicateExists) {
                return response()->json([
                    'message' => 'El titulo ya existe para ese rol y dependencia.',
                ], 422);
            }

            return null;
        }

        $duplicateExists = $duplicateQuery
            ->where('ruta', $ruta)
            ->exists();

        if ($duplicateExists) {
            return response()->json([
                'message' => 'La ruta ya existe para ese rol y dependencia.',
            ], 422);
        }

        return null;
    }

    private function createsDependencyCycle(MenuRuta $current, int $newParentId): bool
    {
        $visited = [];
        $parentId = $newParentId;

        while ($parentId !== null) {
            if ((int) $parentId === (int) $current->id || in_array($parentId, $visited, true)) {
                return true;
            }

            $visited[] = $parentId;
            $parentId = MenuRuta::query()->whereKey($parentId)->value('dependencia');
        }

        return false;
    }

    private function missingMenuRutaResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'La ruta de menu solicitada no existe.',
        ], 404);
    }

    private function missingStatusResponse(string $statusName): JsonResponse
    {
        return response()->json([
            'message' => "No se encontro el estado requerido: {$statusName}.",
        ], 500);
    }
}
