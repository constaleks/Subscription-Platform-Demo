<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $status = 'ok';
    $database = 'ok';

    try {
        DB::connection()->getPdo();
    } catch (\Throwable $e) {
        $status = 'error';
        $database = 'unreachable';
    }

    return response()->json([
        'status' => $status,
        'app' => config('app.name'),
        'environment' => config('app.env'),
        'database' => $database,
        'timestamp' => now()->toISOString(),
    ], $status === 'ok' ? 200 : 503);
});