<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketPlantilla\StoreTicketPlantillaRequest;
use App\Http\Requests\TicketPlantilla\UpdateTicketPlantillaRequest;
use App\Http\Resources\TicketPlantillaResource;
use App\Models\Estado;
use App\Models\TicketPlantilla;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminTicketPlantillaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 50);

        $ticketPlantillas = TicketPlantilla::query()
            ->with('estado')
            ->when($request->filled('estado_id'), fn ($query) => $query->where('estado_id', $request->integer('estado_id')))
            ->when($request->filled('es_predeterminada'), fn ($query) => $query->where('es_predeterminada', $request->boolean('es_predeterminada')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';

                $query->where('nombre', 'like', $search);
            })
            ->orderBy('id')
            ->paginate($perPage);

        return ApiResponse::paginated($ticketPlantillas, 'ticket_plantillas', TicketPlantillaResource::class);
    }

    public function store(StoreTicketPlantillaRequest $request): JsonResponse
    {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $validated = $request->validated();
        $imagePath = $request->file('image')->store('ticket-plantillas');

        unset($validated['image']);

        $ticketPlantilla = DB::transaction(function () use ($validated, $activeStatus, $imagePath): TicketPlantilla {
            if ((bool) ($validated['es_predeterminada'] ?? false)) {
                TicketPlantilla::query()->update(['es_predeterminada' => false]);
            }

            return TicketPlantilla::query()->create([
                ...$validated,
                'image_path' => $imagePath,
                'estado_id' => $activeStatus->id,
                'es_predeterminada' => (bool) ($validated['es_predeterminada'] ?? false),
            ]);
        });

        return response()->json([
            'message' => 'Plantilla de ticket creada correctamente.',
            'ticket_plantilla' => new TicketPlantillaResource($ticketPlantilla->load('estado')),
        ], 201);
    }

    public function show(int|string $ticketPlantilla): JsonResponse
    {
        $ticketPlantilla = $this->findTicketPlantilla($ticketPlantilla);

        if (! $ticketPlantilla) {
            return $this->missingTicketPlantillaResponse();
        }

        return response()->json([
            'ticket_plantilla' => new TicketPlantillaResource($ticketPlantilla->load('estado')),
        ]);
    }

    public function update(UpdateTicketPlantillaRequest $request, int|string $ticketPlantilla): JsonResponse
    {
        $ticketPlantilla = $this->findTicketPlantilla($ticketPlantilla);

        if (! $ticketPlantilla) {
            return $this->missingTicketPlantillaResponse();
        }

        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        if ($request->boolean('es_predeterminada') && (int) $ticketPlantilla->estado_id !== (int) $activeStatus->id) {
            return $this->inactiveDefaultResponse();
        }

        $validated = $request->validated();
        $oldImagePath = null;

        if ($request->hasFile('image')) {
            $oldImagePath = $ticketPlantilla->image_path;
            $validated['image_path'] = $request->file('image')->store('ticket-plantillas');
        }

        unset($validated['image']);

        DB::transaction(function () use ($ticketPlantilla, $validated): void {
            if ((bool) ($validated['es_predeterminada'] ?? false)) {
                TicketPlantilla::query()
                    ->whereKeyNot($ticketPlantilla->id)
                    ->update(['es_predeterminada' => false]);
            }

            $ticketPlantilla->update($validated);
        });

        if ($oldImagePath) {
            $this->deleteImageIfUnused($oldImagePath);
        }

        return response()->json([
            'message' => 'Plantilla de ticket actualizada correctamente.',
            'ticket_plantilla' => new TicketPlantillaResource($ticketPlantilla->fresh('estado')),
        ]);
    }

    public function destroy(int|string $ticketPlantilla): JsonResponse
    {
        $ticketPlantilla = $this->findTicketPlantilla($ticketPlantilla);

        if (! $ticketPlantilla) {
            return $this->missingTicketPlantillaResponse();
        }

        if ($ticketPlantilla->es_predeterminada) {
            return response()->json([
                'message' => 'No se puede eliminar una plantilla predeterminada.',
            ], 422);
        }

        $imagePath = $ticketPlantilla->image_path;

        $ticketPlantilla->delete();
        $this->deleteImageIfUnused($imagePath);

        return response()->json([
            'message' => 'Plantilla de ticket eliminada correctamente.',
        ]);
    }

    public function download(int|string $ticketPlantilla): JsonResponse|StreamedResponse
    {
        $ticketPlantilla = $this->findTicketPlantilla($ticketPlantilla);

        if (! $ticketPlantilla) {
            return $this->missingTicketPlantillaResponse();
        }

        if (! Storage::exists($ticketPlantilla->image_path)) {
            return response()->json([
                'message' => 'El archivo de la plantilla no existe.',
            ], 404);
        }

        return Storage::download(
            $ticketPlantilla->image_path,
            basename($ticketPlantilla->image_path),
        );
    }

    public function toggleStatus(int|string $ticketPlantilla): JsonResponse
    {
        $ticketPlantilla = $this->findTicketPlantilla($ticketPlantilla);

        if (! $ticketPlantilla) {
            return $this->missingTicketPlantillaResponse();
        }

        $activeStatus = Estado::activo();
        $inactiveStatus = Estado::inactivo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        if (! $inactiveStatus) {
            return $this->missingStatusResponse('inactivo/desactivado');
        }

        $newStatusId = (int) $ticketPlantilla->estado_id === (int) $activeStatus->id
            ? $inactiveStatus->id
            : $activeStatus->id;

        $ticketPlantilla->forceFill([
            'estado_id' => $newStatusId,
            'es_predeterminada' => (int) $newStatusId === (int) $activeStatus->id
                ? $ticketPlantilla->es_predeterminada
                : false,
        ])->save();

        return response()->json([
            'message' => 'Estado de la plantilla de ticket actualizado correctamente.',
            'ticket_plantilla' => new TicketPlantillaResource($ticketPlantilla->fresh('estado')),
        ]);
    }

    public function setDefault(int|string $ticketPlantilla): JsonResponse
    {
        $ticketPlantilla = $this->findTicketPlantilla($ticketPlantilla);

        if (! $ticketPlantilla) {
            return $this->missingTicketPlantillaResponse();
        }

        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        if ((int) $ticketPlantilla->estado_id !== (int) $activeStatus->id) {
            return $this->inactiveDefaultResponse();
        }

        DB::transaction(function () use ($ticketPlantilla): void {
            TicketPlantilla::query()
                ->whereKeyNot($ticketPlantilla->id)
                ->update(['es_predeterminada' => false]);

            $ticketPlantilla->forceFill([
                'es_predeterminada' => true,
            ])->save();
        });

        return response()->json([
            'message' => 'Plantilla de ticket predeterminada actualizada correctamente.',
            'ticket_plantilla' => new TicketPlantillaResource($ticketPlantilla->fresh('estado')),
        ]);
    }

    private function findTicketPlantilla(int|string $id): ?TicketPlantilla
    {
        return TicketPlantilla::query()->find($id);
    }

    private function deleteImageIfUnused(string $imagePath): void
    {
        $isUsed = TicketPlantilla::query()
            ->where('image_path', $imagePath)
            ->exists();

        if (! $isUsed && Storage::exists($imagePath)) {
            Storage::delete($imagePath);
        }
    }

    private function missingTicketPlantillaResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'La plantilla de ticket solicitada no existe.',
        ], 404);
    }

    private function inactiveDefaultResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'La plantilla de ticket debe estar activa para marcarse como predeterminada.',
        ], 422);
    }

    private function missingStatusResponse(string $statusName): JsonResponse
    {
        return response()->json([
            'message' => "No se encontro el estado requerido: {$statusName}.",
        ], 500);
    }
}
