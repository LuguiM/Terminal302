<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuRutaResource;
use App\Models\Estado;
use App\Models\MenuRuta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MeMenuRutaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return response()->json([
                'message' => 'No se encontro el estado requerido: activo.',
            ], 500);
        }

        $menuRutas = MenuRuta::query()
            ->where('role_id', $request->user()?->role_id)
            ->where('estado_id', $activeStatus->id)
            ->where('visible', true)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        return response()->json([
            'menu_rutas' => MenuRutaResource::collection($this->buildTree($menuRutas)),
        ]);
    }

    private function buildTree(Collection $menuRutas): Collection
    {
        $grouped = $menuRutas->groupBy('dependencia');
        $parents = $menuRutas->filter(fn (MenuRuta $menuRuta): bool => $menuRuta->dependencia === null);

        return $this->attachChildren($parents, $grouped);
    }

    private function attachChildren(Collection $items, Collection $grouped): Collection
    {
        return $items
            ->sortBy(fn (MenuRuta $menuRuta): string => sprintf('%010.2f-%010d', (float) $menuRuta->orden, $menuRuta->id))
            ->values()
            ->map(function (MenuRuta $menuRuta) use ($grouped): MenuRuta {
                $children = $grouped->get($menuRuta->id, collect());
                $menuRuta->setRelation('dependencias', $this->attachChildren($children, $grouped));

                return $menuRuta;
            });
    }
}
