<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'ok',
    ]);
});

Route::get('/docs/api', function () {
    return response((string) file_get_contents(public_path('swagger/index.html')))
        ->header('Content-Type', 'text/html; charset=UTF-8');
});

Route::get('/docs/api/openapi.yaml', function () {
    return response((string) file_get_contents(public_path('swagger/openapi.yaml')))
        ->header('Content-Type', 'application/yaml; charset=UTF-8');
});
