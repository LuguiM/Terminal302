<?php

use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/change-initial-password', [AuthController::class, 'changeInitialPassword']);

    Route::middleware(['password.changed', 'admin'])
        ->prefix('admin')
        ->group(function (): void {
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::get('/users/{user}', [AdminUserController::class, 'show']);
            Route::put('/users/{user}', [AdminUserController::class, 'update']);
            Route::patch('/users/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
            Route::patch('/users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus']);
        });
});
