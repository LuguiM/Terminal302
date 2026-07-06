<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OperadorEmpleado\StoreOperadorEmpleadoRequest;
use App\Http\Requests\OperadorEmpleado\ToggleOperadorEmpleadoStatusRequest;
use App\Http\Requests\OperadorEmpleado\UpdateOperadorEmpleadoRequest;
use App\Http\Resources\OperadorEmpleadoResource;
use App\Mail\InitialUserCredentialsMail;
use App\Models\Estado;
use App\Models\Operador;
use App\Models\OperadorEmpleado;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\TemporaryPasswordGenerator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class OperadorEmpleadoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $operador = $this->authenticatedOperador($request);

        if (! $operador) {
            return $this->missingOperadorResponse();
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 50);
        $search = trim($request->string('search')->toString());

        $empleados = OperadorEmpleado::query()
            ->with(['user', 'estado'])
            ->where('operador_id', $operador->id)
            ->when($search !== '', function ($query) use ($search): void {
                $searchTerm = '%'.mb_strtolower($search).'%';

                $query->whereHas('user', function ($query) use ($searchTerm): void {
                    $query
                        ->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$searchTerm]);
                });
            })
            ->orderBy('id')
            ->paginate($perPage);

        return ApiResponse::paginated($empleados, 'empleados', OperadorEmpleadoResource::class);
    }

    public function store(
        StoreOperadorEmpleadoRequest $request,
        TemporaryPasswordGenerator $passwordGenerator,
    ): JsonResponse {
        $operador = $this->authenticatedOperador($request);

        if (! $operador) {
            return $this->missingOperadorResponse();
        }

        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $validatorRole = $this->validatorRole();

        if (! $validatorRole) {
            return response()->json([
                'message' => 'No se encontro el rol requerido: validador.',
            ], 500);
        }

        $temporaryPassword = $passwordGenerator->generate();

        $empleado = DB::transaction(function () use ($request, $operador, $activeStatus, $validatorRole, $temporaryPassword): OperadorEmpleado {
            $user = User::query()->create([
                'role_id' => $validatorRole->id,
                'estado_id' => $activeStatus->id,
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'email_verified_at' => now(),
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
            ]);

            return OperadorEmpleado::query()->create([
                'operador_id' => $operador->id,
                'user_id' => $user->id,
                'estado_id' => $activeStatus->id,
            ]);
        });

        $empleado->load(['user', 'estado']);

        Mail::to($empleado->user?->email)->send(new InitialUserCredentialsMail(
            user: $empleado->user,
            temporaryPassword: $temporaryPassword,
            purpose: InitialUserCredentialsMail::PURPOSE_INITIAL,
        ));

        return response()->json([
            'message' => 'Empleado creado correctamente.',
            'empleado' => new OperadorEmpleadoResource($empleado),
        ], 201);
    }

    public function update(UpdateOperadorEmpleadoRequest $request, OperadorEmpleado $empleado): JsonResponse
    {
        $operador = $this->authenticatedOperador($request);

        if (! $operador) {
            return $this->missingOperadorResponse();
        }

        if (! $this->employeeBelongsToOperator($empleado, $operador)) {
            return $this->missingOwnedEmpleadoResponse();
        }

        $empleado->user?->update($request->validated());

        return response()->json([
            'message' => 'Empleado actualizado correctamente.',
            'empleado' => new OperadorEmpleadoResource($empleado->fresh(['user', 'estado'])),
        ]);
    }

    public function toggleStatus(
        ToggleOperadorEmpleadoStatusRequest $request,
        OperadorEmpleado $empleado,
    ): JsonResponse {
        $operador = $this->authenticatedOperador($request);

        if (! $operador) {
            return $this->missingOperadorResponse();
        }

        if (! $this->employeeBelongsToOperator($empleado, $operador)) {
            return $this->missingOwnedEmpleadoResponse();
        }

        $activeStatus = Estado::activo();
        $inactiveStatus = Estado::inactivo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        if (! $inactiveStatus) {
            return $this->missingStatusResponse('inactivo/desactivado');
        }

        $isActive = (int) $empleado->estado_id === (int) $activeStatus->id;

        if ($isActive && ! trim((string) $request->input('motivo_desactivacion'))) {
            return response()->json([
                'message' => 'El motivo de desactivacion es requerido.',
                'errors' => [
                    'motivo_desactivacion' => ['El motivo de desactivacion es requerido.'],
                ],
            ], 422);
        }

        $targetStatus = $isActive ? $inactiveStatus : $activeStatus;
        $motivoDesactivacion = $isActive
            ? trim((string) $request->input('motivo_desactivacion'))
            : null;

        DB::transaction(function () use ($empleado, $targetStatus, $motivoDesactivacion): void {
            $empleado->forceFill([
                'estado_id' => $targetStatus->id,
                'motivo_desactivacion' => $motivoDesactivacion,
            ])->save();

            $empleado->user?->forceFill([
                'estado_id' => $targetStatus->id,
            ])->save();
        });

        return response()->json([
            'message' => 'Estado del empleado actualizado correctamente.',
            'empleado' => new OperadorEmpleadoResource($empleado->fresh(['user', 'estado'])),
        ]);
    }

    private function authenticatedOperador(Request $request): ?Operador
    {
        return $request->user()
            ?->operador()
            ->first();
    }

    private function employeeBelongsToOperator(OperadorEmpleado $empleado, Operador $operador): bool
    {
        return (int) $empleado->operador_id === (int) $operador->id;
    }

    private function validatorRole(): ?Role
    {
        return Role::query()
            ->whereRaw('LOWER(nombre) = ?', ['validador'])
            ->first();
    }

    private function missingOperadorResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'El empresario autenticado no tiene operador registrado.',
        ], 404);
    }

    private function missingOwnedEmpleadoResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'El empleado no pertenece al operador autenticado.',
        ], 404);
    }

    private function missingStatusResponse(string $statusName): JsonResponse
    {
        return response()->json([
            'message' => "No se encontro el estado requerido: {$statusName}.",
        ], 500);
    }
}
