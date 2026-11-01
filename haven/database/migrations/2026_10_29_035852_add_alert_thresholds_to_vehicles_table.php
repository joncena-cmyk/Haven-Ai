<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // JSON blob for per-vehicle sensor alert thresholds, e.g.:
            // {"temp_max_c": 95, "rpm_max": 5000, "voltage_min_v": 11.5, "vibration_max": 15}
            $table->json('alert_thresholds')->nullable()->after('device_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // JSON blob for per-vehicle sensor alert thresholds, e.g.:
            // {"temp_max_c": 95, "rpm_max": 5000, "voltage_min_v": 11.5, "vibration_max": 15}
            $table->json('alert_thresholds')->nullable()->after('device_key');
        });
    }
};
