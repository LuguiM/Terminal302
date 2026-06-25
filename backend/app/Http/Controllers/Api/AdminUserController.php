<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Http\Resources\UserResource;
use App\Mail\InitialUserCredentialsMail;
use App\Models\Estado;
use App\Models\User;
use App\Services\Auth\TemporaryPasswordGenerator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 50);

        $users = User::query()
            ->with(['role', 'estado'])
            ->orderBy('id')
            ->paginate($perPage);

        return ApiResponse::paginated($users, 'users', UserResource::class);
    }

    public function store(
        StoreAdminUserRequest $request,
        TemporaryPasswordGenerator $passwordGenerator,
    ): JsonResponse {
        $activeStatus = Estado::activo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        $temporaryPassword = $passwordGenerator->generate();

        $user = User::query()->create([
            ...$request->validated(),
            'estado_id' => $activeStatus->id,
            'email_verified_at' => now(),
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);

        Mail::to($user->email)->send(new InitialUserCredentialsMail(
            user: $user,
            temporaryPassword: $temporaryPassword,
            purpose: InitialUserCredentialsMail::PURPOSE_INITIAL,
        ));

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'user' => new UserResource($user->load(['role', 'estado'])),
        ], 201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load(['role', 'estado']));
    }

    public function update(UpdateAdminUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'user' => new UserResource($user->fresh(['role', 'estado'])),
        ]);
    }

    public function resetPassword(
        User $user,
        TemporaryPasswordGenerator $passwordGenerator,
    ): JsonResponse {
        $temporaryPassword = $passwordGenerator->generate();

        $user->forceFill([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ])->save();

        Mail::to($user->email)->send(new InitialUserCredentialsMail(
            user: $user,
            temporaryPassword: $temporaryPassword,
            purpose: InitialUserCredentialsMail::PURPOSE_RESET,
        ));

        return response()->json([
            'message' => 'Contrasena restablecida correctamente.',
            'user' => new UserResource($user->fresh(['role', 'estado'])),
        ]);
    }

    public function toggleStatus(Request $request, User $user): JsonResponse
    {
        $activeStatus = Estado::activo();
        $inactiveStatus = Estado::inactivo();

        if (! $activeStatus) {
            return $this->missingStatusResponse('activo');
        }

        if (! $inactiveStatus) {
            return $this->missingStatusResponse('inactivo/desactivado');
        }

        if ($request->user()?->is($user)) {
            return response()->json([
                'message' => 'No puede cambiar su propio estado.',
            ], 403);
        }

        $user->forceFill([
            'estado_id' => (int) $user->estado_id === (int) $activeStatus->id
                ? $inactiveStatus->id
                : $activeStatus->id,
        ])->save();

        return response()->json([
            'message' => 'Estado del usuario actualizado correctamente.',
            'user' => new UserResource($user->fresh(['role', 'estado'])),
        ]);
    }

    private function missingStatusResponse(string $statusName): JsonResponse
    {
        return response()->json([
            'message' => "No se encontro el estado requerido: {$statusName}.",
        ], 500);
    }
}
