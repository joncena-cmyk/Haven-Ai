<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeDiagnosticJob;
use App\Models\Diagnostic;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'device_id' => 'nullable|string|max:100',
            'device_key' => 'nullable|string|max:100',
            'samples' => 'nullable|array|max:5000',
            'csv_data' => 'nullable|string|max:512000',
            'audio_base64' => 'nullable|string|max:7000000',
            'audio_mime' => 'nullable|string|in:audio/webm,audio/wav,audio/ogg,audio/mpeg',
            'peak_g' => 'nullable|numeric',
            'rms_g' => 'nullable|numeric',
            'crest_factor' => 'nullable|numeric',
            'sample_rate_hz' => 'nullable|integer|min:1|max:48000',
            'temperature_c' => 'nullable|numeric',
            'temp_c' => 'nullable|numeric',
            'rpm' => 'nullable|numeric',
            'voltage_v' => 'nullable|numeric',
            'battery_v' => 'nullable|numeric',
            'current_ma' => 'nullable|numeric',
            'sensor_data' => 'nullable|array|max:32',
            'auto_analyze' => 'nullable|boolean',
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
                'device_id' => $validated['device_id'] ?? 'unknown',
                'reason' => 'Missing or invalid X-Haven-Device-Key header',
            ], $vehicle->user_id, 'failure');

            return response()->json([
                'success' => false,
                'error' => 'Unauthorized IoT Device: Invalid or missing X-Haven-Device-Key header.',
                'iso_standard' => 'ISO/SAE 21434 §9 (Automotive Cybersecurity Hardware Authentication Required)',
                'authorization' => 'Include header "X-Haven-Device-Key: <your_vehicle_device_key>" or Bearer token.',
            ], 401);
        }

        // 3. Process Vibration Telemetry (MPU6050: D21/D22) — stored on private disk ONLY when save_session=true
        $saveSession = $request->boolean('save_session', false);
        $vibrationPath = null;
        $mags = [];
        $sampleCount = 0;

        // Collect raw samples first; gravity/DC is removed below before computing peak/RMS.
        $xyzRows = [];
        $scalarRows = [];

        if (! empty($validated['csv_data'])) {
            if ($saveSession) {
                $filename = 'vibration/'.Str::uuid().'.csv';
                Storage::disk('local')->put($filename, $validated['csv_data']);
                $vibrationPath = $filename;
            }

            $lines = array_filter(array_map('trim', explode("\n", $validated['csv_data'])));
            if (count($lines) > 1) {
                foreach ($lines as $line) {
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    $cols = str_getcsv($line);
                    if (empty($cols) || ! is_numeric(trim((string) $cols[0]))) {
                        continue;
                    }
                    if (count($cols) >= 4) {
                        $xyzRows[] = [(float) $cols[1], (float) $cols[2], (float) $cols[3]];
                    } elseif (count($cols) >= 2) {
                        $scalarRows[] = (float) $cols[1];
                    }
                }
            }
        } elseif (! empty($validated['samples']) && is_array($validated['samples'])) {
            if ($saveSession) {
                $filename = 'vibration/'.Str::uuid().'.json';
                Storage::disk('local')->put($filename, json_encode($validated['samples'], JSON_PRETTY_PRINT));
                $vibrationPath = $filename;
            }

            foreach ($validated['samples'] as $sample) {
                if (is_array($sample)) {
                    if (isset($sample['x'], $sample['y'], $sample['z'])) {
                        $xyzRows[] = [(float) $sample['x'], (float) $sample['y'], (float) $sample['z']];
                    } elseif (isset($sample['magnitude'])) {
                        $scalarRows[] = (float) $sample['magnitude'];
                    }
                } elseif (is_numeric($sample)) {
                    $scalarRows[] = (float) $sample;
                }
            }
        }

        if (! empty($xyzRows)) {
            // Per-axis mean subtraction cancels the static 1 g gravity vector.
            $n = count($xyzRows);
            $mx = array_sum(array_column($xyzRows, 0)) / $n;
            $my = array_sum(array_column($xyzRows, 1)) / $n;
            $mz = array_sum(array_column($xyzRows, 2)) / $n;
            foreach ($xyzRows as [$x, $y, $z]) {
                $mags[] = sqrt((($x - $mx) ** 2) + (($y - $my) ** 2) + (($z - $mz) ** 2));
            }
        } elseif (! empty($scalarRows)) {
            $mean = array_sum($scalarRows) / count($scalarRows);
            $mags = array_map(fn ($v) => abs($v - $mean), $scalarRows);
        }

        $sampleCount = count($mags);
        $peakG = $validated['peak_g'] ?? (! empty($mags) ? round(max($mags), 4) : null);
        $rmsG = $validated['rms_g'] ?? (! empty($mags) ? round(sqrt(array_sum(array_map(fn ($v) => $v ** 2, $mags)) / $sampleCount), 4) : null);

        // 4. Process Audio (INMP441 I2S MEMS Mic: D18/D19/D5) — stored on private disk ONLY when save_session=true
        $audioPath = null;
        if (! empty($validated['audio_base64'])) {
            $base64Payload = $validated['audio_base64'];
            if (str_contains($base64Payload, ',')) {
                $base64Payload = substr($base64Payload, strpos($base64Payload, ',') + 1);
            }
            $rawAudio = base64_decode($base64Payload, true);
            if ($rawAudio === false || strlen($rawAudio) > 5 * 1024 * 1024) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid or oversized audio_base64 payload (maximum 5 MB decoded).',
                ], 422);
            }
            if (strlen($rawAudio) > 0 && $saveSession) {
                $mimeStr = strtolower((string) ($validated['audio_mime'] ?? ''));
                $ext = match (true) {
                    str_contains($mimeStr, 'wav') => 'wav',
                    str_contains($mimeStr, 'ogg') => 'ogg',
                    str_contains($mimeStr, 'mpeg') || str_contains($mimeStr, 'mp3') => 'mp3',
                    default => 'webm',
                };
                $audioFilename = 'audio/'.Str::uuid().'.'.$ext;
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
            'ds18b20' => ['pin' => 'D4', 'temp_c' => $tempC !== null ? (float) $tempC : null],
            'hall_rpm' => ['pin' => 'D23', 'rpm' => $rpm !== null ? (int) $rpm : null],
            'mpu6050' => ['pins' => 'D21/D22', 'peak_g' => $peakG, 'rms_g' => $rmsG],
            'ina219' => ['pins' => 'D21/D22', 'voltage_v' => $voltageV !== null ? (float) $voltageV : null, 'current_ma' => $currentMa !== null ? (float) $currentMa : null],
            'inmp441' => ['pins' => 'D18/D19/D5', 'has_audio' => ! empty($audioPath)],
        ];

        // 6. Compute Component Pluggability & Health Status
        $healthInput = $request->input('health', []);
        $ds18Ok = filter_var($healthInput['ds18b20'] ?? true, FILTER_VALIDATE_BOOLEAN) && ! is_null($tempC) && $tempC > -50 && $tempC < 125;
        // MPU health: trust the firmware's explicit health flag. Peak is now gravity-removed,
        // so a stationary but connected sensor legitimately reports ~0 g.
        $mpuOk = filter_var($healthInput['mpu6050'] ?? true, FILTER_VALIDATE_BOOLEAN) && ! is_null($peakG);
        $inaReported = filter_var($healthInput['ina219'] ?? true, FILTER_VALIDATE_BOOLEAN) && ! is_null($voltageV);
        $micOk = filter_var($healthInput['inmp441'] ?? true, FILTER_VALIDATE_BOOLEAN) && ! is_null($audioRms) && $audioRms > 0;

        $componentsHealth = [
            'ds18b20' => [
                'name' => 'Temperature Sensor (DS18B20)',
                'pin' => 'D4 (OneWire)',
                'connected' => $ds18Ok,
                'status' => $ds18Ok ? 'CONNECTED' : 'DISCONNECTED / PIN UNPLUGGED',
                'value' => $ds18Ok ? (round((float) $tempC, 1).' °C') : 'DISCONNECTED',
                'hint' => $ds18Ok ? 'Reading room/ambient temp' : 'Signal lost on D4. Check jumper wire & 4.7kΩ pull-up.',
            ],
            'mpu6050' => [
                'name' => 'Vibration / Accelerometer (MPU6050)',
                'pins' => 'D21 (SDA) / D22 (SCL)',
                'connected' => $mpuOk,
                'status' => $mpuOk ? 'CONNECTED' : 'DISCONNECTED / PIN UNPLUGGED',
                'value' => $mpuOk ? (round((float) $peakG, 3).' g') : 'DISCONNECTED',
                'hint' => $mpuOk ? 'Dynamic vibration (gravity removed). ~0 g when stationary.' : 'I2C line lost. Check D21 (SDA) / D22 (SCL) jumper wire.',
            ],
            'ina219' => [
                'name' => 'Battery / Power Monitor (INA219)',
                'pins' => 'D21 (SDA) / D22 (SCL)',
                'connected' => $inaReported,
                'status' => ! $inaReported ? 'DISCONNECTED / PIN UNPLUGGED' : ($voltageV > 1.0 ? 'CONNECTED (VOLTAGE DETECTED)' : 'CONNECTED (NO BATTERY ON VIN)'),
                'value' => $inaReported ? (round((float) $voltageV, 2).' V · '.round((float) ($currentMa ?? 0), 1).' mA') : 'DISCONNECTED',
                'hint' => ! $inaReported ? 'I2C line lost. Check D21 (SDA) / D22 (SCL) jumper wire.' : ($voltageV > 1.0 ? 'Reading vehicle battery' : 'Module is active, but Vin+/- screw terminals have no battery connected'),
            ],
            'inmp441' => [
                'name' => 'Acoustic MEMS Mic (INMP441)',
                'pins' => 'D18 (BCLK), D19 (WS), D5 (SD)',
                'connected' => $micOk,
                'status' => $micOk ? 'CONNECTED' : 'DISCONNECTED / PIN UNPLUGGED',
                'value' => $micOk ? (number_format((int) $audioRms).' RMS') : 'DISCONNECTED',
                'hint' => $micOk ? 'Capturing ambient audio' : 'I2S bus lost. Check pins D18, D19, and D5.',
            ],
            'hall_rpm' => [
                'name' => 'Tachometer / Speed (Hall Sensor)',
                'pin' => 'D23 (Digital Interrupt)',
                'connected' => ! is_null($rpm),
                'status' => $rpm > 0 ? 'PULSES DETECTED' : (! is_null($rpm) ? 'IDLE / READY' : 'NOT REPORTED'),
                'value' => number_format((int) ($rpm ?? 0)).' RPM',
                'hint' => $rpm > 0 ? 'Pulses registering (if not on engine, consider 10kΩ pull-up to stop noise)' : 'Waiting for magnet pass',
            ],
        ];

        // 7. Live Telemetry Cache (Always updated for live hardware verification)
        // Guard against empty USB-relay status-line fallback pings overwriting real sensor packets
        $isEmptyFallbackPing = ! $saveSession
            && $tempC === null
            && $peakG === null
            && $voltageV === null
            && ($audioRms === null || (float) $audioRms <= 0)
            && ($rpm === null || (int) $rpm === 0);

        $existingLive = Cache::get('telemetry:live:'.$vehicle->id)
            ?? ($vehicle->user_id ? Cache::get('telemetry:live:user:'.$vehicle->user_id) : null);

        if ($isEmptyFallbackPing && is_array($existingLive)) {
            $hasExistingSensorData = ($existingLive['temperature_c'] ?? null) !== null
                || ($existingLive['peak_g'] ?? null) !== null
                || ($existingLive['voltage_v'] ?? null) !== null
                || (($existingLive['audio_rms'] ?? 0) > 0)
                || (($existingLive['rpm'] ?? 0) > 0);

            if ($hasExistingSensorData) {
                $existingLive['online'] = true;
                $existingLive['diff_seconds'] = 0;
                $existingLive['last_seen_human'] = 'Just now';
                $existingLive['timestamp'] = now()->toIso8601String();
                Cache::put('telemetry:live:'.$vehicle->id, $existingLive, 900);
                if ($vehicle->user_id) {
                    Cache::put('telemetry:live:user:'.$vehicle->user_id, $existingLive, 900);
                }

                return response()->json([
                    'success' => true,
                    'mode' => 'live_check',
                    'message' => 'Heartbeat refreshed; preserved active sensor telemetry.',
                    'components_health' => $existingLive['components_health'] ?? $componentsHealth,
                    'metrics' => [
                        'temperature_c' => $existingLive['temperature_c'] ?? null,
                        'rpm' => $existingLive['rpm'] ?? 0,
                        'voltage_v' => $existingLive['voltage_v'] ?? null,
                        'peak_g' => $existingLive['peak_g'] ?? null,
                    ],
                ], 200);
            }
        }

        $livePayload = [
            'online' => true,
            'diff_seconds' => 0,
            'last_seen_human' => 'Just now',
            'device_id' => $validated['device_id'] ?? 'ESP32-5SENSOR-NODE',
            'vehicle_name' => $vehicle->display_name,
            'vehicle_id' => $vehicle->id,
            'temperature_c' => $tempC !== null ? (float) $tempC : null,
            'rpm' => $rpm !== null ? (int) $rpm : 0,
            'voltage_v' => $voltageV !== null ? (float) $voltageV : null,
            'current_ma' => $currentMa !== null ? (float) $currentMa : null,
            'power_mw' => $powerMw !== null ? (float) $powerMw : null,
            'peak_g' => ! is_null($peakG) ? (float) $peakG : null,
            'rms_g' => ! is_null($rmsG) ? (float) $rmsG : null,
            'crest_factor' => isset($validated['crest_factor'])
                ? (float) $validated['crest_factor']
                : (($rmsG && $peakG && $rmsG > 0) ? round($peakG / $rmsG, 2) : null),
            'samples' => $validated['samples'] ?? [],
            'sample_rate_hz' => isset($validated['sample_rate_hz']) ? (int) $validated['sample_rate_hz'] : 100,
            'sensor_type' => $mpuOk ? ($request->input('sensor_data.adxl345') ? 'ADXL345' : 'MPU6050') : 'ACCELEROMETER',
            'packet_id' => Str::random(8),
            'accel_x' => $request->input('sensor_data.mpu6050.ax'),
            'accel_y' => $request->input('sensor_data.mpu6050.ay'),
            'accel_z' => $request->input('sensor_data.mpu6050.az'),
            'audio_rms' => $audioRms !== null ? (float) $audioRms : null,
            'audio_peak' => $audioPeak !== null ? (float) $audioPeak : null,
            'components_health' => $componentsHealth,
            'mode' => 'live_check',
            'timestamp' => now()->toIso8601String(),
        ];

        Cache::put('telemetry:live:'.$vehicle->id, $livePayload, 900);
        if ($vehicle->user_id) {
            Cache::put('telemetry:live:user:'.$vehicle->user_id, $livePayload, 900);
        }

        // Rolling 30-packet history buffer for live oscilloscope chart (filter out empty status-line pings)
        $historyKey = 'telemetry:history:'.$vehicle->id;
        $history = array_values(array_filter(Cache::get($historyKey, []), function ($pt) {
            return ! (($pt['temp'] ?? null) === null && ($pt['peak'] ?? null) === null && (int) ($pt['rpm'] ?? 0) === 0);
        }));
        if (! $isEmptyFallbackPing) {
            $history[] = [
                'time' => now()->format('H:i:s'),
                'rpm' => $rpm !== null ? (int) $rpm : 0,
                'temp' => $tempC !== null ? (float) $tempC : null,
                'peak' => ! is_null($peakG) ? (float) $peakG : null,
            ];
        }
        if (count($history) > 30) {
            $history = array_slice($history, -30);
        }
        Cache::put($historyKey, $history, 60);
        if ($vehicle->user_id) {
            Cache::put('telemetry:history:user:'.$vehicle->user_id, $history, 60);
        }

        // 8. Session Persistence Decision:
        // Do NOT create diagnostic database sessions for periodic 5-second hardware checkups!
        // Only persist if save_session=true was explicitly passed.
        $saveSession = $request->boolean('save_session', false);

        if (! $saveSession) {
            return response()->json([
                'success' => true,
                'mode' => 'live_check',
                'message' => 'Hardware checkup received & cached. No diagnostic session created.',
                'components_health' => $componentsHealth,
                'metrics' => [
                    'temperature_c' => $tempC,
                    'rpm' => $rpm,
                    'voltage_v' => $voltageV,
                    'peak_g' => $peakG,
                ],
            ], 200);
        }

        // Formal Diagnostic Session Creation (Only when save_session=true)
        $autoAnalyze = $request->boolean('auto_analyze', true);
        $hasAnalyzableData = $audioPath || $vibrationPath || ! is_null($tempC) || ! is_null($rpm) || ! is_null($voltageV);
        $shouldAnalyze = $autoAnalyze && $hasAnalyzableData;
        $deviceId = $validated['device_id'] ?? 'ESP32-Automotive-DAQ';

        $notesList = ["ESP32 Wi-Fi DAQ Node [{$deviceId}]."];
        if ($tempC !== null) {
            $notesList[] = "Temp: {$tempC}°C (DS18B20/D4).";
        }
        if ($rpm !== null) {
            $notesList[] = "RPM: {$rpm} (Hall/D23).";
        }
        if ($voltageV !== null) {
            $notesList[] = "Battery: {$voltageV}V (INA219/D21-D22).";
        }
        if ($peakG !== null) {
            $notesList[] = "Peak G: {$peakG}g (MPU6050/D21-D22).";
        }

        $diagnostic = Diagnostic::create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'status' => $shouldAnalyze ? 'processing' : 'submitted',
            'audio_path' => $audioPath,
            'vibration_path' => $vibrationPath,
            'vibration_source' => ! empty($vibrationPath) ? 'device_sensor' : null,
            'vibration_sample_count' => $sampleCount,
            'vibration_peak_g' => $peakG,
            'vibration_rms_g' => $rmsG,
            'sample_rate_hz' => isset($validated['sample_rate_hz'])
                ? (int) $validated['sample_rate_hz']
                : (! empty($vibrationPath) ? 100 : null),
            'temperature_c' => $tempC !== null ? (float) $tempC : null,
            'rpm' => $rpm !== null ? (int) $rpm : null,
            'voltage_v' => $voltageV !== null ? (float) $voltageV : null,
            'current_ma' => $currentMa !== null ? (float) $currentMa : null,
            'sensor_data' => $sensorData,
            'notes' => implode(' ', $notesList),
        ]);

        // 7. Record ISO 27001 Audit Trail
        AuditLogger::log('iot.telemetry_ingested', [
            'vehicle_id' => $vehicle->id,
            'diagnostic_id' => $diagnostic->id,
            'device_id' => $deviceId,
            'sample_count' => $sampleCount,
            'peak_g' => $peakG,
            'temperature_c' => $tempC,
            'rpm' => $rpm,
            'voltage_v' => $voltageV,
            'auto_analyze' => $autoAnalyze,
        ], $user->id, 'success');

        // 8. Dispatch Background ML Analysis Job if requested
        if ($shouldAnalyze) {
            AnalyzeDiagnosticJob::dispatch($diagnostic);
        }

        Log::info("ESP32 multi-sensor telemetry ingested for vehicle #{$vehicle->id} -> Diagnostic #{$diagnostic->id}");

        return response()->json([
            'success' => true,
            'message' => 'ESP32 Multi-Sensor Telemetry successfully authenticated and ingested.',
            'diagnostic_id' => $diagnostic->id,
            'status' => $diagnostic->status,
            'show_url' => route('diagnostics.show', $diagnostic),
            'status_url' => route('diagnostics.status', $diagnostic),
            'metrics' => [
                'sample_count' => $sampleCount,
                'peak_g' => $peakG,
                'rms_g' => $rmsG,
                'temperature_c' => $tempC ? (float) $tempC : null,
                'rpm' => $rpm ? (int) $rpm : null,
                'voltage_v' => $voltageV ? (float) $voltageV : null,
                'current_ma' => $currentMa ? (float) $currentMa : null,
                'device_id' => $deviceId,
                'auto_analyzed' => $autoAnalyze,
                'authenticated' => true,
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
            'status' => 'online',
            'service' => 'HAVEN AI IoT Telemetry Ingest',
            'version' => '2.5.0',
            'server_time' => now()->toIso8601String(),
            'supported_modes' => ['csv', 'json', 'audio_base64'],
            'iso_standards' => ['ISO/IEC 27001:2022', 'ISO/SAE 21434'],
            'auth_required' => 'X-Haven-Device-Key or Bearer token on POST /ingest',
            'recommended_baud' => 115200,
            'recommended_hz' => 50,
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
                    'online' => false,
                    'status' => 'forbidden',
                    'message' => 'Unauthorized vehicle access.',
                ], 403);
            }
            $cached = Cache::get('telemetry:live:'.$vehicle->id)
                ?? Cache::get('telemetry:live:user:'.$user->id);
        } else {
            $vehicle = Vehicle::where('user_id', $user->id)->first();
            $cached = Cache::get('telemetry:live:user:'.$user->id)
                ?? ($vehicle ? Cache::get('telemetry:live:'.$vehicle->id) : null);
        }

        if ($cached) {
            // Revalidate freshness from stored timestamp: ESP32 sends every 5s (plus up to 5s HTTP timeout).
            // 45s grace tolerates Wi-Fi hiccups, slow Apache responses and missed packets without flapping offline.
            $ts = $cached['timestamp'] ?? null;
            $diffSec = $ts ? abs(now()->diffInSeconds(Carbon::parse($ts))) : 999;
            $isLive = ($diffSec <= 45);

            $cached['diff_seconds'] = $diffSec;
            $cached['online'] = $isLive;
            $cached['last_seen_human'] = $isLive
                ? 'Live (Streaming)'
                : ($ts ? Carbon::parse($ts)->diffForHumans() : 'Offline');

            // If hardware is offline/unplugged, clear active component health so UI shows disconnected
            if (! $isLive) {
                $cached['temperature_c'] = null;
                $cached['voltage_v'] = null;
                $cached['current_ma'] = null;
                $cached['peak_g'] = null;
                $cached['rms_g'] = null;
                $cached['crest_factor'] = null;
                $cached['samples'] = [];
                $cached['audio_rms'] = null;
                $cached['rpm'] = 0;
            }

            $activeVid = ! empty($cached['vehicle_id']) ? (int) $cached['vehicle_id'] : ($vehicle?->id);
            $historyKey = $activeVid ? ('telemetry:history:'.$activeVid) : ('telemetry:history:user:'.$user->id);
            $cached['history'] = Cache::get($historyKey, []);

            return response()->json($cached);
        }

        // Hardware is currently offline / not sending
        $historyKey = ($vehicleId !== null && $vehicleId !== '' && $vehicle)
            ? ('telemetry:history:'.$vehicle->id)
            : ('telemetry:history:user:'.$user->id);

        return response()->json([
            'online' => false,
            'diff_seconds' => 999,
            'last_seen_human' => 'Offline / Standby',
            'device_id' => 'ESP32-5SENSOR-NODE',
            'vehicle_name' => $vehicle?->display_name ?? 'Vehicle',
            'vehicle_id' => $vehicle?->id,
            'temperature_c' => null,
            'rpm' => 0,
            'voltage_v' => null,
            'current_ma' => null,
            'power_mw' => null,
            'peak_g' => null,
            'accel_x' => null,
            'accel_y' => null,
            'accel_z' => null,
            'audio_rms' => null,
            'audio_peak' => null,
            'components_health' => null,
            'mode' => 'standby',
            'status' => 'standby',
            'timestamp' => null,
            'history' => Cache::get($historyKey, []),
        ]);
    }

    /**
     * Server-Sent Events (SSE) stream for live ESP32 telemetry & rolling waveform.
     * GET /api/v1/telemetry/stream
     */
    public function stream(Request $request): StreamedResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $vehicleId = $request->query('vehicle_id');
        $vehicle = $vehicleId ? Vehicle::where('user_id', $user->id)->find($vehicleId) : Vehicle::where('user_id', $user->id)->first();

        // Release the session immediately so we do not block subsequent concurrent requests
        if (session()->isStarted()) {
            session()->save();
        }

        return response()->stream(function () use ($user, $vehicle, $vehicleId) {
            // Send initial ping event
            echo ": connected\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            $cached = ($vehicleId && $vehicle)
                ? (Cache::get('telemetry:live:'.$vehicle->id) ?? Cache::get('telemetry:live:user:'.$user->id))
                : (Cache::get('telemetry:live:user:'.$user->id)
                    ?? ($vehicle ? Cache::get('telemetry:live:'.$vehicle->id) : null));

            $activeVid = ! empty($cached['vehicle_id']) ? (int) $cached['vehicle_id'] : ($vehicle?->id);
            $historyKey = $activeVid ? ('telemetry:history:'.$activeVid) : ('telemetry:history:user:'.$user->id);
            $history = Cache::get($historyKey, []);

            $payload = $cached ?: [
                'online' => false,
                'last_seen_human' => 'Standby',
                'vehicle_name' => $vehicle?->display_name ?? 'Vehicle',
                'temperature_c' => null,
                'rpm' => 0,
                'voltage_v' => null,
                'peak_g' => null,
            ];
            $payload['history'] = $history;

            echo "event: telemetry\n";
            echo 'data: '.json_encode($payload)."\n\n";

            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
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
            $vehicle->update(['device_key' => 'hvn_dev_'.bin2hex(random_bytes(16))]);
        }

        $deviceKey = $vehicle->device_key;
        $vId = $vehicle->id;

        $code = <<<CPP
#include <WiFi.h>
#include <WebServer.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <Adafruit_INA219.h>
#include <driver/i2s.h>

Preferences prefs;
WebServer server(80);

char ssid[64]      = "YOUR_WIFI_SSID";
char password[64]  = "YOUR_WIFI_PASSWORD";
char serverUrl[128]= "http://YOUR_SERVER_IP:8000/api/v1/telemetry/ingest";
char deviceKey[64] = "{$deviceKey}";

// Pins: MPU6050 + INA219 share I2C (D21/D22), DS18B20 on D4, Hall on D23, INMP441 on D18/D19/D5
#define SENSOR_SDA 21
#define SENSOR_SCL 22
#define MPU_ADDR   0x68
#define INA_ADDR   0x40
#define TEMP_PIN   4
#define HALL_PIN   23
#define MIC_BCLK   18
#define MIC_WS     19
#define MIC_SD     5
#define VIB_BUFFER_SIZE 128

OneWire oneWire(TEMP_PIN);
DallasTemperature tempSensor(&oneWire);
Adafruit_INA219 ina219(INA_ADDR);

float vibBufX[VIB_BUFFER_SIZE];
float vibBufY[VIB_BUFFER_SIZE];
float vibBufZ[VIB_BUFFER_SIZE];
int vibIdx = 0;
bool vibFull = false;
bool accelReady = false;
bool inaReady = false;
bool micReady = false;
float latestAx = 0.0f, latestAy = 0.0f, latestAz = 0.0f;
float tempC = NAN;
bool ds18Plugged = false;
float busVoltage = 0, currentMA = 0, powerMW = 0;
float audioRMS = 0, audioPeak = 0;
float rpmValue = 0;

volatile unsigned long pulseCount = 0;
volatile unsigned long lastPulseUs = 0;
portMUX_TYPE pulseMux = portMUX_INITIALIZER_UNLOCKED;
unsigned long lastRpmMs = 0;

unsigned long lastSampleMs = 0;
unsigned long lastUploadMs = 0;
unsigned long lastTempMs = 0;
unsigned long lastWifiRetryMs = 0;

void IRAM_ATTR countPulse() {
  unsigned long nowUs = micros();
  portENTER_CRITICAL_ISR(&pulseMux);
  if ((unsigned long)(nowUs - lastPulseUs) >= 20000UL && digitalRead(HALL_PIN) == LOW) {
    pulseCount++;
    lastPulseUs = nowUs;
  }
  portEXIT_CRITICAL_ISR(&pulseMux);
}

bool probeI2C(uint8_t addr) {
  Wire.beginTransmission(addr);
  return (Wire.endTransmission() == 0);
}

bool initAccelI2C() {
  Wire.begin(SENSOR_SDA, SENSOR_SCL, 400000);
  Wire.beginTransmission(MPU_ADDR);
  Wire.write(0x6B); // PWR_MGMT_1: wake up
  Wire.write(0x00);
  if (Wire.endTransmission() != 0) return false;
  Wire.beginTransmission(MPU_ADDR);
  Wire.write(0x1C); // ACCEL_CONFIG: +/- 8g (4096 LSB/g)
  Wire.write(0x10);
  return (Wire.endTransmission() == 0);
}

bool readAccelG(float &ax, float &ay, float &az) {
  Wire.beginTransmission(MPU_ADDR);
  Wire.write(0x3B); // ACCEL_XOUT_H
  if (Wire.endTransmission(false) != 0) return false;
  if (Wire.requestFrom((uint8_t)MPU_ADDR, (uint8_t)6, (uint8_t)true) != 6) return false;
  int16_t rx = (int16_t)((Wire.read() << 8) | Wire.read());
  int16_t ry = (int16_t)((Wire.read() << 8) | Wire.read());
  int16_t rz = (int16_t)((Wire.read() << 8) | Wire.read());
  ax = (float)rx / 4096.0f;
  ay = (float)ry / 4096.0f;
  az = (float)rz / 4096.0f;
  return true;
}

void setupMicrophone() {
  i2s_config_t config = {};
  config.mode = (i2s_mode_t)(I2S_MODE_MASTER | I2S_MODE_RX);
  config.sample_rate = 16000;
  config.bits_per_sample = I2S_BITS_PER_SAMPLE_32BIT;
  config.channel_format = I2S_CHANNEL_FMT_ONLY_LEFT;
  config.communication_format = I2S_COMM_FORMAT_STAND_I2S;
  config.intr_alloc_flags = 0;
  config.dma_buf_count = 4;
  config.dma_buf_len = 256;
  config.use_apll = false;
  i2s_pin_config_t pins = {};
  pins.bck_io_num = MIC_BCLK;
  pins.ws_io_num = MIC_WS;
  pins.data_out_num = I2S_PIN_NO_CHANGE;
  pins.data_in_num = MIC_SD;
  if (i2s_driver_install(I2S_NUM_0, &config, 0, NULL) != ESP_OK) { micReady = false; return; }
  micReady = (i2s_set_pin(I2S_NUM_0, &pins) == ESP_OK);
}

void readMicrophone() {
  if (!micReady) return;
  int32_t buf[256];
  size_t bytesRead = 0;
  if (i2s_read(I2S_NUM_0, buf, sizeof(buf), &bytesRead, pdMS_TO_TICKS(50)) != ESP_OK || bytesRead == 0) return;
  int count = bytesRead / sizeof(int32_t);
  double sum = 0, sumSq = 0, peak = 0;
  for (int i = 0; i < count; i++) {
    double s = buf[i] / 256.0;
    sum += s;
    sumSq += s * s;
    if (fabs(s) > peak) peak = fabs(s);
  }
  double mean = sum / count;
  double variance = (sumSq / count) - (mean * mean);
  if (variance < 0) variance = 0;
  audioRMS = sqrt(variance);
  audioPeak = peak;
}

// Config page is protected by this device's key (printed in the website pairing dialog).
bool configAuthorized() {
  return server.arg("key") == String(deviceKey);
}

void setup() {
  Serial.begin(115200);
  prefs.begin("haven", true);
  if (prefs.isKey("ssid")) {
    prefs.getString("ssid", ssid, sizeof(ssid));
    prefs.getString("pass", password, sizeof(password));
    prefs.getString("server", serverUrl, sizeof(serverUrl));
  }
  prefs.end();

  accelReady = initAccelI2C();
  if (probeI2C(INA_ADDR)) {
    inaReady = ina219.begin(&Wire);
    if (inaReady) ina219.setCalibration_32V_2A();
  }
  setupMicrophone();

  pinMode(HALL_PIN, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(HALL_PIN), countPulse, FALLING);

  // Async non-blocking temperature conversion (eliminates 750ms 1-Wire stall)
  tempSensor.begin();
  tempSensor.setResolution(10);
  tempSensor.setWaitForConversion(false);
  tempSensor.requestTemperatures();

  // Soft AP password = last 8 chars of the device key (unique per vehicle, not a shared default)
  String apPass = String(deviceKey);
  apPass = apPass.substring(apPass.length() > 8 ? apPass.length() - 8 : 0);
  WiFi.mode(WIFI_AP_STA);
  WiFi.softAP("HAVEN_AI_LOCAL", apPass.c_str());
  WiFi.setAutoReconnect(true);
  WiFi.begin(ssid, password);
  int tries = 0;
  while (WiFi.status() != WL_CONNECTED && tries++ < 20) {
    delay(400);
  }

  server.on("/config", []() {
    server.send(200, "text/html", "<form method='POST' action='/save'>Device key:<input name='key' type='password'><br>SSID:<input name='ssid'><br>Pass:<input name='pass' type='password'><br>Server:<input name='server'><br><button>Save</button></form>");
  });
  server.on("/save", HTTP_POST, []() {
    if (!configAuthorized()) {
      server.send(403, "text/plain", "Forbidden: invalid device key");
      return;
    }
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

  // Wi-Fi auto-reconnect every 15s if the link dropped
  if (WiFi.status() != WL_CONNECTED && now - lastWifiRetryMs >= 15000) {
    lastWifiRetryMs = now;
    WiFi.disconnect();
    WiFi.begin(ssid, password);
  }

  // Strict 100Hz non-blocking 3-axis hardware vibration sampling loop (10ms interval)
  if (now - lastSampleMs >= 10) {
    lastSampleMs = now;
    if (accelReady && readAccelG(latestAx, latestAy, latestAz)) {
      vibBufX[vibIdx] = latestAx;
      vibBufY[vibIdx] = latestAy;
      vibBufZ[vibIdx] = latestAz;
      vibIdx = (vibIdx + 1) % VIB_BUFFER_SIZE;
      if (vibIdx == 0) vibFull = true;
    } else if (!accelReady && (now % 1000 < 10)) {
      accelReady = initAccelI2C();
    }
  }

  // DS18B20 async read once per second
  if (now - lastTempMs >= 1000) {
    lastTempMs = now;
    float t = tempSensor.getTempCByIndex(0);
    ds18Plugged = (t > -55.0f && t < 125.0f && t != DEVICE_DISCONNECTED_C);
    tempC = ds18Plugged ? t : NAN;
    tempSensor.requestTemperatures();
  }

  // Hall RPM over 1s windows
  if (now - lastRpmMs >= 1000) {
    unsigned long pulses;
    portENTER_CRITICAL(&pulseMux);
    pulses = pulseCount;
    pulseCount = 0;
    portEXIT_CRITICAL(&pulseMux);
    rpmValue = (float)pulses * 60.0f * 1000.0f / (float)(now - lastRpmMs);
    lastRpmMs = now;
  }

  if (now - lastUploadMs >= 5000 && WiFi.status() == WL_CONNECTED) {
    lastUploadMs = now;

    // Refresh INA219 (hot-plug aware) and microphone level
    if (!inaReady && probeI2C(INA_ADDR)) {
      inaReady = ina219.begin(&Wire);
      if (inaReady) ina219.setCalibration_32V_2A();
    } else if (inaReady && !probeI2C(INA_ADDR)) {
      inaReady = false;
    }
    if (inaReady) {
      busVoltage = ina219.getBusVoltage_V();
      currentMA  = ina219.getCurrent_mA();
      powerMW    = ina219.getPower_mW();
    }
    readMicrophone();

    HTTPClient http;
    http.setTimeout(2000);
    http.begin(serverUrl);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-Haven-Device-Key", deviceKey);

    DynamicJsonDocument doc(8192);
    doc["vehicle_id"]     = {$vId};
    doc["device_id"]      = "ESP32-HAVEN-NODE-01";
    doc["sample_rate_hz"] = 100;
    doc["save_session"]   = false;
    doc["auto_analyze"]   = false;
    doc["rpm"]            = (int)rpmValue;
    if (ds18Plugged) doc["temperature_c"] = tempC;
    if (inaReady) {
      doc["voltage_v"]  = busVoltage;
      doc["current_ma"] = currentMA;
    }

    JsonObject health = doc.createNestedObject("health");
    health["ds18b20"]  = ds18Plugged;
    health["mpu6050"]  = accelReady;
    health["ina219"]   = inaReady;
    health["inmp441"]  = micReady && audioRMS > 0;
    health["hall_rpm"] = true;

    JsonObject sd = doc.createNestedObject("sensor_data");
    if (ds18Plugged) sd.createNestedObject("ds18b20")["temp_c"] = tempC;
    sd.createNestedObject("hall_rpm")["rpm"] = (int)rpmValue;
    if (inaReady) {
      JsonObject inaObj = sd.createNestedObject("ina219");
      inaObj["voltage_v"]  = busVoltage;
      inaObj["current_ma"] = currentMA;
      inaObj["power_mw"]   = powerMW;
    }
    if (micReady) {
      JsonObject micObj = sd.createNestedObject("inmp441");
      micObj["rms"]  = audioRMS;
      micObj["peak"] = audioPeak;
    }

    int available = vibFull ? VIB_BUFFER_SIZE : vibIdx;
    int countToSend = (available > 64) ? 64 : available;
    if (accelReady && countToSend > 0) {
      JsonArray samples = doc.createNestedArray("samples");
      for (int i = 0; i < countToSend; i++) {
        int idx = (vibIdx - countToSend + i + VIB_BUFFER_SIZE) % VIB_BUFFER_SIZE;
        JsonObject sObj = samples.createNestedObject();
        sObj["x"] = round(vibBufX[idx] * 1000.0f) / 1000.0f;
        sObj["y"] = round(vibBufY[idx] * 1000.0f) / 1000.0f;
        sObj["z"] = round(vibBufZ[idx] * 1000.0f) / 1000.0f;
      }
      JsonObject mpuObj = sd.createNestedObject("mpu6050");
      mpuObj["ax"] = latestAx;
      mpuObj["ay"] = latestAy;
      mpuObj["az"] = latestAz;
    }

    String requestBody;
    serializeJson(doc, requestBody);
    http.POST(requestBody);
    http.end();
  }
}
CPP;

        return response()->json([
            'language' => 'cpp',
            'platform' => 'ESP32 / Arduino',
            'filename' => 'Haven_ESP32_Telemetry.ino',
            'vehicle_id' => $vId,
            'sketch' => $code,
        ]);
    }
}
