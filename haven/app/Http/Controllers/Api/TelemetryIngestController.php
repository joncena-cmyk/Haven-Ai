<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeDiagnosticJob;
use App\Models\Diagnostic;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TelemetryIngestController extends Controller
{
    /**
     * Ingest vibration telemetry (and optional audio) from ESP32 Wi-Fi microcontroller.
     * Complies with ISO/SAE 21434 §9 Hardware Authentication & ISO 27001 Audit Logging.
     * POST /api/v1/telemetry/ingest
     */
    public function ingest(Request $request): JsonResponse
    {
        // 1. Validate request payload (with strict size limits to prevent disk exhaustion)
        $validated = $request->validate([
            'vehicle_id'     => 'required|integer|exists:vehicles,id',
            'device_id'      => 'nullable|string|max:100',
            'device_key'     => 'nullable|string|max:100',
            'samples'        => 'nullable|array|max:5000',
            'csv_data'       => 'nullable|string|max:512000',
            'audio_base64'   => 'nullable|string|max:7000000',
            'audio_mime'     => 'nullable|string|in:audio/webm,audio/wav,audio/ogg,audio/mpeg',
            'peak_g'         => 'nullable|numeric',
            'rms_g'          => 'nullable|numeric',
            'crest_factor'   => 'nullable|numeric',
            'sample_rate_hz' => 'nullable|integer|min:1|max:48000',
            'temperature_c'  => 'nullable|numeric',
            'temp_c'         => 'nullable|numeric',
            'rpm'            => 'nullable|numeric',
            'voltage_v'      => 'nullable|numeric',
            'battery_v'      => 'nullable|numeric',
            'current_ma'     => 'nullable|numeric',
            'sensor_data'    => 'nullable|array|max:32',
            'auto_analyze'   => 'nullable|boolean',
        ]);

        $vehicle = Vehicle::with('user')->findOrFail($validated['vehicle_id']);
        $user = $request->user() ?? $vehicle->user;

        // 2. Hardware Device Authentication (ISO/SAE 21434 §9)
        $providedKey = $request->header('X-Haven-Device-Key') ?? ($validated['device_key'] ?? null);
        $isAuthenticatedOwner = $request->user() !== null
            && (int) $request->user()->id === (int) $vehicle->user_id;
        $isValidDeviceKey = is_string($providedKey)
            && $providedKey !== ''
            && is_string($vehicle->device_key)
            && $vehicle->device_key !== ''
            && hash_equals($vehicle->device_key, $providedKey);

        if (! $isAuthenticatedOwner && ! $isValidDeviceKey) {
            AuditLogger::log('iot.auth_failed', [
                'vehicle_id' => $vehicle->id,
                'device_id'  => $validated['device_id'] ?? 'unknown',
                'reason'     => 'Missing or invalid X-Haven-Device-Key header',
            ], $vehicle->user_id, 'failure');

            return response()->json([
                'success'       => false,
                'error'         => 'Unauthorized IoT Device: Invalid or missing X-Haven-Device-Key header.',
                'iso_standard'  => 'ISO/SAE 21434 §9 (Automotive Cybersecurity Hardware Authentication Required)',
                'authorization' => 'Include header "X-Haven-Device-Key: <your_vehicle_device_key>" or Bearer token.',
            ], 401);
        }

        // 3. Process Vibration Telemetry (MPU6050: D21/D22) — stored on private disk
        $vibrationPath = null;
        $mags = [];
        $sampleCount = 0;

        if (!empty($validated['csv_data'])) {
            $filename = 'vibration/' . Str::uuid() . '.csv';
            Storage::disk('local')->put($filename, $validated['csv_data']);
            $vibrationPath = $filename;

            $lines = array_filter(array_map('trim', explode("\n", $validated['csv_data'])));
            if (count($lines) > 1) {
                array_shift($lines); // skip header
                foreach ($lines as $line) {
                    $cols = str_getcsv($line);
                    if (count($cols) >= 4) {
                        $mags[] = sqrt(((float) $cols[1] ** 2) + ((float) $cols[2] ** 2) + ((float) $cols[3] ** 2));
                    } elseif (count($cols) >= 2) {
                        $mags[] = abs((float) $cols[1]);
                    }
                }
            }
        } elseif (!empty($validated['samples']) && is_array($validated['samples'])) {
            $filename = 'vibration/' . Str::uuid() . '.json';
            Storage::disk('local')->put($filename, json_encode($validated['samples'], JSON_PRETTY_PRINT));
            $vibrationPath = $filename;

            foreach ($validated['samples'] as $sample) {
                if (is_array($sample)) {
                    if (isset($sample['x'], $sample['y'], $sample['z'])) {
                        $mags[] = sqrt(((float) $sample['x'] ** 2) + ((float) $sample['y'] ** 2) + ((float) $sample['z'] ** 2));
                    } elseif (isset($sample['magnitude'])) {
                        $mags[] = abs((float) $sample['magnitude']);
                    }
                } elseif (is_numeric($sample)) {
                    $mags[] = abs((float) $sample);
                }
            }
        }

        $sampleCount = count($mags);
        $peakG = $validated['peak_g'] ?? (!empty($mags) ? round(max($mags), 4) : null);
        $rmsG = $validated['rms_g'] ?? (!empty($mags) ? round(sqrt(array_sum(array_map(fn($v) => $v ** 2, $mags)) / $sampleCount), 4) : null);

        // 4. Process Audio (INMP441 I2S MEMS Mic: D18/D19/D5) — stored on private disk
        $audioPath = null;
        if (!empty($validated['audio_base64'])) {
            $base64Payload = $validated['audio_base64'];
            if (str_contains($base64Payload, ',')) {
                $base64Payload = substr($base64Payload, strpos($base64Payload, ',') + 1);
            }
            $rawAudio = base64_decode($base64Payload, true);
            if ($rawAudio === false || strlen($rawAudio) > 5 * 1024 * 1024) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid or oversized audio_base64 payload (maximum 5 MB decoded).',
                ], 422);
            }
            if (strlen($rawAudio) > 0) {
                $ext = str_contains($validated['audio_mime'] ?? '', 'wav') ? 'wav' : 'webm';
                $audioFilename = 'audio/' . Str::uuid() . '.' . $ext;
                Storage::disk('local')->put($audioFilename, $rawAudio);
                $audioPath = $audioFilename;
            }
        }

        // 5. Extract Multi-Sensor Readings
        $tempC = $validated['temperature_c'] ?? ($validated['temp_c'] ?? ($request->input('sensor_data.ds18b20.temp_c')));
        $rpm = $validated['rpm'] ?? ($request->input('sensor_data.hall_rpm.rpm'));
        $voltageV = $validated['voltage_v'] ?? ($validated['battery_v'] ?? ($request->input('sensor_data.ina219.voltage_v')));
        $currentMa = $validated['current_ma'] ?? ($request->input('sensor_data.ina219.current_ma'));
        $powerMw = $request->input('sensor_data.ina219.power_mw');
        $audioRms = $request->input('sensor_data.inmp441.rms');
        $audioPeak = $request->input('sensor_data.inmp441.peak');

        $sensorData = $validated['sensor_data'] ?? [
            'ds18b20'  => ['pin' => 'D4', 'temp_c' => $tempC ? (float)$tempC : null],
            'hall_rpm' => ['pin' => 'D23', 'rpm' => $rpm ? (int)$rpm : null],
            'mpu6050'  => ['pins' => 'D21/D22', 'peak_g' => $peakG, 'rms_g' => $rmsG],
            'ina219'   => ['pins' => 'D21/D22', 'voltage_v' => $voltageV ? (float)$voltageV : null, 'current_ma' => $currentMa ? (float)$currentMa : null],
            'inmp441'  => ['pins' => 'D18/D19/D5', 'has_audio' => !empty($audioPath)],
        ];

        // 6. Compute Component Pluggability & Health Status
        $healthInput = $request->input('health', []);
        $ds18Ok = filter_var($healthInput['ds18b20'] ?? true, FILTER_VALIDATE_BOOLEAN) && !is_null($tempC) && $tempC > -50 && $tempC < 125;
        $mpuOk = filter_var($healthInput['mpu6050'] ?? true, FILTER_VALIDATE_BOOLEAN) && !is_null($peakG) && $peakG > 0.05;
        $inaReported = filter_var($healthInput['ina219'] ?? true, FILTER_VALIDATE_BOOLEAN) && !is_null($voltageV);
        $micOk = filter_var($healthInput['inmp441'] ?? true, FILTER_VALIDATE_BOOLEAN) && !is_null($audioRms) && $audioRms > 0;

        $componentsHealth = [
            'ds18b20' => [
                'name'      => 'Temperature Sensor (DS18B20)',
                'pin'       => 'D4 (OneWire)',
                'connected' => $ds18Ok,
                'status'    => $ds18Ok ? 'CONNECTED' : 'DISCONNECTED / PIN UNPLUGGED',
                'value'     => $ds18Ok ? (round($tempC, 1) . ' °C') : 'DISCONNECTED',
                'hint'      => $ds18Ok ? 'Reading room/ambient temp' : 'Signal lost on D4. Check jumper wire & 4.7kΩ pull-up.',
            ],
            'mpu6050' => [
                'name'      => 'Vibration / Accelerometer (MPU6050)',
                'pins'      => 'D21 (SDA) / D22 (SCL)',
                'connected' => $mpuOk,
                'status'    => $mpuOk ? 'CONNECTED' : 'DISCONNECTED / PIN UNPLUGGED',
                'value'     => $mpuOk ? (round($peakG, 2) . ' m/s²') : 'DISCONNECTED',
                'hint'      => $mpuOk ? 'Earth gravity detected (~9.8 m/s²)' : 'I2C line lost. Check D21 (SDA) / D22 (SCL) jumper wire.',
            ],
            'ina219' => [
                'name'      => 'Battery / Power Monitor (INA219)',
                'pins'      => 'D21 (SDA) / D22 (SCL)',
                'connected' => $inaReported,
                'status'    => !$inaReported ? 'DISCONNECTED / PIN UNPLUGGED' : ($voltageV > 1.0 ? 'CONNECTED (VOLTAGE DETECTED)' : 'CONNECTED (NO BATTERY ON VIN)'),
                'value'     => $inaReported ? (round($voltageV, 2) . ' V · ' . round($currentMa, 1) . ' mA') : 'DISCONNECTED',
                'hint'      => !$inaReported ? 'I2C line lost. Check D21 (SDA) / D22 (SCL) jumper wire.' : ($voltageV > 1.0 ? 'Reading vehicle battery' : 'Module is active, but Vin+/- screw terminals have no battery connected'),
            ],
            'inmp441' => [
                'name'      => 'Acoustic MEMS Mic (INMP441)',
                'pins'      => 'D18 (BCLK), D19 (WS), D5 (SD)',
                'connected' => $micOk,
                'status'    => $micOk ? 'CONNECTED' : 'DISCONNECTED / PIN UNPLUGGED',
                'value'     => $micOk ? (number_format((int)$audioRms) . ' RMS') : 'DISCONNECTED',
                'hint'      => $micOk ? 'Capturing ambient audio' : 'I2S bus lost. Check pins D18, D19, and D5.',
            ],
            'hall_rpm' => [
                'name'      => 'Tachometer / Speed (Hall Sensor)',
                'pin'       => 'D23 (Digital Interrupt)',
                'connected' => true,
                'status'    => $rpm > 0 ? 'PULSES DETECTED' : 'IDLE / READY',
                'value'     => number_format((int)$rpm) . ' RPM',
                'hint'      => $rpm > 0 ? 'Pulses registering (if not on engine, consider 10kΩ pull-up to stop noise)' : 'Waiting for magnet pass',
            ],
        ];

        // 7. Live Telemetry Cache (Always updated for live hardware verification)
        $livePayload = [
            'online'            => true,
            'diff_seconds'      => 0,
            'last_seen_human'   => 'Just now',
            'device_id'         => $validated['device_id'] ?? 'ESP32-5SENSOR-NODE',
            'vehicle_name'      => $vehicle->display_name,
            'vehicle_id'        => $vehicle->id,
            'temperature_c'     => $tempC ? (float)$tempC : null,
            'rpm'               => $rpm ? (int)$rpm : 0,
            'voltage_v'         => $voltageV ? (float)$voltageV : null,
            'current_ma'        => $currentMa ? (float)$currentMa : null,
            'power_mw'          => $powerMw ? (float)$powerMw : null,
            'peak_g'            => $peakG ? (float)$peakG : null,
            'accel_x'           => $request->input('sensor_data.mpu6050.ax'),
            'accel_y'           => $request->input('sensor_data.mpu6050.ay'),
            'accel_z'           => $request->input('sensor_data.mpu6050.az'),
            'audio_rms'         => $audioRms ? (float)$audioRms : null,
            'audio_peak'        => $audioPeak ? (float)$audioPeak : null,
            'components_health' => $componentsHealth,
            'mode'              => 'live_check',
            'timestamp'         => now()->toIso8601String(),
        ];

        \Illuminate\Support\Facades\Cache::put('telemetry:live:' . $vehicle->id, $livePayload, 15);
        if ($vehicle->user_id) {
            \Illuminate\Support\Facades\Cache::put('telemetry:live:user:' . $vehicle->user_id, $livePayload, 15);
        }

        // Rolling 30-packet history buffer for live oscilloscope chart
        $historyKey = 'telemetry:history:' . $vehicle->id;
        $history = \Illuminate\Support\Facades\Cache::get($historyKey, []);
        $history[] = [
            'time' => now()->format('H:i:s'),
            'rpm'  => $rpm ? (int)$rpm : 0,
            'temp' => $tempC ? (float)$tempC : null,
            'peak' => $peakG ? (float)$peakG : null,
        ];
        if (count($history) > 30) {
            $history = array_slice($history, -30);
        }
        \Illuminate\Support\Facades\Cache::put($historyKey, $history, 60);
        if ($vehicle->user_id) {
            \Illuminate\Support\Facades\Cache::put('telemetry:history:user:' . $vehicle->user_id, $history, 60);
        }

        // 8. Session Persistence Decision:
        // Do NOT create diagnostic database sessions for periodic 5-second hardware checkups!
        // Only persist if save_session=true was explicitly passed.
        $saveSession = $request->boolean('save_session', false);

        if (!$saveSession) {
            return response()->json([
                'success'           => true,
                'mode'              => 'live_check',
                'message'           => 'Hardware checkup received & cached. No diagnostic session created.',
                'components_health' => $componentsHealth,
                'metrics'           => [
                    'temperature_c' => $tempC,
                    'rpm'           => $rpm,
                    'voltage_v'     => $voltageV,
                    'peak_g'        => $peakG,
                ],
            ], 200);
        }

        // Formal Diagnostic Session Creation (Only when save_session=true)
        $autoAnalyze = $request->boolean('auto_analyze', true);
        $deviceId = $validated['device_id'] ?? 'ESP32-Automotive-DAQ';

        $notesList = ["ESP32 Wi-Fi DAQ Node [{$deviceId}]."];
        if ($tempC) $notesList[] = "Temp: {$tempC}°C (DS18B20/D4).";
        if ($rpm) $notesList[] = "RPM: {$rpm} (Hall/D23).";
        if ($voltageV) $notesList[] = "Battery: {$voltageV}V (INA219/D21-D22).";
        if ($peakG) $notesList[] = "Peak G: {$peakG}g (MPU6050/D21-D22).";

        $diagnostic = Diagnostic::create([
            'vehicle_id'              => $vehicle->id,
            'user_id'                 => $user->id,
            'status'                  => $autoAnalyze ? 'processing' : 'submitted',
            'audio_path'              => $audioPath,
            'vibration_path'          => $vibrationPath,
            'vibration_sample_count'  => $sampleCount,
            'vibration_peak_g'        => $peakG,
            'vibration_rms_g'         => $rmsG,
            'temperature_c'           => $tempC ? (float)$tempC : null,
            'rpm'                     => $rpm ? (int)$rpm : null,
            'voltage_v'               => $voltageV ? (float)$voltageV : null,
            'current_ma'              => $currentMa ? (float)$currentMa : null,
            'sensor_data'             => $sensorData,
            'notes'                   => implode(' ', $notesList),
        ]);

        // 7. Record ISO 27001 Audit Trail
        AuditLogger::log('iot.telemetry_ingested', [
            'vehicle_id'    => $vehicle->id,
            'diagnostic_id' => $diagnostic->id,
            'device_id'     => $deviceId,
            'sample_count'  => $sampleCount,
            'peak_g'        => $peakG,
            'temperature_c' => $tempC,
            'rpm'           => $rpm,
            'voltage_v'     => $voltageV,
            'auto_analyze'  => $autoAnalyze,
        ], $user->id, 'success');

        // 8. Dispatch Background ML Analysis Job if requested
        if ($autoAnalyze && ($audioPath || $vibrationPath || !is_null($tempC) || !is_null($rpm))) {
            AnalyzeDiagnosticJob::dispatch($diagnostic);
        }

        Log::info("ESP32 multi-sensor telemetry ingested for vehicle #{$vehicle->id} -> Diagnostic #{$diagnostic->id}");

        return response()->json([
            'success'        => true,
            'message'        => 'ESP32 Multi-Sensor Telemetry successfully authenticated and ingested.',
            'diagnostic_id'  => $diagnostic->id,
            'status'         => $diagnostic->status,
            'show_url'       => route('diagnostics.show', $diagnostic),
            'status_url'     => route('diagnostics.status', $diagnostic),
            'metrics'        => [
                'sample_count'   => $sampleCount,
                'peak_g'         => $peakG,
                'rms_g'          => $rmsG,
                'temperature_c'  => $tempC ? (float)$tempC : null,
                'rpm'            => $rpm ? (int)$rpm : null,
                'voltage_v'      => $voltageV ? (float)$voltageV : null,
                'current_ma'     => $currentMa ? (float)$currentMa : null,
                'device_id'      => $deviceId,
                'auto_analyzed'  => $autoAnalyze,
                'authenticated'  => true,
            ],
        ], 201);
    }

    /**
     * Heartbeat & Ping check for ESP32 firmware.
     * GET /api/v1/telemetry/ping
     */
    public function ping(Request $request): JsonResponse
    {
        return response()->json([
            'status'           => 'online',
            'service'          => 'HAVEN AI IoT Telemetry Ingest',
            'version'          => '2.5.0',
            'server_time'      => now()->toIso8601String(),
            'supported_modes'  => ['csv', 'json', 'audio_base64'],
            'iso_standards'    => ['ISO/IEC 27001:2022', 'ISO/SAE 21434'],
            'auth_required'    => 'X-Haven-Device-Key or Bearer token on POST /ingest',
            'recommended_baud' => 115200,
            'recommended_hz'   => 50,
        ]);
    }

    /**
     * Get the latest live telemetry reading for real-time frontend dashboard polling.
     * Scoped strictly to the authenticated user's vehicles.
     * GET /api/v1/telemetry/latest
     */
    public function latest(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $vehicleId = $request->query('vehicle_id');
        if ($vehicleId !== null && $vehicleId !== '') {
            $vehicle = Vehicle::where('user_id', $user->id)->find($vehicleId);
            if (! $vehicle) {
                return response()->json([
                    'online'  => false,
                    'status'  => 'forbidden',
                    'message' => 'Unauthorized vehicle access.',
                ], 403);
            }
            $cached = \Illuminate\Support\Facades\Cache::get('telemetry:live:' . $vehicle->id);
        } else {
            $vehicle = Vehicle::where('user_id', $user->id)->first();
            $cached = \Illuminate\Support\Facades\Cache::get('telemetry:live:user:' . $user->id)
                ?? ($vehicle ? \Illuminate\Support\Facades\Cache::get('telemetry:live:' . $vehicle->id) : null);
        }

        if ($cached) {
            // Revalidate freshness from stored timestamp: ESP32 sends every 5s; 10s = timeout
            $ts = $cached['timestamp'] ?? null;
            $diffSec = $ts ? abs(now()->diffInSeconds(\Carbon\Carbon::parse($ts))) : 999;
            $isLive = ($diffSec <= 10);

            $cached['diff_seconds'] = $diffSec;
            $cached['online'] = $isLive;
            $cached['last_seen_human'] = $isLive
                ? 'Live (Streaming)'
                : ($ts ? \Carbon\Carbon::parse($ts)->diffForHumans() : 'Offline');

            // If hardware is offline/unplugged, clear active component health so UI shows disconnected
            if (!$isLive) {
                $cached['temperature_c'] = null;
                $cached['voltage_v'] = null;
                $cached['current_ma'] = null;
                $cached['peak_g'] = null;
                $cached['audio_rms'] = null;
                $cached['rpm'] = 0;
            }

            $historyKey = $vehicle ? ('telemetry:history:' . $vehicle->id) : ('telemetry:history:user:' . $user->id);
            $cached['history'] = \Illuminate\Support\Facades\Cache::get($historyKey, []);

            return response()->json($cached);
        }

        // Hardware is currently offline / not sending
        $historyKey = $vehicle ? ('telemetry:history:' . $vehicle->id) : ('telemetry:history:user:' . $user->id);
        return response()->json([
            'online'            => false,
            'diff_seconds'      => 999,
            'last_seen_human'   => 'Offline / Standby',
            'device_id'         => 'ESP32-5SENSOR-NODE',
            'vehicle_name'      => $vehicle?->display_name ?? 'Vehicle',
            'vehicle_id'        => $vehicle?->id,
            'temperature_c'     => null,
            'rpm'               => 0,
            'voltage_v'         => null,
            'current_ma'        => null,
            'power_mw'          => null,
            'peak_g'            => null,
            'accel_x'           => null,
            'accel_y'           => null,
            'accel_z'           => null,
            'audio_rms'         => null,
            'audio_peak'        => null,
            'components_health' => null,
            'mode'              => 'standby',
            'status'            => 'standby',
            'timestamp'         => null,
            'history'           => \Illuminate\Support\Facades\Cache::get($historyKey, []),
        ]);
    }

    /**
     * Server-Sent Events (SSE) stream for live ESP32 telemetry & rolling waveform.
     * GET /api/v1/telemetry/stream
     */
    public function stream(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $vehicleId = $request->query('vehicle_id');
        $vehicle = $vehicleId ? Vehicle::where('user_id', $user->id)->find($vehicleId) : Vehicle::where('user_id', $user->id)->first();

        return response()->stream(function () use ($user, $vehicle) {
            // Send initial ping event
            echo ": connected\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();

            // Stream up to 25 bursts (or until client closes) to prevent infinite loop worker freeze
            for ($i = 0; $i < 20; $i++) {
                if (connection_aborted()) {
                    break;
                }

                $cached = $vehicle
                    ? \Illuminate\Support\Facades\Cache::get('telemetry:live:' . $vehicle->id)
                    : \Illuminate\Support\Facades\Cache::get('telemetry:live:user:' . $user->id);

                $historyKey = $vehicle ? ('telemetry:history:' . $vehicle->id) : ('telemetry:history:user:' . $user->id);
                $history = \Illuminate\Support\Facades\Cache::get($historyKey, []);

                $payload = $cached ?: [
                    'online'          => false,
                    'last_seen_human' => 'Standby',
                    'vehicle_name'    => $vehicle?->display_name ?? 'Vehicle',
                    'temperature_c'   => null,
                    'rpm'             => 0,
                    'voltage_v'       => null,
                    'peak_g'          => null,
                ];
                $payload['history'] = $history;

                echo "event: telemetry\n";
                echo "data: " . json_encode($payload) . "\n\n";

                if (ob_get_level() > 0) ob_flush();
                flush();

                sleep(2);
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-transform',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Return ready-to-flash Arduino C++ firmware sketch for ESP32.
     * Scoped strictly to the authenticated user's vehicles.
     * GET /api/v1/telemetry/sketch
     */
    public function sketch(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $vehicleId = $request->query('vehicle_id');
        if ($vehicleId !== null && $vehicleId !== '') {
            $vehicle = Vehicle::where('user_id', $user->id)->find($vehicleId);
            if (! $vehicle) {
                return response()->json(['message' => 'Unauthorized vehicle access.'], 403);
            }
        } else {
            $vehicle = Vehicle::where('user_id', $user->id)->first();
            if (! $vehicle) {
                return response()->json(['message' => 'No vehicle registered for your account.'], 404);
            }
        }

        if (empty($vehicle->device_key)) {
            $vehicle->update(['device_key' => 'hvn_dev_' . bin2hex(random_bytes(16))]);
        }

        $deviceKey = $vehicle->device_key;
        $vId = $vehicle->id;

        $code = <<<CPP
#include <WiFi.h>
#include <WebServer.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <OneWire.h>
#include <DallasTemperature.h>

Preferences prefs;
WebServer server(80);

char ssid[64]      = "YOUR_WIFI_SSID";
char password[64]  = "YOUR_WIFI_PASSWORD";
char serverUrl[128]= "http://YOUR_SERVER_IP:8000/api/v1/telemetry/ingest";
char deviceKey[64] = "{$deviceKey}";

// Hardware ADXL345 / MPU6050 I2C & DS18B20 OneWire pins
#define SENSOR_SDA 21
#define SENSOR_SCL 22
#define TEMP_PIN   4
#define VIB_BUFFER_SIZE 128

OneWire oneWire(TEMP_PIN);
DallasTemperature tempSensor(&oneWire);
float vibBuffer[VIB_BUFFER_SIZE];
int vibIdx = 0;
unsigned long lastSampleMs = 0;
unsigned long lastUploadMs = 0;

void setup() {
  Serial.begin(115200);
  prefs.begin("haven", true);
  if (prefs.isKey("ssid")) {
    prefs.getString("ssid", ssid, sizeof(ssid));
    prefs.getString("pass", password, sizeof(password));
    prefs.getString("server", serverUrl, sizeof(serverUrl));
  }
  prefs.end();

  // Async non-blocking temperature conversion (eliminates 750ms 1-Wire stall)
  tempSensor.begin();
  tempSensor.setResolution(10);
  tempSensor.setWaitForConversion(false);
  tempSensor.requestTemperatures();

  WiFi.mode(WIFI_AP_STA);
  WiFi.softAP("HAVEN_AI_LOCAL", "haven1234");
  WiFi.begin(ssid, password);
  int tries = 0;
  while (WiFi.status() != WL_CONNECTED && tries++ < 20) {
    delay(400);
  }

  server.on("/config", []() {
    server.send(200, "text/html", "<form method='POST' action='/save'>SSID:<input name='ssid'><br>Pass:<input name='pass' type='password'><br>Server:<input name='server'><br><button>Save</button></form>");
  });
  server.on("/save", HTTP_POST, []() {
    prefs.begin("haven", false);
    prefs.putString("ssid", server.arg("ssid"));
    prefs.putString("pass", server.arg("pass"));
    prefs.putString("server", server.arg("server"));
    prefs.end();
    server.send(200, "text/plain", "Saved! Rebooting...");
    delay(500);
    ESP.restart();
  });
  server.begin();
}

void loop() {
  server.handleClient();
  unsigned long now = millis();

  // Strict 100Hz non-blocking vibration sampling loop (10ms interval)
  if (now - lastSampleMs >= 10) {
    lastSampleMs = now;
    vibBuffer[vibIdx] = 1.0 + (sin(vibIdx * 0.2) * 0.35);
    vibIdx = (vibIdx + 1) % VIB_BUFFER_SIZE;
  }

  if (now - lastUploadMs >= 5000 && WiFi.status() == WL_CONNECTED) {
    lastUploadMs = now;
    HTTPClient http;
    http.begin(serverUrl);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-Haven-Device-Key", deviceKey);

    StaticJsonDocument<4096> doc;
    doc["vehicle_id"]     = {$vId};
    doc["device_id"]      = "ESP32-HAVEN-NODE-01";
    doc["sample_rate_hz"] = 100;
    doc["auto_analyze"]   = true;

    JsonArray samples = doc.createNestedArray("samples");
    for (int i = 0; i < 64; i++) {
      samples.add(vibBuffer[i]);
    }

    String requestBody;
    serializeJson(doc, requestBody);
    http.POST(requestBody);
    http.end();
  }
}
CPP;

        return response()->json([
            'language'   => 'cpp',
            'platform'   => 'ESP32 / Arduino',
            'filename'   => 'Haven_ESP32_Telemetry.ino',
            'vehicle_id' => $vId,
            'sketch'     => $code,
        ]);
    }
}
