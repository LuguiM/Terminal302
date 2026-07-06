<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangeInitialPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Estado;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->with(['role', 'estado', 'operador', 'operadorEmpleado.operador'])
            ->where('email', $request->input('email'))
            ->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son validas.'],
            ]);
        }

        if ((int) $user->estado_id !== Estado::ACTIVO_ID) {
            return response()->json([
                'message' => 'El usuario no esta activo.',
            ], 403);
        }

        $token = $user->createToken('terminal302-api')->plainTextToken;

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'requires_operator_registration' => $this->requiresOperatorRegistration($user),
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sesion cerrada correctamente.',
        ]);
    }

    public function user(Request $request): UserResource
    {
        return new UserResource($request->user()->load([
            'role',
            'estado',
            'operador',
            'operadorEmpleado.operador',
        ]));
    }

    public function changeInitialPassword(ChangeInitialPasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make((string) $request->input('password')),
            'must_change_password' => false,
        ])->save();

        return response()->json([
            'message' => 'Contrasena actualizada correctamente.',
            'user' => new UserResource($user->fresh([
                'role',
                'estado',
                'operador',
                'operadorEmpleado.operador',
            ])),
        ]);
    }

    private function requiresOperatorRegistration(User $user): bool
    {
        return mb_strtolower((string) $user->role?->nombre) === 'empresario'
            && $user->operador === null;
    }
}
