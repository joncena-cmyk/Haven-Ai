<?php

use App\Http\Controllers\DiagnosticController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleLookupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/terms', function () {
    return view('terms');
})->name('terms');

Route::get('/privacy', function () {
    return view('privacy');
})->name('privacy');

// Stage 7 — Public Learning Modules & Knowledge Hub (Guest + Authenticated)
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
Route::post('learning/quiz/submit', [\App\Http\Controllers\LearningController::class, 'submitQuiz'])
     ->middleware(['auth:web'])
     ->name('learning.quiz.submit');
Route::post('learning/{slug}/complete', [\App\Http\Controllers\LearningController::class, 'markCompleted'])
     ->middleware(['auth:web'])
     ->name('learning.complete');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth:web'])->name('dashboard');

Route::middleware(['auth:web'])->group(function () {

    // Global AI Assistant (Authenticated & Rate-Limited per user)
    Route::post('ai/ask', [\App\Http\Controllers\AiAssistantController::class, 'ask'])
         ->middleware(['throttle:ai-ask'])
         ->name('ai.ask');

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
         ->middleware(['throttle:ai-ask'])
         ->name('diagnostics.ai.ask');

    // Owner-checked private media streaming routes
    Route::get('diagnostics/{diagnostic}/audio', [DiagnosticController::class, 'streamAudio'])
         ->name('diagnostics.audio');
    Route::get('diagnostics/{diagnostic}/vibration', [DiagnosticController::class, 'streamVibration'])
         ->name('diagnostics.vibration');


    // Stage 6 — Report
    Route::get('diagnostics/{diagnostic}/report', [DiagnosticController::class, 'report'])
         ->name('diagnostics.report');
});

// ISO/IEC 27001 Admin Console — dedicated `admin` guard, separate login, no public registration
Route::prefix('admin')->group(function () {
    Route::get('login', [\App\Http\Controllers\Admin\AuthController::class, 'create'])
         ->name('admin.login');
    Route::post('login', [\App\Http\Controllers\Admin\AuthController::class, 'store'])
         ->middleware('throttle:20,1')
         ->name('admin.login.store');
    Route::match(['get', 'post'], 'logout', [\App\Http\Controllers\Admin\AuthController::class, 'destroy'])
         ->name('admin.logout');

    Route::middleware('admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])
             ->name('admin.dashboard');

        Route::get('audit-logs', [\App\Http\Controllers\AdminAuditController::class, 'index'])
             ->name('admin.audit.index');
        Route::get('audit-logs/export', [\App\Http\Controllers\AdminAuditController::class, 'export'])
             ->name('admin.audit.export');

        Route::get('users', [\App\Http\Controllers\Admin\UserController::class, 'index'])
             ->name('admin.users.index');
        Route::get('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'show'])
             ->name('admin.users.show');

        // Machine Learning & Model Pipeline Monitor (Pillar 4)
        Route::get('ml', [\App\Http\Controllers\Admin\MlManagementController::class, 'index'])
             ->name('admin.ml.index');
        Route::get('ml/export', [\App\Http\Controllers\Admin\MlManagementController::class, 'exportDataset'])
             ->name('admin.ml.export');
        Route::post('ml/retrain', [\App\Http\Controllers\Admin\MlManagementController::class, 'retrain'])
             ->name('admin.ml.retrain');

        // Administrator Security Settings & Multi-Admin Management
        Route::get('security', [\App\Http\Controllers\Admin\SecurityController::class, 'index'])
             ->name('admin.security.index');
        Route::match(['post', 'put'], 'security/password', [\App\Http\Controllers\Admin\SecurityController::class, 'updatePassword'])
             ->name('admin.security.password');
        Route::post('security/admins', [\App\Http\Controllers\Admin\SecurityController::class, 'storeAdmin'])
             ->name('admin.security.store');

        Route::middleware('throttle:30,1')->group(function () {
            Route::post('users/{user}/suspend', [\App\Http\Controllers\Admin\UserController::class, 'suspend'])
                 ->name('admin.users.suspend');
            Route::post('users/{user}/unsuspend', [\App\Http\Controllers\Admin\UserController::class, 'unsuspend'])
                 ->name('admin.users.unsuspend');
        });
    });
});

require __DIR__.'/auth.php';
