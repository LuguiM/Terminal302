<?php

use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AdminOperadorController;
use App\Http\Controllers\Api\AdminRutaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusController;
use App\Http\Controllers\Api\OperadorController;
use App\Http\Controllers\Api\OperadorRutaController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/change-initial-password', [AuthController::class, 'changeInitialPassword']);

    Route::middleware(['password.changed', 'role:empresario', 'operator.active'])
        ->prefix('operador')
        ->group(function (): void {
            Route::get('/me', [OperadorController::class, 'me']);
            Route::post('/', [OperadorController::class, 'store']);
            Route::put('/{operador}', [OperadorController::class, 'update']);

            Route::get('/rutas', [OperadorRutaController::class, 'index']);
            Route::post('/rutas', [OperadorRutaController::class, 'store']);
            Route::patch('/rutas/{operadorRuta}/toggle-status', [OperadorRutaController::class, 'toggleStatus']);
            Route::delete('/rutas/{operadorRuta}', [OperadorRutaController::class, 'destroy']);

            Route::get('/buses', [BusController::class, 'index']);
            Route::post('/buses', [BusController::class, 'store']);
            Route::put('/buses/{bus}', [BusController::class, 'update']);
            Route::patch('/buses/{bus}/toggle-status', [BusController::class, 'toggleStatus']);
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
