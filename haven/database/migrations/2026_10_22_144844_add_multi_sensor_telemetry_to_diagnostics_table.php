<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds multi-sensor hardware telemetry columns for ESP32 automotive DAQ:
     * - DS18B20 Temperature Sensor (Pin D4)
     * - Hall Effect RPM Sensor (Pin D23)
     * - MPU6050 Accelerometer / Gyroscope (Pins D21 / D22)
     * - INA219 Voltage / Current Monitor (Pins D21 / D22)
     * - INMP441 I2S MEMS Microphone (Pins D18 / D19 / D5)
     */
    public function up(): void
    {
        Schema::table('diagnostics', function (Blueprint $table) {
            $table->float('temperature_c')->nullable()->after('vibration_rms_g')->comment('Engine/Coolant temperature from DS18B20 (D4)');
            $table->unsignedInteger('rpm')->nullable()->after('temperature_c')->comment('Engine RPM from Hall Effect Sensor (D23)');
            $table->float('voltage_v')->nullable()->after('rpm')->comment('Battery/Alternator DC voltage from INA219 (D21/D22)');
            $table->float('current_ma')->nullable()->after('voltage_v')->comment('Electrical current draw in mA from INA219 (D21/D22)');
            $table->json('sensor_data')->nullable()->after('current_ma')->comment('Full structured ESP32 multi-sensor telemetry payload');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diagnostics', function (Blueprint $table) {
            $table->dropColumn([
                'temperature_c',
                'rpm',
                'voltage_v',
                'current_ma',
                'sensor_data',
            ]);
        });
    }
};
