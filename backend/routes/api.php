<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminHorarioController;
use App\Http\Controllers\Api\AdminMenuRutaController;
use App\Http\Controllers\Api\AdminOperadorController;
use App\Http\Controllers\Api\AdminRoleController;
use App\Http\Controllers\Api\AdminRutaController;
use App\Http\Controllers\Api\AdminTicketPlantillaController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusController;
use App\Http\Controllers\Api\MeMenuRutaController;
use App\Http\Controllers\Api\OperadorController;
use App\Http\Controllers\Api\OperadorDashboardController;
use App\Http\Controllers\Api\OperadorEmpleadoController;
use App\Http\Controllers\Api\OperadorHorarioController;
use App\Http\Controllers\Api\OperadorRutaController;
use App\Http\Controllers\Api\PublicRutaController;
use App\Http\Controllers\Api\PublicTicketController;
use App\Http\Controllers\Api\ValidadorTicketController;
use App\Http\Controllers\Api\ValidadorValidacionController;
use App\Http\Controllers\Api\VendedorTicketController;
use App\Http\Controllers\Api\VendedorVentaHorarioController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::prefix('public')->group(function (): void {
    Route::get('/rutas', [PublicRutaController::class, 'index']);
    Route::get('/rutas/{ruta}/horarios', [PublicRutaController::class, 'horariosPorRuta']);
    Route::get('/tickets/{codigoTicket}', [PublicTicketController::class, 'showByCode']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/me/menu-rutas', [MeMenuRutaController::class, 'index']);
    Route::post('/change-initial-password', [AuthController::class, 'changeInitialPassword']);

    Route::middleware(['password.changed', 'role:empresario'])
        ->prefix('operador')
        ->group(function (): void {
            Route::post('/', [OperadorController::class, 'store']);

            Route::middleware('operator.active')->group(function (): void {
                Route::get('/me', [OperadorController::class, 'me']);
                Route::get('/dashboard/flujo-pasajeros', [OperadorDashboardController::class, 'flujoPasajeros']);
                Route::put('/{operador}', [OperadorController::class, 'update']);

                Route::get('/empleados', [OperadorEmpleadoController::class, 'index']);
                Route::post('/empleados', [OperadorEmpleadoController::class, 'store']);
                Route::put('/empleados/{empleado}', [OperadorEmpleadoController::class, 'update']);
                Route::patch('/empleados/{empleado}/toggle-status', [OperadorEmpleadoController::class, 'toggleStatus']);

                Route::get('/rutas-disponibles', [OperadorRutaController::class, 'rutasDisponibles']);
                Route::get('/rutas', [OperadorRutaController::class, 'index']);
                Route::post('/rutas', [OperadorRutaController::class, 'store']);
                Route::patch('/rutas/{operadorRuta}/toggle-status', [OperadorRutaController::class, 'toggleStatus']);
                Route::delete('/rutas/{operadorRuta}', [OperadorRutaController::class, 'destroy']);

                Route::get('/tipo-buses', [BusController::class, 'tipoBuses']);
                Route::get('/buses', [BusController::class, 'index']);
                Route::post('/buses', [BusController::class, 'store']);
                Route::put('/buses/{bus}', [BusController::class, 'update']);
                Route::patch('/buses/{bus}/toggle-status', [BusController::class, 'toggleStatus']);

                Route::get('/horarios/rutas', [OperadorHorarioController::class, 'rutas']);
                Route::get('/horarios/rutas/{ruta}', [OperadorHorarioController::class, 'diasPorRuta']);
                Route::get('/horarios', [OperadorHorarioController::class, 'horariosPorRutaYDia']);
            });
        });

    Route::middleware(['password.changed', 'role:vendedor'])
        ->prefix('vendedor')
        ->group(function (): void {
            Route::get('/rutas-disponibles', [VendedorVentaHorarioController::class, 'rutasDisponibles']);
            Route::get('/rutas/{ruta}/horarios-disponibles', [VendedorVentaHorarioController::class, 'horariosDisponiblesPorRuta']);
            Route::patch('/ventas-horarios/{ventaHorario}/cerrar', [VendedorVentaHorarioController::class, 'cerrar']);
            Route::get('/tipo-envios', [VendedorTicketController::class, 'tipoEnvios']);
            Route::get('/tickets', [VendedorTicketController::class, 'index']);
            Route::get('/tickets/entregas', [VendedorTicketController::class, 'entregas']);
            Route::post('/tickets', [VendedorTicketController::class, 'store']);
            Route::post('/tickets/{id}/retry-processing', [VendedorTicketController::class, 'retryProcessing']);
            Route::get('/tickets/{id}/template-image', [VendedorTicketController::class, 'templateImage']);
            Route::get('/tickets/{id}/image', [VendedorTicketController::class, 'image']);
            Route::get('/tickets/{id}/print', [VendedorTicketController::class, 'print']);
        });

    Route::middleware(['password.changed', 'role:validador'])
        ->prefix('validador')
        ->group(function (): void {
            Route::post('/tickets/validar', [ValidadorTicketController::class, 'validar']);
            Route::get('/validaciones', [ValidadorValidacionController::class, 'index']);
        });

    Route::middleware(['password.changed', 'role:administrador'])
        ->prefix('admin')
        ->group(function (): void {
            Route::get('/dashboard/flujo-pasajeros', [AdminDashboardController::class, 'flujoPasajeros']);

            Route::get('/roles', [AdminRoleController::class, 'index']);

            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::get('/users/{user}', [AdminUserController::class, 'show']);
            Route::put('/users/{user}', [AdminUserController::class, 'update']);
            Route::patch('/users/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
            Route::patch('/users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus']);

            Route::get('/operadores', [AdminOperadorController::class, 'index']);
            Route::get('/operadores/{operador}', [AdminOperadorController::class, 'show']);
            Route::get('/operadores/{operador}/empleados', [AdminOperadorController::class, 'empleados']);
            Route::get('/operadores/{operador}/buses', [AdminOperadorController::class, 'buses']);
            Route::get('/operadores/{operador}/rutas', [AdminOperadorController::class, 'rutas']);
            Route::patch('/operadores/{operador}/toggle-status', [AdminOperadorController::class, 'toggleStatus']);

            Route::get('/rutas', [AdminRutaController::class, 'index']);
            Route::post('/rutas', [AdminRutaController::class, 'store']);
            Route::put('/rutas/{ruta}', [AdminRutaController::class, 'update']);
            Route::patch('/rutas/{ruta}/toggle-status', [AdminRutaController::class, 'toggleStatus']);
            Route::delete('/rutas/{ruta}', [AdminRutaController::class, 'destroy']);

            Route::get('/menu-rutas', [AdminMenuRutaController::class, 'index']);
            Route::post('/menu-rutas', [AdminMenuRutaController::class, 'store']);
            Route::put('/menu-rutas/{menuRuta}', [AdminMenuRutaController::class, 'update']);
            Route::patch('/menu-rutas/{menuRuta}/toggle-status', [AdminMenuRutaController::class, 'toggleStatus']);

            Route::get('/ticket-plantillas', [AdminTicketPlantillaController::class, 'index']);
            Route::post('/ticket-plantillas', [AdminTicketPlantillaController::class, 'store']);
            Route::get('/ticket-plantillas/{ticketPlantilla}/download', [AdminTicketPlantillaController::class, 'download']);
            Route::get('/ticket-plantillas/{ticketPlantilla}', [AdminTicketPlantillaController::class, 'show']);
            Route::put('/ticket-plantillas/{ticketPlantilla}', [AdminTicketPlantillaController::class, 'update']);
            Route::delete('/ticket-plantillas/{ticketPlantilla}', [AdminTicketPlantillaController::class, 'destroy']);
            Route::patch('/ticket-plantillas/{ticketPlantilla}/toggle-status', [AdminTicketPlantillaController::class, 'toggleStatus']);
            Route::patch('/ticket-plantillas/{ticketPlantilla}/set-default', [AdminTicketPlantillaController::class, 'setDefault']);

            Route::get('/horarios/dias', [AdminHorarioController::class, 'dias']);
            Route::get('/horarios/rutas', [AdminHorarioController::class, 'rutas']);
            Route::get('/horarios/rutas/{ruta}', [AdminHorarioController::class, 'diasPorRuta']);
            Route::get('/horarios/rutas/{ruta}/operadores', [AdminHorarioController::class, 'operadoresPorRuta']);
            Route::get('/horarios/buses', [AdminHorarioController::class, 'busesPorRutaYOperador']);
            Route::get('/horarios', [AdminHorarioController::class, 'horariosPorRutaYDia']);
            Route::post('/horarios', [AdminHorarioController::class, 'store']);
            Route::put('/horarios/{horario}', [AdminHorarioController::class, 'update']);
            Route::patch('/horarios/{horario}/toggle-status', [AdminHorarioController::class, 'toggleStatus']);
            Route::delete('/horarios/{horario}', [AdminHorarioController::class, 'destroy']);
        });
});
