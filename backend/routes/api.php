<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\MonitorCheckController;
use App\Http\Controllers\MonitorIncidentController;
use App\Http\Controllers\MonitorStatsController;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('monitors', MonitorController::class);

        Route::prefix('monitors/{monitor}')->group(function () {
            Route::post('pause', [MonitorController::class, 'pause']);
            Route::post('resume', [MonitorController::class, 'resume']);

            Route::get('checks', [MonitorCheckController::class, 'index']);
            Route::get('incidents', [MonitorIncidentController::class, 'index']);
            Route::get('stats', MonitorStatsController::class);
        });
    });
});