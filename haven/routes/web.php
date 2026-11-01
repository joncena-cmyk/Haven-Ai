<?php

use App\Http\Controllers\DiagnosticController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleLookupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('ai/ask', [\App\Http\Controllers\AiAssistantController::class, 'ask'])
     ->middleware(['throttle:15,1'])
     ->name('ai.ask');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware(['auth'])->group(function () {

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Stage 3 — Vehicle Management
    Route::get('vehicles/catalog/makes', [VehicleLookupController::class, 'makes'])
         ->name('vehicles.catalog.makes');
    Route::get('vehicles/catalog/models', [VehicleLookupController::class, 'models'])
         ->name('vehicles.catalog.models');
    Route::get('vehicles/catalog/vin', [VehicleLookupController::class, 'vin'])
         ->name('vehicles.catalog.vin');

    Route::resource('vehicles', VehicleController::class);
    // IoT Key Management & Alert Thresholds (profile page actions)
    Route::post('vehicles/{vehicle}/regenerate-key', [VehicleController::class, 'regenerateKey'])
         ->name('vehicles.regenerate-key');
    Route::post('vehicles/{vehicle}/thresholds', [VehicleController::class, 'saveThresholds'])
         ->name('vehicles.thresholds');
    Route::post('vehicles/{vehicle}/sketchfab-uid', [VehicleController::class, 'updateSketchfabUid'])
         ->name('vehicles.sketchfab-uid');

    // Stage 4 — Diagnostics
    Route::get('diagnostics/export/dataset', [DiagnosticController::class, 'exportDataset'])
         ->name('diagnostics.export.dataset');
    Route::resource('diagnostics', DiagnosticController::class)
         ->only(['index', 'create', 'store', 'show', 'destroy']);

    // Stage 5 & 8 — ML Analysis, Status & AI Copilot
    Route::post('diagnostics/{diagnostic}/analyze', [DiagnosticController::class, 'analyze'])
         ->name('diagnostics.analyze');
    Route::get('diagnostics/{diagnostic}/status', [DiagnosticController::class, 'status'])
         ->name('diagnostics.status');
    Route::post('diagnostics/{diagnostic}/ai-ask', [\App\Http\Controllers\DiagnosticAiController::class, 'ask'])
         ->middleware(['throttle:15,1'])
         ->name('diagnostics.ai.ask');


    // Stage 6 — Report
    Route::get('diagnostics/{diagnostic}/report', [DiagnosticController::class, 'report'])
         ->name('diagnostics.report');

    // Stage 7 — Learning Modules & Knowledge Hub
    Route::get('learning', [\App\Http\Controllers\LearningController::class, 'index'])
         ->name('learning.index');
    Route::get('learning/dtc', [\App\Http\Controllers\LearningController::class, 'dtc'])
         ->name('learning.dtc');
    Route::get('learning/quiz', [\App\Http\Controllers\LearningController::class, 'quiz'])
         ->name('learning.quiz');
    Route::get('learning/topic/{slug}', [\App\Http\Controllers\LearningController::class, 'topic'])
         ->name('learning.topic');
    Route::get('learning/{slug}', [\App\Http\Controllers\LearningController::class, 'show'])
         ->name('learning.show');
});

require __DIR__.'/auth.php';
