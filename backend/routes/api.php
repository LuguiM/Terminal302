<?php

use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AdminOperadorController;
use App\Http\Controllers\Api\AdminRutaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OperadorController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/change-initial-password', [AuthController::class, 'changeInitialPassword']);

    Route::middleware(['password.changed', 'role:empresario', 'operator.active'])
        ->group(function (): void {
            Route::get('/operador/me', [OperadorController::class, 'me']);
            Route::post('/operador', [OperadorController::class, 'store']);
            Route::put('/operador/{operador}', [OperadorController::class, 'update']);
        });

    Route::middleware(['password.changed', 'role:administrador'])
        ->prefix('admin')
        ->group(function (): void {
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::get('/users/{user}', [AdminUserController::class, 'show']);
            Route::put('/users/{user}', [AdminUserController::class, 'update']);
            Route::patch('/users/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
            Route::patch('/users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus']);

            Route::get('/operadores', [AdminOperadorController::class, 'index']);
            Route::get('/operadores/{operador}', [AdminOperadorController::class, 'show']);
            Route::patch('/operadores/{operador}/toggle-status', [AdminOperadorController::class, 'toggleStatus']);

            Route::get('/rutas', [AdminRutaController::class, 'index']);
            Route::post('/rutas', [AdminRutaController::class, 'store']);
            Route::put('/rutas/{ruta}', [AdminRutaController::class, 'update']);
            Route::patch('/rutas/{ruta}/toggle-status', [AdminRutaController::class, 'toggleStatus']);
            Route::delete('/rutas/{ruta}', [AdminRutaController::class, 'destroy']);
        });
});
