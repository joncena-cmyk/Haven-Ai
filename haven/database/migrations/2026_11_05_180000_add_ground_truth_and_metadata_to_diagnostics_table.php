<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds ground-truth labeling and session metadata fields to diagnostics
     * so model training and evaluation strictly use verified ground-truth labels
     * (`true_condition` + `is_training_sample = true`) rather than model predictions (`ml_condition`).
     */
    public function up(): void
    {
        Schema::table('diagnostics', function (Blueprint $table) {
            $table->enum('true_condition', ['normal', 'misfire', 'loose_component', 'valve_clearance'])
                  ->nullable()
                  ->after('notes')
                  ->comment('Verified ground-truth engine condition label for supervised training');
            $table->string('session_id', 100)
                  ->nullable()
                  ->index()
                  ->after('true_condition')
                  ->comment('Recording session identifier grouping captures');
            $table->text('fault_detail')
                  ->nullable()
                  ->after('session_id')
                  ->comment('Specific mechanical fault description or diagnostic notes');
            $table->unsignedInteger('target_rpm')
                  ->nullable()
                  ->after('fault_detail')
                  ->comment('Target engine speed in RPM during capture');
            $table->string('load_state', 100)
                  ->nullable()
                  ->after('target_rpm')
                  ->comment('Engine load condition (e.g., idle_neutral, ac_on, rev_hold)');
            $table->string('sensor_position', 150)
                  ->nullable()
                  ->after('load_state')
                  ->comment('Mounting location/orientation of accelerometer on engine');
            $table->string('mic_position', 150)
                  ->nullable()
                  ->after('sensor_position')
                  ->comment('Microphone placement position and distance');
            $table->string('operator', 150)
                  ->nullable()
                  ->after('mic_position')
                  ->comment('Name/ID of operator who recorded the session');
            $table->string('verified_by', 150)
                  ->nullable()
                  ->after('operator')
                  ->comment('Mechanic or engineer who verified the ground-truth condition');
            $table->unsignedInteger('sample_rate_hz')
                  ->nullable()
                  ->after('verified_by')
                  ->comment('Actual vibration sensor sampling rate in Hz');
            $table->boolean('is_training_sample')
                  ->default(false)
                  ->after('sample_rate_hz')
                  ->comment('Whether this session is approved for ML training/evaluation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diagnostics', function (Blueprint $table) {
            $table->dropIndex(['session_id']);
            $table->dropColumn([
                'true_condition',
                'session_id',
                'fault_detail',
                'target_rpm',
                'load_state',
                'sensor_position',
                'mic_position',
                'operator',
                'verified_by',
                'sample_rate_hz',
                'is_training_sample',
            ]);
        });
    }
};

