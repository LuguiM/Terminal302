<?php

use App\Http\Middleware\EnsureInitialPasswordChanged;
use App\Http\Middleware\EnsureOperadorIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(null);
        $middleware->alias([
            'operator.active' => EnsureOperadorIsActive::class,
            'password.changed' => EnsureInitialPasswordChanged::class,
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e): bool {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'No se ha autenticado.'], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'No tiene permisos para realizar esta acción.'], 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'El recurso solicitado no existe.'], 404);
            }
        });

        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'La sesión ha expirado.'], 419);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $messages = [
                400 => 'La solicitud no es válida.',
                401 => 'No se ha autenticado.',
                403 => 'No tiene permisos para realizar esta acción.',
                404 => 'El recurso solicitado no existe.',
                405 => 'El método utilizado no está permitido para esta ruta.',
                408 => 'La solicitud tardó demasiado tiempo.',
                413 => 'El contenido enviado supera el tamaño permitido.',
                419 => 'La sesión ha expirado.',
                422 => 'Los datos proporcionados no son válidos.',
                429 => 'Se realizaron demasiadas solicitudes. Intente nuevamente más tarde.',
                500 => 'Ocurrió un error interno. Intente nuevamente más tarde.',
                503 => 'El servicio no está disponible temporalmente.',
            ];
            $status = $e->getStatusCode();

            return response()->json([
                'message' => $messages[$status] ?? 'No se pudo completar la solicitud.',
            ], $status, $e->getHeaders());
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (($request->is('api/*') || $request->expectsJson())
                && ! $e instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'message' => 'Ocurrió un error interno. Intente nuevamente más tarde.',
                ], 500);
            }
        });
    })->create();
