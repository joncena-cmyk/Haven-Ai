<?php

namespace App\Services;

use App\Models\Diagnostic;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * HTTP client wrapper for the HAVEN FastAPI ML backend.
 *
 * When the ML server is unavailable, falls back to a deterministic
 * mock classifier so the app remains demonstrable without Python running.
 */
class MlBackendService
{
    private string $baseUrl;

    private int $timeoutSec;

    private string $apiSecret;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.ml_backend.url', 'http://localhost:8001'), '/');
        $this->timeoutSec = (int) config('services.ml_backend.timeout', 30);
        $this->apiSecret = (string) config('services.ml_backend.secret', '');
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
     * Get FastAPI health, loaded model metadata, and architecture info.
     */
    public function getHealthAndInfo(): array
    {
        $localMeta = $this->readLocalModelMeta();

        if (! $this->isAlive()) {
            return [
                'online' => false,
                'status' => 'offline',
                'url' => $this->baseUrl,
                'mode' => 'rule_based_fallback',
                'model_loaded' => ! empty($localMeta),
                'model' => $this->normalizeModelMetadata(array_merge([
                    'type' => ! empty($localMeta) ? 'trained_sklearn_on_disk' : 'fallback_heuristics',
                    'description' => 'FastAPI ML server is offline. Built-in multi-sensor fallback heuristic classifier is active (trained model.pkl ready on disk).',
                ], $localMeta)),
                'classes' => ['normal', 'misfire', 'loose_component', 'valve_clearance'],
            ];
        }

        try {
            $health = Http::timeout(3)->get("{$this->baseUrl}/health")->json() ?? [];
            $info = Http::timeout(3)->get("{$this->baseUrl}/info")->json() ?? [];
            $remoteModel = is_array($info['model'] ?? null) ? $info['model'] : [];
            if (isset($remoteModel['meta']) && is_array($remoteModel['meta'])) {
                $nestedMeta = $remoteModel['meta'];
                unset($remoteModel['meta']);
                $remoteModel = array_merge($nestedMeta, $remoteModel);
            }
            $info['model'] = $this->normalizeModelMetadata(array_merge($localMeta, $remoteModel));
            $secretConfigured = (bool) ($health['secret_configured'] ?? true) && ($this->apiSecret !== '');

            $merged = array_merge([
                'online' => true,
                'url' => $this->baseUrl,
                'secret_configured' => $secretConfigured,
            ], $health, $info);
            $merged['secret_configured'] = $secretConfigured;
            if (! $secretConfigured) {
                $merged['auth_warning'] = 'ML_API_SECRET is blank in haven/.env — authenticated /analyze and /train requests will fail until configured.';
            }

            return $merged;
        } catch (\Throwable $e) {
            return [
                'online' => false,
                'status' => 'error',
                'error' => $e->getMessage(),
                'url' => $this->baseUrl,
                'model' => $this->normalizeModelMetadata($localMeta),
            ];
        }
    }

    /**
     * Normalize model metadata structure so Blade views and API consumers
     * receive consistent feature_importances, per_class_metrics, and sample counts.
     */
    private function normalizeModelMetadata(array $meta): array
    {
        if (isset($meta['meta']) && is_array($meta['meta'])) {
            $nested = $meta['meta'];
            unset($meta['meta']);
            $meta = array_merge($nested, $meta);
        }

        // Normalize feature_importances: support both list-of-dicts and associative map
        if (! empty($meta['feature_importances']) && is_array($meta['feature_importances'])) {
            $normalizedImps = [];
            foreach ($meta['feature_importances'] as $key => $val) {
                if (is_array($val) && isset($val['feature'])) {
                    $normalizedImps[(string) $val['feature']] = (float) ($val['importance'] ?? 0.0);
                } elseif (is_string($key) && is_numeric($val)) {
                    $normalizedImps[$key] = (float) $val;
                }
            }
            if (! empty($normalizedImps)) {
                $meta['feature_importances_list'] = $meta['feature_importances'];
                $meta['feature_importances'] = $normalizedImps;
            }
        }

        // Normalize per_class_metrics so both 'f1' and 'f1_score' keys exist
        if (! empty($meta['per_class_metrics']) && is_array($meta['per_class_metrics'])) {
            foreach ($meta['per_class_metrics'] as $cls => $metrics) {
                if (is_array($metrics)) {
                    $f1 = (float) ($metrics['f1_score'] ?? $metrics['f1'] ?? 0.0);
                    $meta['per_class_metrics'][$cls]['f1'] = $f1;
                    $meta['per_class_metrics'][$cls]['f1_score'] = $f1;
                }
            }
        }

        // Compute test_samples and train_samples from confusion matrix / n_samples when omitted
        if (! isset($meta['test_samples']) && ! empty($meta['confusion_matrix']) && is_array($meta['confusion_matrix'])) {
            $cmSum = 0;
            foreach ($meta['confusion_matrix'] as $row) {
                if (is_array($row)) {
                    $cmSum += array_sum($row);
                }
            }
            if ($cmSum > 0) {
                $meta['test_samples'] = $cmSum;
                if (isset($meta['n_samples']) && (int) $meta['n_samples'] >= $cmSum) {
                    $meta['train_samples'] = max(0, (int) $meta['n_samples'] - $cmSum);
                }
            }
        }

        return $meta;
    }

    /**
     * Read local model_meta.json exported by train.py if available.
     */
    public function readLocalModelMeta(): array
    {
        $metaPath = base_path('../ml_backend/model_meta.json');
        if (file_exists($metaPath)) {
            $decoded = json_decode((string) file_get_contents($metaPath), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Resolve the best available Python executable (virtualenv first, system python fallback).
     */
    public function resolvePythonBinary(): string
    {
        $configured = config('services.ml_backend.python_binary');
        if (! empty($configured) && (file_exists($configured) || is_executable($configured))) {
            return $configured;
        }

        $winVenv = base_path('../ml_backend/.venv/Scripts/python.exe');
        if (file_exists($winVenv)) {
            return $winVenv;
        }

        $unixVenv = base_path('../ml_backend/.venv/bin/python');
        if (file_exists($unixVenv)) {
            return $unixVenv;
        }

        return 'python';
    }

    /**
     * Trigger Random Forest retraining via FastAPI (or local Python fallback).
     */
    public function triggerRetrain(array $records = [], bool $augmentSynthetic = true, int $syntheticPerClass = 100): array
    {
        if (! $this->isAlive()) {
            $trainScript = base_path('../ml_backend/train.py');
            if (file_exists($trainScript)) {
                $tmpJson = null;
                try {
                    $cmd = [
                        $this->resolvePythonBinary(),
                        $trainScript,
                        $augmentSynthetic ? '--generate-synthetic' : '--no-synthetic',
                        '--samples',
                        (string) $syntheticPerClass,
                    ];
                    if (! empty($records)) {
                        $tmpJson = tempnam(sys_get_temp_dir(), 'haven_records_').'.json';
                        file_put_contents($tmpJson, json_encode($records));
                        $cmd[] = '--records-json';
                        $cmd[] = $tmpJson;
                    }

                    $process = new Process($cmd);
                    $process->setTimeout(120);
                    $process->run();

                    if ($tmpJson && file_exists($tmpJson)) {
                        @unlink($tmpJson);
                    }

                    if ($process->isSuccessful()) {
                        $meta = $this->readLocalModelMeta();

                        return [
                            'status' => 'success',
                            'success' => true,
                            'message' => 'Random Forest model retrained successfully via Python pipeline with FFT & Explainable AI.',
                            'output' => trim($process->getOutput()),
                            'test_accuracy' => $meta['accuracy'] ?? null,
                            'f1_macro' => $meta['f1_macro'] ?? null,
                            'n_samples' => $meta['n_samples'] ?? ($syntheticPerClass * 4),
                            'meta' => $meta,
                        ];
                    }
                } catch (\Throwable $ex) {
                    if ($tmpJson && file_exists($tmpJson)) {
                        @unlink($tmpJson);
                    }
                    Log::warning('Local ML retrain process failed: '.$ex->getMessage());
                }
            }

            return [
                'status' => 'failure',
                'success' => false,
                'error' => 'ML Backend (FastAPI) is offline at '.$this->baseUrl.'. Ensure "uvicorn main:app --port 8001" or python is running.',
            ];
        }

        try {
            $response = Http::timeout(90)
                ->withHeaders(['X-ML-API-Secret' => $this->apiSecret])
                ->post("{$this->baseUrl}/train", [
                    'records' => $records,
                    'augment_synthetic' => $augmentSynthetic,
                    'synthetic_per_class' => $syntheticPerClass,
                ]);

            if ($response->successful()) {
                $json = $response->json() ?? [];
                $meta = $json['meta'] ?? [];

                return array_merge($json, [
                    'status' => 'success',
                    'success' => true,
                    'test_accuracy' => $meta['accuracy'] ?? ($json['test_accuracy'] ?? null),
                    'f1_macro' => $meta['f1_macro'] ?? ($json['f1_macro'] ?? null),
                    'n_samples' => $meta['n_samples'] ?? ($json['n_samples'] ?? 0),
                ]);
            }

            return [
                'status' => 'failure',
                'success' => false,
                'error' => 'Retraining failed: HTTP '.$response->status().' '.$response->body(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'failure',
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Submit a Diagnostic record to the ML backend for analysis.
     * Returns the parsed result array or throws on hard failure.
     */
    public function analyze(Diagnostic $diagnostic, bool $allowTransientFallback = true): array
    {
        // Telemetry-only diagnostics (no audio and no vibration file) use contextual heuristics directly
        if (! $diagnostic->hasAudio() && ! $diagnostic->hasVibration()) {
            return $this->mockAnalysis($diagnostic, 'telemetry_only');
        }

        // Check connectivity first
        if (! $this->isAlive()) {
            if (! $allowTransientFallback) {
                throw new \RuntimeException("ML backend unavailable at {$this->baseUrl} for diagnostic #{$diagnostic->id}");
            }
            Log::info("ML backend unavailable — using mock classifier for diagnostic #{$diagnostic->id}");

            return $this->mockAnalysis($diagnostic);
        }

        try {
            $request = Http::timeout($this->timeoutSec)
                ->withHeaders(['X-ML-API-Secret' => $this->apiSecret])
                ->asMultipart();

            $attachedFiles = 0;

            // Attach audio file if present (resolve from private disk first, fallback to public)
            if ($diagnostic->hasAudio()) {
                $audioDisk = Storage::disk('local')->exists($diagnostic->audio_path) ? 'local' : 'public';
                $audioPath = Storage::disk($audioDisk)->path($diagnostic->audio_path);
                if (file_exists($audioPath)) {
                    $request = $request->attach(
                        'audio_file',
                        file_get_contents($audioPath),
                        basename($diagnostic->audio_path)
                    );
                    $attachedFiles++;
                }
            }

            // Attach vibration file if present (resolve from private disk first, fallback to public)
            if ($diagnostic->hasVibration()) {
                $vibDisk = Storage::disk('local')->exists($diagnostic->vibration_path) ? 'local' : 'public';
                $vibPath = Storage::disk($vibDisk)->path($diagnostic->vibration_path);
                if (file_exists($vibPath)) {
                    $request = $request->attach(
                        'vibration_file',
                        file_get_contents($vibPath),
                        basename($diagnostic->vibration_path)
                    );
                    $attachedFiles++;
                }
            }

            if ($attachedFiles === 0) {
                return $this->mockAnalysis($diagnostic, 'missing_signal_files');
            }

            $params = [
                ['name' => 'diagnostic_id', 'contents' => (string) $diagnostic->id],
                ['name' => 'vehicle_id',    'contents' => (string) $diagnostic->vehicle_id],
            ];
            if (! is_null($diagnostic->temperature_c)) {
                $params[] = ['name' => 'temperature_c', 'contents' => (string) $diagnostic->temperature_c];
            }
            if (! is_null($diagnostic->rpm)) {
                $params[] = ['name' => 'rpm', 'contents' => (string) $diagnostic->rpm];
            }
            if (! is_null($diagnostic->voltage_v)) {
                $params[] = ['name' => 'voltage_v', 'contents' => (string) $diagnostic->voltage_v];
            }
            if (! is_null($diagnostic->current_ma)) {
                $params[] = ['name' => 'current_ma', 'contents' => (string) $diagnostic->current_ma];
            }
            if (! empty($diagnostic->recording_mode)) {
                $params[] = ['name' => 'recording_mode', 'contents' => (string) $diagnostic->recording_mode];
            }
            if (! empty($diagnostic->vibration_source)) {
                $params[] = ['name' => 'vibration_source', 'contents' => (string) $diagnostic->vibration_source];
            }
            if (! is_null($diagnostic->sample_rate_hz)) {
                $params[] = ['name' => 'sample_rate_hz', 'contents' => (string) $diagnostic->sample_rate_hz];
            }

            $response = $request->post("{$this->baseUrl}/analyze", $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("ML backend returned {$response->status()} for diagnostic #{$diagnostic->id}", [
                'body' => $response->body(),
            ]);

            if (! $allowTransientFallback && $response->serverError()) {
                throw new \RuntimeException("ML backend returned HTTP {$response->status()} for diagnostic #{$diagnostic->id}");
            }

            // Degraded: still mock rather than crash
            return $this->mockAnalysis($diagnostic, 'backend_error');

        } catch (ConnectionException $e) {
            Log::warning("ML backend connection failed: {$e->getMessage()}");
            if (! $allowTransientFallback) {
                throw $e;
            }

            return $this->mockAnalysis($diagnostic, 'connection_error');
        } catch (\Throwable $e) {
            Log::error("ML backend unexpected error: {$e->getMessage()}");
            if (! $allowTransientFallback) {
                throw $e;
            }

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
        $hasVibration = $diagnostic->hasVibration();
        $hasVerifiedAudio = $this->hasVerifiedAudioWave($diagnostic);
        $peakG = $diagnostic->vibration_peak_g ?? 0.0;
        $rmsG = $diagnostic->vibration_rms_g ?? 0.0;
        $crest = $rmsG > 0 ? round($peakG / $rmsG, 2) : 0.0;
        $samples = $diagnostic->vibration_sample_count ?? 0;
        $tempC = $diagnostic->temperature_c;
        $rpm = $diagnostic->rpm;
        $voltage = $diagnostic->voltage_v;
        $mode = $diagnostic->recording_mode ?? 'idle';

        // Rule-based mock using stored vibration & hardware sensor stats
        [$condition, $confidence, $topClasses, $sensorWarnings] = $this->mockRules(
            $peakG, $rmsG, $crest, $tempC, $rpm, $voltage, $mode, $diagnostic->vibration_source, $hasVerifiedAudio
        );

        $featuresUsed = $hasVibration ? ['peak_g', 'rms_g', 'crest_factor'] : [];
        if ($diagnostic->hasAudio()) {
            $featuresUsed[] = 'inmp441_audio_spectrum';
        }
        if (! is_null($tempC)) {
            $featuresUsed[] = 'ds18b20_temperature';
        }
        if (! is_null($rpm)) {
            $featuresUsed[] = 'hall_tachometer_rpm';
        }
        if (! is_null($voltage)) {
            $featuresUsed[] = 'ina219_battery_voltage';
        }

        $domHz = $hasVibration ? round(($rpm ? ($rpm / 60.0) : 14.0) * (match ($condition) {
            'valve_clearance' => 0.5,
            'loose_component' => 2.0,
            default => 1.0,
        }), 1) : 0.0;
        $entropy = $hasVibration ? (match ($condition) {
            'misfire' => 0.65,
            'valve_clearance' => 0.45,
            'loose_component' => 0.32,
            default => 0.25,
        }) : 0.0;

        $featureContributions = $this->mockExplainability($condition, $peakG, $rmsG, $crest, $domHz, $tempC, $rpm, $hasVibration);

        return [
            'condition' => $condition,
            'confidence' => $confidence,
            'confidence_pct' => round($confidence * 100, 1).'%',
            'top_classes' => $topClasses,
            'feature_contributions' => $featureContributions,
            'features_used' => $featuresUsed,
            'audio_features' => [],
            'vibration_features' => $hasVibration ? [
                'peak_g' => $peakG,
                'rms_g' => $rmsG,
                'crest_factor' => $crest,
                'dominant_freq_hz' => $domHz,
                'spectral_entropy' => $entropy,
                'sample_count' => $samples,
            ] : [],
            'hardware_telemetry' => [
                'temperature_c' => $tempC,
                'rpm' => $rpm,
                'voltage_v' => $voltage,
            ],
            'mode' => "mock_{$reason}",
            'warnings' => array_merge(
                ["ML server offline ({$reason}). Multi-sensor heuristic analysis applied."],
                $sensorWarnings
            ),
            'processed_at' => now()->toISOString(),
        ];
    }

    private function hasVerifiedAudioWave(Diagnostic $diagnostic): bool
    {
        if (! $diagnostic->hasAudio()) {
            return false;
        }

        try {
            $fullPath = null;
            foreach (['local', 'public'] as $diskName) {
                $candidate = Storage::disk($diskName)->path($diagnostic->audio_path);
                if (is_file($candidate)) {
                    $fullPath = $candidate;
                    break;
                }
            }
            if ($fullPath === null || filesize($fullPath) < 128) {
                return false;
            }

            $fh = @fopen($fullPath, 'rb');
            if (! $fh) {
                return false;
            }
            $buf = fread($fh, 16384);
            fclose($fh);

            if ($buf === false || strlen($buf) < 128) {
                return false;
            }

            // Verify genuine RIFF/WAVE container and non-silent PCM signal energy
            if (substr($buf, 0, 4) !== 'RIFF' || substr($buf, 8, 4) !== 'WAVE') {
                return false;
            }

            // Locate the 'data' chunk instead of assuming a fixed 44-byte header (LIST/JUNK chunks shift it).
            $dataPos = strpos($buf, 'data', 12);
            $pcm = $dataPos !== false ? substr($buf, $dataPos + 8) : substr($buf, 44);
            if (strlen($pcm) % 2 === 1) {
                $pcm = substr($pcm, 0, -1);
            }
            $samples = @unpack('s*', $pcm);
            if (empty($samples)) {
                return false;
            }

            $sumSq = 0.0;
            $n = count($samples);
            foreach ($samples as $s) {
                $norm = $s / 32768.0;
                $sumSq += $norm * $norm;
            }

            $rms = sqrt($sumSq / $n);

            return $rms >= 0.015;
        } catch (\Throwable) {
            return false;
        }
    }

    private function mockExplainability(string $condition, float $peakG, float $rmsG, float $crest, float $domHz, ?float $tempC, ?int $rpm, bool $hasVibration = true): array
    {
        $contributions = [];

        if ($condition === 'ambient_noise') {
            $contributions[] = [
                'feature' => 'mechanical_coupling',
                'label' => 'Engine Block Mechanical Coupling',
                'value' => round($rmsG, 2),
                'unit' => 'g RMS',
                'impact_pct' => 45.0,
                'explanation' => '0.00g mechanical vibration confirms sensor was uncoupled from an operating internal combustion engine.',
            ];
            $contributions[] = [
                'feature' => 'ambient_acoustic_profile',
                'label' => 'Acoustic Combustion Cadence',
                'value' => 0,
                'unit' => 'orders',
                'impact_pct' => 35.0,
                'explanation' => 'Microphone signal contains diffuse ambient/crowd noise without periodic cylinder firing harmonics.',
            ];
            $contributions[] = [
                'feature' => 'hardware_telemetry',
                'label' => 'Powertrain Operating Telemetry',
                'value' => 0,
                'unit' => 'sensors',
                'impact_pct' => 20.0,
                'explanation' => 'No active engine coolant thermal signature (>45°C) or alternator bus voltage (>13.2V) verified.',
            ];

            return $contributions;
        }

        if (! $hasVibration) {
            $contributions[] = [
                'feature' => 'acoustic_harmonic_envelope',
                'label' => 'Acoustic Harmonic Envelope',
                'value' => $rpm ?? 800,
                'unit' => 'RPM eq',
                'impact_pct' => 55.0,
                'explanation' => 'Audio-only/telemetry mode evaluated without mechanical vibration sensor data (vibration stream skipped).',
            ];
            if (! is_null($tempC)) {
                $contributions[] = [
                    'feature' => 'ds18b20_temperature',
                    'label' => 'Coolant Thermal Context',
                    'value' => round($tempC, 1),
                    'unit' => '°C',
                    'impact_pct' => 25.0,
                    'explanation' => "DS18B20 coolant temperature recorded at {$tempC}°C during session.",
                ];
            }
            if (! is_null($rpm)) {
                $contributions[] = [
                    'feature' => 'hall_tachometer_rpm',
                    'label' => 'Crankshaft Rotational Speed',
                    'value' => $rpm,
                    'unit' => 'RPM',
                    'impact_pct' => 20.0,
                    'explanation' => "Hall tachometer confirmed engine rotational speed of {$rpm} RPM.",
                ];
            }

            return $contributions;
        }

        if ($condition === 'valve_clearance') {
            $contributions[] = [
                'feature' => 'crest_factor',
                'label' => 'Crest Factor (Impulsiveness)',
                'value' => round($crest, 2),
                'unit' => 'ratio',
                'impact_pct' => 42.0,
                'explanation' => "High crest factor ({$crest} > 3.5) indicates sharp mechanical valvetrain impact lash.",
            ];
            $contributions[] = [
                'feature' => 'peak_g',
                'label' => 'Peak G Acceleration Spikes',
                'value' => round($peakG, 2),
                'unit' => 'g',
                'impact_pct' => 32.0,
                'explanation' => "Repetitive acceleration spikes ({$peakG}g) detected on cylinder head.",
            ];
            $contributions[] = [
                'feature' => 'dominant_freq_hz',
                'label' => 'FFT Valvetrain Harmonic',
                'value' => round($domHz, 1),
                'unit' => 'Hz',
                'impact_pct' => 26.0,
                'explanation' => 'Spectral energy concentrated around 0.5x crankshaft rotation speed.',
            ];
        } elseif ($condition === 'misfire') {
            $contributions[] = [
                'feature' => 'peak_g',
                'label' => 'Torque Dip Shockwave',
                'value' => round($peakG, 2),
                'unit' => 'g',
                'impact_pct' => 42.0,
                'explanation' => "Rotational deceleration shock ({$peakG}g) during combustion dropout strokes.",
            ];
            $contributions[] = [
                'feature' => 'crest_factor',
                'label' => 'Cyclic Impulse Crest Factor',
                'value' => round($crest, 2),
                'unit' => 'ratio',
                'impact_pct' => 32.0,
                'explanation' => 'Intermittent torque deficit spikes relative to baseline RMS energy.',
            ];
            $contributions[] = [
                'feature' => 'dominant_freq_hz',
                'label' => 'Sub-Harmonic Lurch',
                'value' => round($domHz, 1),
                'unit' => 'Hz',
                'impact_pct' => 26.0,
                'explanation' => 'Low-frequency cyclic lurch detected across consecutive 720° crank rotations.',
            ];
        } elseif ($condition === 'loose_component') {
            $contributions[] = [
                'feature' => 'rms_g',
                'label' => 'Continuous RMS Vibration Energy',
                'value' => round($rmsG, 3),
                'unit' => 'g',
                'impact_pct' => 45.0,
                'explanation' => "High continuous energy ({$rmsG}g) indicates worn engine mounts or loose bracket resonance.",
            ];
            $contributions[] = [
                'feature' => 'dominant_freq_hz',
                'label' => 'Structural Harmonic Resonance',
                'value' => round($domHz, 1),
                'unit' => 'Hz',
                'impact_pct' => 35.0,
                'explanation' => "Dominant vibration peak at {$domHz} Hz indicates mechanical component loose play.",
            ];
            $contributions[] = [
                'feature' => 'crest_factor',
                'label' => 'Continuous Non-Impulsive Waveform',
                'value' => round($crest, 2),
                'unit' => 'ratio',
                'impact_pct' => 20.0,
                'explanation' => 'Low impulsiveness confirms continuous vibration rather than isolated impact taps.',
            ];
        } else {
            $contributions[] = [
                'feature' => 'rms_g',
                'label' => 'Healthy Baseline RMS',
                'value' => round($rmsG, 3),
                'unit' => 'g',
                'impact_pct' => 40.0,
                'explanation' => "Vibration levels ({$rmsG}g) remain comfortably within factory baseline envelope.",
            ];
            $contributions[] = [
                'feature' => 'crest_factor',
                'label' => 'Stable Crest Factor',
                'value' => round($crest, 2),
                'unit' => 'ratio',
                'impact_pct' => 34.0,
                'explanation' => 'Harmonic waveform confirms smooth engine balance without abnormal impact shocks.',
            ];
            $contributions[] = [
                'feature' => 'peak_g',
                'label' => 'Gravity-Removed Peak Acceleration',
                'value' => round($peakG, 3),
                'unit' => 'g',
                'impact_pct' => 26.0,
                'explanation' => "Peak dynamic acceleration ({$peakG}g) shows no abnormal cylinder head shockwaves.",
            ];
        }

        return $contributions;
    }

    private function mockRules(float $peakG, float $rmsG, float $crest, ?float $tempC, ?int $rpm, ?float $voltage, string $mode = 'idle', ?string $vibrationSource = null, bool $hasVerifiedAudio = false): array
    {
        // ── Engine Presence & Ambient Noise Filter ──
        // An active ICE engine physically produces measurable mechanical vibration (at least 0.04g RMS / 0.08g Peak)
        // when physically coupled to the engine block via real hardware accelerometer (adxl345_live, device_sensor, or csv_import),
        // or operating engine coolant temperature (>45°C), or alternator charging (>=13.2V), or active RPM (>=300),
        // or a verified RIFF/WAVE acoustic capture when no engine-off telemetry (RPM=0 or resting battery <13.2V) contradicts it.
        $isHardwareVibration = (in_array($vibrationSource, ['adxl345_live', 'device_sensor', 'csv_import'], true) || (is_null($vibrationSource) && $rmsG > 0.04));
        $hasMechanicalCoupling = $isHardwareVibration && ($peakG >= 0.08 || $rmsG >= 0.04);
        $hasActiveTelemetry = (! is_null($tempC) && $tempC >= 45.0) || (! is_null($voltage) && $voltage >= 13.2) || (! is_null($rpm) && $rpm >= 300);
        $isExplicitlyEngineOff = (! is_null($rpm) && $rpm === 0) || (! is_null($voltage) && $voltage < 13.2 && (is_null($rpm) || $rpm < 300));
        $hasActiveAudioCapture = $hasVerifiedAudio && ! $isExplicitlyEngineOff;

        if (! $hasMechanicalCoupling && ! $hasActiveTelemetry && ! $hasActiveAudioCapture) {
            $gateWarning = 'SIGNAL GATE: No physical engine block vibration (hardware uncoupled) or operating telemetry detected. Ambient noise filter engaged.';

            return [
                'ambient_noise',
                0.95,
                [
                    ['condition' => 'ambient_noise', 'confidence' => 0.95],
                    ['condition' => 'normal', 'confidence' => 0.05],
                ],
                [$gateWarning],
            ];
        }

        // 4 Thesis-Approved Engine Fault Classes (UMak CET Capstone Scope)
        $classes = ['normal', 'misfire', 'loose_component', 'valve_clearance'];
        $scores = array_fill_keys($classes, 0.0);
        $warnings = [];

        // Temperature Telemetry Heuristics (DS18B20: Pin D4)
        if (! is_null($tempC)) {
            if ($tempC >= 105.0) {
                $warnings[] = "CRITICAL: Engine coolant temperature reached {$tempC}°C exceeding thermal threshold (105°C).";
                $scores['loose_component'] += 0.30;
            } elseif ($tempC >= 98.0) {
                $warnings[] = "WARNING: Elevated coolant temperature ({$tempC}°C). Inspect thermostat & cooling fans.";
            }
        }

        // Electrical Voltage Heuristics (INA219: Pins D21/D22)
        if (! is_null($voltage)) {
            if ($voltage < 12.2) {
                $warnings[] = "ELECTRICAL ALERT: Battery voltage low ({$voltage}V). Alternator charging system underperforming.";
                $scores['misfire'] += 0.15;
            } elseif ($voltage > 15.0) {
                $warnings[] = "ELECTRICAL ALERT: Over-voltage detected ({$voltage}V). Alternator regulator anomaly.";
            }
        }

        // 1. Improper Valve Clearance: Sharp metallic impact clicking at 0.5x RPM (high Crest Factor, impulsive G spikes)
        if ($crest > 4.0 || $peakG > 1.8) {
            $scores['valve_clearance'] += 0.65;
        } elseif ($crest > 3.5 && $peakG > 1.25) {
            $scores['valve_clearance'] += 0.35;
        }

        // 2. Engine Misfire: Combustion dropout every 720 deg crank rotation, erratic RPM lurch, cyclic torque dips
        if ($crest > 2.5 && $crest <= 4.2 && $peakG > 0.65 && $peakG < 1.9) {
            $scores['misfire'] += 0.45;
        }

        // 3. Loose or Worn Component Vibration: High continuous baseline RMS G, mount degradation
        if ($rmsG > 0.30 && $crest < 3.0) {
            $scores['loose_component'] += 0.50;
        } elseif ($rmsG > 0.26 && $peakG > 0.65) {
            $scores['loose_component'] += 0.30;
        }

        // Tachometer correlation (Hall Sensor D23)
        if (! is_null($rpm) && $rpm > 0) {
            if ($scores['valve_clearance'] > 0.3) {
                $warnings[] = "Tachometer correlation: Valvetrain harmonic impacts detected at {$rpm} RPM.";
            }
        }

        // 4. Normal Engine Operation: Gravity-removed baseline reading within healthy envelope (Idle vs Revving mode)
        $isRevving = ($mode === 'revving');
        $normalPeakLimit = $isRevving ? 0.85 : 0.55;
        $normalRmsLimit = $isRevving ? 0.28 : 0.22;

        if ($peakG <= $normalPeakLimit && $rmsG <= $normalRmsLimit && $crest <= 3.6) {
            $scores['normal'] += 0.50;
        } else {
            $scores['normal'] += 0.15;
        }

        $total = array_sum($scores) ?: 1.0;
        $probs = array_map(fn ($v) => round($v / $total, 4), $scores);

        arsort($probs);
        $condition = array_key_first($probs);
        $confidence = $probs[$condition];

        // Ensure realistic minimum confidence
        if ($confidence < 0.40) {
            $confidence = round(0.40 + ($peakG * 0.05), 4);
            $condition = 'normal';
        }

        $topClasses = array_slice(
            array_map(
                fn ($k, $v) => ['condition' => $k, 'confidence' => $v],
                array_keys($probs), array_values($probs)
            ),
            0, 3
        );

        return [$condition, $confidence, $topClasses, $warnings];
    }
}
