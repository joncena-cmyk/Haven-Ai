<?php

use App\Http\Controllers\Api\TelemetryIngestController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Stage 8 & ISO Security — ESP32 Wi-Fi IoT Telemetry Ingestion API
Route::prefix('v1/telemetry')->group(function () {
    Route::get('/ping', [TelemetryIngestController::class, 'ping'])->name('api.telemetry.ping');

    Route::middleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        'auth:sanctum,web',
    ])->group(function () {
        Route::get('/latest', [TelemetryIngestController::class, 'latest'])->name('api.telemetry.latest');
        Route::get('/stream', [TelemetryIngestController::class, 'stream'])->name('api.telemetry.stream');
        Route::get('/sketch', [TelemetryIngestController::class, 'sketch'])->name('api.telemetry.sketch');
    });

    Route::post('/ingest', [TelemetryIngestController::class, 'ingest'])
        ->middleware('throttle:telemetry-ingest')
        ->name('api.telemetry.ingest');
});
