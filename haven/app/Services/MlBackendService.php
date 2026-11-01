<?php

namespace App\Services;

use App\Models\Diagnostic;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * HTTP client wrapper for the HAVEN FastAPI ML backend.
 *
 * When the ML server is unavailable, falls back to a deterministic
 * mock classifier so the app remains demonstrable without Python running.
 */
class MlBackendService
{
    private string $baseUrl;
    private int    $timeoutSec;

    public function __construct()
    {
        $this->baseUrl    = rtrim(config('services.ml_backend.url', 'http://localhost:8001'), '/');
        $this->timeoutSec = (int) config('services.ml_backend.timeout', 30);
    }

    /* ─────────────────────────────────────────────
       PUBLIC API
    ───────────────────────────────────────────── */

    /**
     * Check if the ML backend server is reachable.
     */
    public function isAlive(): bool
    {
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Submit a Diagnostic record to the ML backend for analysis.
     * Returns the parsed result array or throws on hard failure.
     */
    public function analyze(Diagnostic $diagnostic): array
    {
        // Check connectivity first
        if (! $this->isAlive()) {
            Log::info("ML backend unavailable — using mock classifier for diagnostic #{$diagnostic->id}");
            return $this->mockAnalysis($diagnostic);
        }

        try {
            $request = Http::timeout($this->timeoutSec)->asMultipart();

            // Attach audio file if present
            if ($diagnostic->hasAudio()) {
                $audioPath = Storage::disk('public')->path($diagnostic->audio_path);
                if (file_exists($audioPath)) {
                    $request = $request->attach(
                        'audio_file',
                        file_get_contents($audioPath),
                        basename($diagnostic->audio_path)
                    );
                }
            }

            // Attach vibration file if present
            if ($diagnostic->hasVibration()) {
                $vibPath = Storage::disk('public')->path($diagnostic->vibration_path);
                if (file_exists($vibPath)) {
                    $request = $request->attach(
                        'vibration_file',
                        file_get_contents($vibPath),
                        basename($diagnostic->vibration_path)
                    );
                }
            }

            $params = [
                ['name' => 'diagnostic_id', 'contents' => (string) $diagnostic->id],
                ['name' => 'vehicle_id',    'contents' => (string) $diagnostic->vehicle_id],
            ];
            if (!is_null($diagnostic->temperature_c)) {
                $params[] = ['name' => 'temperature_c', 'contents' => (string) $diagnostic->temperature_c];
            }
            if (!is_null($diagnostic->rpm)) {
                $params[] = ['name' => 'rpm', 'contents' => (string) $diagnostic->rpm];
            }
            if (!is_null($diagnostic->voltage_v)) {
                $params[] = ['name' => 'voltage_v', 'contents' => (string) $diagnostic->voltage_v];
            }
            if (!is_null($diagnostic->current_ma)) {
                $params[] = ['name' => 'current_ma', 'contents' => (string) $diagnostic->current_ma];
            }
            if (!empty($diagnostic->recording_mode)) {
                $params[] = ['name' => 'recording_mode', 'contents' => (string) $diagnostic->recording_mode];
            }

            $response = $request->post("{$this->baseUrl}/analyze", $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("ML backend returned {$response->status()} for diagnostic #{$diagnostic->id}", [
                'body' => $response->body(),
            ]);

            // Degraded: still mock rather than crash
            return $this->mockAnalysis($diagnostic, 'backend_error');

        } catch (ConnectionException $e) {
            Log::warning("ML backend connection failed: {$e->getMessage()}");
            return $this->mockAnalysis($diagnostic, 'connection_error');
        } catch (\Throwable $e) {
            Log::error("ML backend unexpected error: {$e->getMessage()}");
            return $this->mockAnalysis($diagnostic, 'unexpected_error');
        }
    }

    /* ─────────────────────────────────────────────
       MOCK CLASSIFIER
       Produces realistic results from stored feature
       data when the Python server isn't running.
    ───────────────────────────────────────────── */
    private function mockAnalysis(Diagnostic $diagnostic, string $reason = 'server_offline'): array
    {
        $peakG   = $diagnostic->vibration_peak_g ?? 0.0;
        $rmsG    = $diagnostic->vibration_rms_g  ?? 0.0;
        $crest   = $rmsG > 0 ? round($peakG / $rmsG, 2) : 0.0;
        $samples = $diagnostic->vibration_sample_count ?? 0;
        $tempC   = $diagnostic->temperature_c;
        $rpm     = $diagnostic->rpm;
        $voltage = $diagnostic->voltage_v;

        // Rule-based mock using stored vibration & hardware sensor stats
        [$condition, $confidence, $topClasses, $sensorWarnings] = $this->mockRules($peakG, $rmsG, $crest, $tempC, $rpm, $voltage);

        $featuresUsed = ['peak_g', 'rms_g', 'crest_factor'];
        if (!is_null($tempC))   $featuresUsed[] = 'ds18b20_temperature';
        if (!is_null($rpm))     $featuresUsed[] = 'hall_tachometer_rpm';
        if (!is_null($voltage)) $featuresUsed[] = 'ina219_battery_voltage';

        return [
            'condition'        => $condition,
            'confidence'       => $confidence,
            'confidence_pct'   => round($confidence * 100, 1) . '%',
            'top_classes'      => $topClasses,
            'features_used'    => $featuresUsed,
            'audio_features'   => [],
            'vibration_features' => [
                'peak_g'       => $peakG,
                'rms_g'        => $rmsG,
                'crest_factor' => $crest,
                'sample_count' => $samples,
            ],
            'hardware_telemetry' => [
                'temperature_c' => $tempC,
                'rpm'           => $rpm,
                'voltage_v'     => $voltage,
            ],
            'mode'             => "mock_{$reason}",
            'warnings'         => array_merge(
                ["ML server offline ({$reason}). Multi-sensor heuristic analysis applied."],
                $sensorWarnings
            ),
            'processed_at'     => now()->toISOString(),
        ];
    }

    private function mockRules(float $peakG, float $rmsG, float $crest, ?float $tempC, ?int $rpm, ?float $voltage): array
    {
        // 4 Thesis-Approved Engine Fault Classes (UMak CET Capstone Scope)
        $classes = ['normal', 'misfire', 'loose_component', 'valve_clearance'];
        $scores  = array_fill_keys($classes, 0.0);
        $warnings = [];

        // Temperature Telemetry Heuristics (DS18B20: Pin D4)
        if (!is_null($tempC)) {
            if ($tempC >= 105.0) {
                $warnings[] = "CRITICAL: Engine coolant temperature reached {$tempC}°C exceeding thermal threshold (105°C).";
                $scores['loose_component'] += 0.30;
            } elseif ($tempC >= 98.0) {
                $warnings[] = "WARNING: Elevated coolant temperature ({$tempC}°C). Inspect thermostat & cooling fans.";
            }
        }

        // Electrical Voltage Heuristics (INA219: Pins D21/D22)
        if (!is_null($voltage)) {
            if ($voltage < 12.2) {
                $warnings[] = "ELECTRICAL ALERT: Battery voltage low ({$voltage}V). Alternator charging system underperforming.";
                $scores['misfire'] += 0.15;
            } elseif ($voltage > 15.0) {
                $warnings[] = "ELECTRICAL ALERT: Over-voltage detected ({$voltage}V). Alternator regulator anomaly.";
            }
        }

        // 1. Improper Valve Clearance: Sharp metallic impact clicking at 0.5x RPM (high Crest Factor, impulsive G spikes)
        if ($crest > 4.2 || $peakG > 3.6) {
            $scores['valve_clearance'] += 0.65;
        } elseif ($crest > 3.5 && $peakG > 2.4) {
            $scores['valve_clearance'] += 0.35;
        }

        // 2. Engine Misfire: Combustion dropout every 720 deg crank rotation, erratic RPM lurch, cyclic torque dips
        if ($crest > 2.8 && $crest <= 4.2 && $peakG > 1.6 && $peakG < 3.5) {
            $scores['misfire'] += 0.45;
        }

        // 3. Loose or Worn Component Vibration: High continuous baseline RMS G, mount degradation
        if ($rmsG > 0.40 && $crest < 3.4) {
            $scores['loose_component'] += 0.50;
        } elseif ($rmsG > 0.30 && $peakG > 1.8) {
            $scores['loose_component'] += 0.30;
        }

        // Tachometer correlation (Hall Sensor D23)
        if (!is_null($rpm) && $rpm > 0) {
            if ($scores['valve_clearance'] > 0.3) {
                $warnings[] = "Tachometer correlation: Valvetrain harmonic impacts detected at {$rpm} RPM.";
            }
        }

        // 4. Normal Engine Operation: Baseline reading within healthy envelope (calibrated for Idle vs Revving mode)
        $isRevving = ($diagnostic->recording_mode === 'revving');
        $normalPeakLimit = $isRevving ? 2.2 : 1.8;
        $normalRmsLimit  = $isRevving ? 0.45 : 0.35;

        if ($peakG <= $normalPeakLimit && $rmsG <= $normalRmsLimit && $crest <= 3.6) {
            $scores['normal'] += 0.50;
        } else {
            $scores['normal'] += 0.15;
        }

        $total = array_sum($scores) ?: 1.0;
        $probs = array_map(fn($v) => round($v / $total, 4), $scores);

        arsort($probs);
        $condition  = array_key_first($probs);
        $confidence = $probs[$condition];

        // Ensure realistic minimum confidence
        if ($confidence < 0.40) {
            $confidence = round(0.40 + ($peakG * 0.05), 4);
            $condition  = 'normal';
        }

        $topClasses = array_slice(
            array_map(
                fn($k, $v) => ['condition' => $k, 'confidence' => $v],
                array_keys($probs), array_values($probs)
            ),
            0, 3
        );

        return [$condition, $confidence, $topClasses, $warnings];
    }
}
