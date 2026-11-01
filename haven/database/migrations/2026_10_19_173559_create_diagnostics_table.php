<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();

            // ── Audio capture (Stage 4) ──
            $table->string('audio_path')->nullable()->comment('Stored audio file (webm/wav/mp3)');
            $table->unsignedSmallInteger('audio_duration_sec')->nullable()->comment('Duration in seconds');
            $table->string('audio_original_name')->nullable();

            // ── Vibration data (Stage 4) ──
            $table->string('vibration_path')->nullable()->comment('Uploaded CSV/JSON from ESP32');
            $table->unsignedInteger('vibration_sample_count')->nullable();
            $table->float('vibration_peak_g')->nullable()->comment('Peak acceleration (g)');
            $table->float('vibration_rms_g')->nullable()->comment('RMS acceleration (g)');

            // ── Session metadata ──
            $table->enum('status', ['draft', 'submitted', 'processing', 'completed', 'failed'])
                  ->default('draft');
            $table->text('notes')->nullable();

            // ── ML result (Stage 5) ──
            $table->string('ml_condition')->nullable()->comment('e.g. normal, knocking, misfiring');
            $table->float('ml_confidence')->nullable()->comment('0.0 – 1.0');
            $table->json('ml_result_raw')->nullable()->comment('Full FastAPI response payload');
            $table->timestamp('ml_processed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostics');
    }
};
