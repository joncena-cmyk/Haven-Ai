<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeDiagnosticJob;
use App\Models\Diagnostic;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use App\Services\DiagnosticAiService;
use App\Services\MlBackendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiagnosticController extends Controller
{
    /* ─────────────────────────────────────────────
       LIST  /diagnostics
    ───────────────────────────────────────────── */
    public function index(Request $request): View
    {
        $vehicles = Auth::user()->vehicles()->orderBy('make')->get();
        $selectedVid = $request->query('vehicle_id');

        $query = Auth::user()->diagnostics()->with('vehicle')->latest();

        // If a specific vehicle ID is requested, filter by it; otherwise show all vehicles in history
        if ($selectedVid && $selectedVid !== 'all') {
            $selectedVehicle = $vehicles->firstWhere('id', $selectedVid);
            if ($selectedVehicle) {
                $query->where('vehicle_id', $selectedVehicle->id);
            } else {
                $selectedVid = 'all';
                $selectedVehicle = null;
            }
        } else {
            $selectedVid = 'all';
            $selectedVehicle = null;
        }

        $diagnostics = $query->paginate(8)->withQueryString();

        // Per-vehicle stats
        $vehicleStats = [];
        foreach ($vehicles as $v) {
            $vDiags = Auth::user()->diagnostics()->where('vehicle_id', $v->id);
            $vehicleStats[$v->id] = [
                'total' => $vDiags->count(),
                'completed' => $vDiags->clone()->where('status', 'completed')->count(),
                'latest' => $vDiags->clone()->latest()->first(),
            ];
        }

        // Overall stats
        $total = Auth::user()->diagnostics()->count();
        $completed = Auth::user()->diagnostics()->where('status', 'completed')->count();
        $conditions = Auth::user()->diagnostics()->whereNotNull('ml_condition')
            ->selectRaw('ml_condition, count(*) as cnt')
            ->groupBy('ml_condition')->orderByDesc('cnt')->get();

        return view('diagnostics.index', compact(
            'diagnostics', 'vehicles', 'selectedVehicle',
            'selectedVid', 'vehicleStats', 'total', 'completed', 'conditions'
        ));
    }

    /* ─────────────────────────────────────────────
       CREATE FORM  /diagnostics/create
    ───────────────────────────────────────────── */
    public function create(Request $request): View
    {
        $vehicles = Auth::user()->vehicles()->orderBy('make')->get();
        $selectedVehicleId = $request->query('vehicle_id') ?? $vehicles->first()?->id;
        $recentDiagnostics = Auth::user()->diagnostics()->with('vehicle')->latest()->take(8)->get();

        return view('diagnostics.create', compact('vehicles', 'selectedVehicleId', 'recentDiagnostics'));
    }

    /* ─────────────────────────────────────────────
       STORE  POST /diagnostics
       Handles audio blob + vibration file upload.
    ───────────────────────────────────────────── */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'audio_blob' => ['nullable', 'file', 'mimes:webm,ogg,wav,mp3,mp4', 'max:51200'], // 50 MB
            'audio_file' => ['nullable', 'file', 'mimes:webm,ogg,wav,mp3,mp4,m4a', 'max:51200'],
            'vibration_file' => ['nullable', 'file', 'mimes:csv,json,txt', 'max:5120'], // 5 MB
            'vibration_source' => ['nullable', 'string', 'in:adxl345_live,device_sensor,csv_import,simulated,skipped'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'recording_mode' => ['nullable', 'string', 'in:idle,revving'],
            'temperature_c' => ['nullable', 'numeric', 'min:-40', 'max:200'],
            'rpm' => ['nullable', 'integer', 'min:0', 'max:12000'],
            'voltage_v' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'current_ma' => ['nullable', 'numeric', 'min:0', 'max:50000'],
            'true_condition' => ['nullable', 'string', 'in:normal,misfire,loose_component,valve_clearance'],
            'session_id' => ['nullable', 'string', 'max:100'],
            'fault_detail' => ['nullable', 'string', 'max:2000'],
            'target_rpm' => ['nullable', 'integer', 'min:0', 'max:12000'],
            'load_state' => ['nullable', 'string', 'max:100'],
            'sensor_position' => ['nullable', 'string', 'max:150'],
            'mic_position' => ['nullable', 'string', 'max:150'],
            'operator' => ['nullable', 'string', 'max:150'],
            'verified_by' => ['nullable', 'string', 'max:150'],
            'sample_rate_hz' => ['nullable', 'integer', 'min:1', 'max:192000'],
            'is_training_sample' => ['nullable', 'boolean'],
        ]);

        // Ensure vehicle belongs to user
        $vehicle = Vehicle::where('id', $request->vehicle_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $trueCondition = $request->filled('true_condition') ? $request->input('true_condition') : null;

        $data = [
            'user_id' => Auth::id(),
            'vehicle_id' => $vehicle->id,
            'notes' => $request->notes,
            'recording_mode' => $request->input('recording_mode', 'idle'),
            'status' => 'draft',
            'true_condition' => $trueCondition,
            'session_id' => $request->input('session_id'),
            'fault_detail' => $request->input('fault_detail'),
            'target_rpm' => $request->filled('target_rpm') ? (int) $request->input('target_rpm') : null,
            'load_state' => $request->input('load_state'),
            'sensor_position' => $request->input('sensor_position'),
            'mic_position' => $request->input('mic_position'),
            'operator' => $request->input('operator'),
            'verified_by' => $request->input('verified_by'),
            'sample_rate_hz' => $request->filled('sample_rate_hz') ? (int) $request->input('sample_rate_hz') : null,
            'is_training_sample' => $request->boolean('is_training_sample') && ! empty($trueCondition),
        ];

        // Auxiliary hardware metadata (DS18B20 Temp, Hall RPM, INA219 Voltage/Current):
        // Per thesis scope, strictly optional contextual metadata. Excluded from core ML feature vector.
        // If not explicitly captured from live ESP32 or provided by user, leave as null (no auto-generated fallbacks).
        $data['temperature_c'] = ($request->filled('temperature_c') && is_numeric($request->temperature_c))
            ? (float) $request->temperature_c
            : null;

        $data['rpm'] = ($request->filled('rpm') && is_numeric($request->rpm))
            ? (int) $request->rpm
            : null;

        $data['voltage_v'] = ($request->filled('voltage_v') && is_numeric($request->voltage_v))
            ? (float) $request->voltage_v
            : null;

        $data['current_ma'] = ($request->filled('current_ma') && is_numeric($request->current_ma))
            ? (float) $request->current_ma
            : null;

        // ── Audio file: prefer blob (recorded) over uploaded file ──
        $audioFile = $request->file('audio_blob') ?? $request->file('audio_file');
        if ($audioFile) {
            $dir = "diagnostics/{$vehicle->id}/audio";
            $path = $audioFile->store($dir, 'local');
            $data['audio_path'] = $path;
            $data['audio_original_name'] = $audioFile->getClientOriginalName();
            // Estimate duration (bytes / 16kbps) — actual duration set by JS via hidden input
            if ($request->filled('audio_duration')) {
                $data['audio_duration_sec'] = (int) $request->audio_duration;
            }
        }

        // ── Vibration file & sensor source ──
        $vibrationSource = $request->input('vibration_source');
        if ($request->hasFile('vibration_file')) {
            $vib = $request->file('vibration_file');
            $dir = "diagnostics/{$vehicle->id}/vibration";
            $path = $vib->store($dir, 'local');
            $data['vibration_path'] = $path;

            if (empty($vibrationSource)) {
                $vibrationSource = 'csv_import';
            }

            // Parse sample count, peak/RMS, and inferred sample rate from CSV if provided
            [$sampleCount, $peakG, $rmsG, $inferredRateHz] = $this->parseVibrationSummary($vib->getRealPath(), $vib->getClientOriginalExtension());
            $data['vibration_sample_count'] = $sampleCount;
            $data['vibration_peak_g'] = $peakG;
            $data['vibration_rms_g'] = $rmsG;
            if (empty($data['sample_rate_hz']) && ! is_null($inferredRateHz)) {
                $data['sample_rate_hz'] = $inferredRateHz;
            }
        } elseif ($vibrationSource === 'skipped') {
            $data['vibration_path'] = null;
        }

        $data['vibration_source'] = $vibrationSource;

        // Mark as submitted and auto-queue analysis if telemetry or files are present
        $hasData = ! empty($data['audio_path']) || ! empty($data['vibration_path']) || ! empty($data['temperature_c']) || ! empty($data['rpm']);
        if ($hasData) {
            $data['status'] = 'submitted';
        }

        $hasAuxTelemetry = isset($data['temperature_c']) || isset($data['rpm']) || isset($data['voltage_v']) || isset($data['current_ma']);
        if ($hasAuxTelemetry) {
            $data['sensor_data'] = [
                'ds18b20' => [
                    'pin' => 'D4',
                    'temp_c' => $data['temperature_c'] ?? null,
                    'role' => 'contextual_non_ml',
                ],
                'hall_rpm' => [
                    'pin' => 'D23',
                    'rpm' => $data['rpm'] ?? null,
                    'role' => 'contextual_non_ml',
                ],
                'ina219' => [
                    'pins' => 'D21/D22',
                    'voltage_v' => $data['voltage_v'] ?? null,
                    'current_ma' => $data['current_ma'] ?? null,
                    'role' => 'contextual_non_ml',
                ],
                'mpu6050' => [
                    'pins' => 'D21/D22',
                    'peak_g' => $data['vibration_peak_g'] ?? null,
                    'rms_g' => $data['vibration_rms_g'] ?? null,
                    'role' => 'ml_feature',
                ],
                'inmp441' => [
                    'pins' => 'D18/D19/D5',
                    'has_audio' => ! empty($data['audio_path']),
                    'duration_sec' => $data['audio_duration_sec'] ?? null,
                    'role' => 'ml_feature',
                ],
            ];
        }

        $diagnostic = Diagnostic::create($data);

        if ($data['status'] === 'submitted') {
            if (config('queue.default') === 'sync' || ! app()->environment('production')) {
                AnalyzeDiagnosticJob::dispatchSync($diagnostic);
                $msg = 'Diagnostic session created! Analysis processed via synchronous ML pipeline.';
            } else {
                AnalyzeDiagnosticJob::dispatch($diagnostic);
                $msg = 'Diagnostic session created! Analysis queued for dual-tier ML processing in the background.';
            }
        } else {
            $msg = 'Diagnostic saved as draft. Add audio, vibration data, or telemetry to run analysis.';
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'redirect_url' => route('diagnostics.show', $diagnostic),
                'diagnostic_id' => $diagnostic->id,
            ]);
        }

        return redirect()->route('diagnostics.show', $diagnostic)->with('success', $msg);
    }

    /* ─────────────────────────────────────────────
       SHOW  /diagnostics/{diagnostic}
    ───────────────────────────────────────────── */
    public function show(Diagnostic $diagnostic, DiagnosticAiService $aiService): View
    {
        $this->authorizeOwner($diagnostic);
        $diagnostic->load('vehicle');
        $recentDiagnostics = Auth::user()->diagnostics()->with('vehicle')->latest()->take(8)->get();

        $aiRecommendations = ($diagnostic->status === 'completed' && $diagnostic->ml_condition)
            ? $aiService->generateRecommendations($diagnostic)
            : null;

        // Resolve vehicle reference baseline (prefer 'normal' session or prior completed session)
        $baseline = Diagnostic::where('vehicle_id', $diagnostic->vehicle_id)
            ->where('status', 'completed')
            ->where('id', '!=', $diagnostic->id)
            ->orderByRaw("CASE WHEN ml_condition = 'normal' THEN 0 ELSE 1 END")
            ->latest('id')
            ->first();

        $comparison = null;
        if ($baseline && $diagnostic->status === 'completed') {
            $curCrest = ($diagnostic->vibration_rms_g > 0 && $diagnostic->vibration_peak_g > 0)
                ? ($diagnostic->vibration_peak_g / $diagnostic->vibration_rms_g) : null;
            $baseCrest = ($baseline->vibration_rms_g > 0 && $baseline->vibration_peak_g > 0)
                ? ($baseline->vibration_peak_g / $baseline->vibration_rms_g) : null;

            $comparison = [
                'baseline_id' => $baseline->id,
                'baseline_date' => $baseline->created_at->format('M j, Y'),
                'baseline_condition' => $baseline->ml_condition,
                'peak_g' => [
                    'current' => $diagnostic->vibration_peak_g,
                    'baseline' => $baseline->vibration_peak_g,
                    'delta' => ($diagnostic->vibration_peak_g !== null && $baseline->vibration_peak_g !== null)
                        ? round($diagnostic->vibration_peak_g - $baseline->vibration_peak_g, 3) : null,
                    'pct' => ($baseline->vibration_peak_g > 0 && $diagnostic->vibration_peak_g !== null)
                        ? round((($diagnostic->vibration_peak_g - $baseline->vibration_peak_g) / $baseline->vibration_peak_g) * 100, 1) : null,
                ],
                'rms_g' => [
                    'current' => $diagnostic->vibration_rms_g,
                    'baseline' => $baseline->vibration_rms_g,
                    'delta' => ($diagnostic->vibration_rms_g !== null && $baseline->vibration_rms_g !== null)
                        ? round($diagnostic->vibration_rms_g - $baseline->vibration_rms_g, 3) : null,
                    'pct' => ($baseline->vibration_rms_g > 0 && $diagnostic->vibration_rms_g !== null)
                        ? round((($diagnostic->vibration_rms_g - $baseline->vibration_rms_g) / $baseline->vibration_rms_g) * 100, 1) : null,
                ],
                'crest_factor' => [
                    'current' => $curCrest ? round($curCrest, 2) : null,
                    'baseline' => $baseCrest ? round($baseCrest, 2) : null,
                    'delta' => ($curCrest !== null && $baseCrest !== null) ? round($curCrest - $baseCrest, 2) : null,
                ],
                'temperature_c' => [
                    'current' => $diagnostic->temperature_c,
                    'baseline' => $baseline->temperature_c,
                    'delta' => ($diagnostic->temperature_c !== null && $baseline->temperature_c !== null)
                        ? round($diagnostic->temperature_c - $baseline->temperature_c, 1) : null,
                ],
                'rpm' => [
                    'current' => $diagnostic->rpm,
                    'baseline' => $baseline->rpm,
                    'delta' => ($diagnostic->rpm !== null && $baseline->rpm !== null)
                        ? ($diagnostic->rpm - $baseline->rpm) : null,
                ],
            ];
        }

        return view('diagnostics.show', compact('diagnostic', 'aiRecommendations', 'recentDiagnostics', 'baseline', 'comparison'));
    }

    /* ─────────────────────────────────────────────
       REPORT  GET /diagnostics/{diagnostic}/report
    ───────────────────────────────────────────── */
    public function report(Diagnostic $diagnostic): View
    {
        $this->authorizeOwner($diagnostic);
        $diagnostic->load('vehicle');

        return view('diagnostics.report', compact('diagnostic'));
    }

    /* ─────────────────────────────────────────────
       STATUS  GET /diagnostics/{diagnostic}/status
       Used by UI polling for async analysis.
    ───────────────────────────────────────────── */
    public function status(Diagnostic $diagnostic): JsonResponse
    {
        $this->authorizeOwner($diagnostic);

        return response()->json([
            'id' => $diagnostic->id,
            'status' => $diagnostic->status,
            'status_label' => $diagnostic->status_label,
            'status_badge' => $diagnostic->status_badge,
            'ml_condition' => $diagnostic->ml_condition,
            'ml_confidence' => $diagnostic->ml_confidence,
            'confidence_pct' => $diagnostic->ml_result_raw['confidence_pct'] ?? null,
            'processed_at' => $diagnostic->ml_processed_at?->diffForHumans(),
            'updated_at' => $diagnostic->updated_at->toIso8601String(),
        ]);
    }

    /* ─────────────────────────────────────────────
       ANALYZE  POST /diagnostics/{diagnostic}/analyze
       Submits stored files to the ML backend via
       background queue worker (AnalyzeDiagnosticJob).
    ───────────────────────────────────────────── */
    public function analyze(Request $request, Diagnostic $diagnostic, MlBackendService $ml): RedirectResponse|JsonResponse
    {
        $this->authorizeOwner($diagnostic);

        if (! $diagnostic->hasAudio() && ! $diagnostic->hasVibration() && ! $diagnostic->hasMultiSensor()) {
            return back()->with('error', 'No telemetry, audio, or vibration data to analyze. Please complete the diagnostic session first.');
        }

        // Mark as processing
        $diagnostic->update(['status' => 'processing']);

        // Synchronous fallback if requested or if sync queue driver or local environment
        if ($request->has('sync') || config('queue.default') === 'sync' || ! app()->environment('production')) {
            try {
                $result = $ml->analyze($diagnostic);

                $vibFeats = is_array($result['vibration_features'] ?? null) ? $result['vibration_features'] : [];
                $updatePayload = [
                    'status' => 'completed',
                    'ml_condition' => $result['condition'] ?? null,
                    'ml_confidence' => $result['confidence'] ?? null,
                    'ml_result_raw' => $result,
                    'ml_processed_at' => now(),
                ];
                if (isset($vibFeats['peak_g']) && is_numeric($vibFeats['peak_g']) && (float) $vibFeats['peak_g'] > 0) {
                    $updatePayload['vibration_peak_g'] = round((float) $vibFeats['peak_g'], 4);
                }
                if (isset($vibFeats['rms_g']) && is_numeric($vibFeats['rms_g']) && (float) $vibFeats['rms_g'] > 0) {
                    $updatePayload['vibration_rms_g'] = round((float) $vibFeats['rms_g'], 4);
                }
                if (empty($diagnostic->sample_rate_hz) && ! empty($vibFeats['sample_rate_hz']) && (float) $vibFeats['sample_rate_hz'] > 0) {
                    $updatePayload['sample_rate_hz'] = (int) round((float) $vibFeats['sample_rate_hz']);
                }

                $diagnostic->update($updatePayload);

                $condition = ucfirst($result['condition'] ?? 'unknown');
                $confidence = $result['confidence_pct'] ?? '';

                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'status' => 'completed',
                        'condition' => $condition,
                        'confidence' => $confidence,
                    ]);
                }

                return redirect()->route('diagnostics.show', $diagnostic)
                    ->with('success', "Analysis complete: {$condition} — {$confidence} confidence.");

            } catch (\Throwable $e) {
                Log::error("Diagnostic analysis failed for #{$diagnostic->id}: {$e->getMessage()}");
                $diagnostic->update(['status' => 'failed']);

                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'error' => 'Analysis failed'], 500);
                }

                return redirect()->route('diagnostics.show', $diagnostic)
                    ->with('error', 'ML analysis failed. Check that the backend server is running.');
            }
        }

        // Dispatch background queue job (Stage 8 Async Architecture)
        AnalyzeDiagnosticJob::dispatch($diagnostic);

        AuditLogger::log('diagnostic.analysis_queued', [
            'diagnostic_id' => $diagnostic->id,
            'vehicle_id' => $diagnostic->vehicle_id,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'status' => 'processing',
                'message' => 'Diagnostic analysis queued successfully.',
                'status_url' => route('diagnostics.status', $diagnostic),
            ]);
        }

        return redirect()->route('diagnostics.show', $diagnostic)
            ->with('success', 'Diagnostic analysis queued! Telemetry is processing in the background.');
    }

    /* ─────────────────────────────────────────────
       DESTROY  DELETE /diagnostics/{diagnostic}
    ───────────────────────────────────────────── */
    public function destroy(Diagnostic $diagnostic): RedirectResponse
    {
        $this->authorizeOwner($diagnostic);

        AuditLogger::log('diagnostic.deleted', [
            'diagnostic_id' => $diagnostic->id,
            'vehicle_id' => $diagnostic->vehicle_id,
        ]);

        // Clean up stored files across private and legacy public disks
        if ($diagnostic->audio_path) {
            Storage::disk('local')->delete($diagnostic->audio_path);
            Storage::disk('public')->delete($diagnostic->audio_path);
        }
        if ($diagnostic->vibration_path) {
            Storage::disk('local')->delete($diagnostic->vibration_path);
            Storage::disk('public')->delete($diagnostic->vibration_path);
        }

        $diagnostic->delete();

        return redirect()->route('diagnostics.index')->with('success', 'Diagnostic session deleted.');
    }

    /* ─────────────────────────────────────────────
       STREAM AUDIO  GET /diagnostics/{diagnostic}/audio
       Owner-checked private audio stream.
    ───────────────────────────────────────────── */
    public function streamAudio(Diagnostic $diagnostic): StreamedResponse
    {
        $this->authorizeOwner($diagnostic);

        abort_if(empty($diagnostic->audio_path), 404, 'No audio recording found.');

        $disk = $this->resolveFileDisk($diagnostic->audio_path);
        abort_if($disk === null, 404, 'Audio recording file not found.');

        return Storage::disk($disk)->response($diagnostic->audio_path);
    }

    /* ─────────────────────────────────────────────
       STREAM VIBRATION  GET /diagnostics/{diagnostic}/vibration
       Owner-checked private vibration file download.
    ───────────────────────────────────────────── */
    public function streamVibration(Diagnostic $diagnostic): StreamedResponse
    {
        $this->authorizeOwner($diagnostic);

        abort_if(empty($diagnostic->vibration_path), 404, 'No vibration file found.');

        $disk = $this->resolveFileDisk($diagnostic->vibration_path);
        abort_if($disk === null, 404, 'Vibration telemetry file not found.');

        return Storage::disk($disk)->download(
            $diagnostic->vibration_path,
            basename($diagnostic->vibration_path)
        );
    }

    private function resolveFileDisk(string $relativePath): ?string
    {
        foreach (['local', 'private', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($relativePath)) {
                return $disk;
            }
        }

        return null;
    }

    /* ─────────────────────────────────────────────
       UPDATE LABEL  PATCH /diagnostics/{diagnostic}/label
       Saves ground-truth label and session metadata without modifying ml_condition.
    ───────────────────────────────────────────── */
    public function updateLabel(Request $request, Diagnostic $diagnostic): RedirectResponse|JsonResponse
    {
        $this->authorizeOwner($diagnostic);

        $validated = $request->validate([
            'true_condition' => ['nullable', 'string', 'in:normal,misfire,loose_component,valve_clearance'],
            'session_id' => ['nullable', 'string', 'max:100'],
            'fault_detail' => ['nullable', 'string', 'max:2000'],
            'target_rpm' => ['nullable', 'integer', 'min:0', 'max:12000'],
            'load_state' => ['nullable', 'string', 'max:100'],
            'sensor_position' => ['nullable', 'string', 'max:150'],
            'mic_position' => ['nullable', 'string', 'max:150'],
            'operator' => ['nullable', 'string', 'max:150'],
            'verified_by' => ['nullable', 'string', 'max:150'],
            'sample_rate_hz' => ['nullable', 'integer', 'min:1', 'max:192000'],
            'is_training_sample' => ['nullable', 'boolean'],
        ]);

        $trueCondition = ! empty($validated['true_condition']) ? $validated['true_condition'] : null;

        $diagnostic->update([
            'true_condition' => $trueCondition,
            'session_id' => $validated['session_id'] ?? null,
            'fault_detail' => $validated['fault_detail'] ?? null,
            'target_rpm' => $validated['target_rpm'] ?? null,
            'load_state' => $validated['load_state'] ?? null,
            'sensor_position' => $validated['sensor_position'] ?? null,
            'mic_position' => $validated['mic_position'] ?? null,
            'operator' => $validated['operator'] ?? null,
            'verified_by' => $validated['verified_by'] ?? null,
            'sample_rate_hz' => $validated['sample_rate_hz'] ?? null,
            'is_training_sample' => $request->boolean('is_training_sample') && ! empty($trueCondition),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Ground-truth label and session metadata saved.',
                'diagnostic' => $diagnostic->fresh(),
            ]);
        }

        return redirect()
            ->route('diagnostics.show', $diagnostic)
            ->with('status', 'Ground-truth label and session metadata saved.');
    }

    /* ─────────────────────────────────────────────
       EXPORT DATASET  GET /diagnostics/export/dataset
    ───────────────────────────────────────────── */
    public function exportDataset(): StreamedResponse
    {
        $diagnostics = Auth::user()->diagnostics()
            ->with('vehicle')
            ->where('is_training_sample', true)
            ->whereNotNull('true_condition')
            ->whereIn('true_condition', Diagnostic::TRAINING_CONDITIONS)
            ->where(function ($q) {
                $q->whereNull('vibration_source')
                    ->orWhere('vibration_source', '!=', 'simulated');
            })
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="haven_training_dataset_'.date('Y-m-d').'.csv"',
        ];

        return response()->stream(function () use ($diagnostics) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'diagnostic_id', 'session_id', 'vehicle_id', 'vehicle', 'engine', 'vibration_source',
                'sample_rate_hz', 'peak_g', 'rms_g', 'crest_factor',
                'dominant_freq_hz', 'spectral_entropy',
                'rms_mean', 'zcr_mean', 'spectral_centroid_mean', 'spectral_rolloff_mean',
                'mfcc_1_mean', 'mfcc_2_mean', 'mfcc_3_mean', 'mfcc_4_mean', 'mfcc_5_mean',
                'temperature_c', 'rpm', 'target_rpm', 'load_state',
                'sensor_position', 'mic_position', 'operator', 'verified_by',
                'voltage_v', 'current_ma', 'true_condition', 'fault_detail',
            ]);

            foreach ($diagnostics as $diag) {
                $raw = is_array($diag->ml_result_raw) ? $diag->ml_result_raw : [];
                $audFeat = is_array($raw['audio_features'] ?? null) ? $raw['audio_features'] : [];
                $vibFeat = is_array($raw['vibration_features'] ?? null) ? $raw['vibration_features'] : [];
                $peak = $vibFeat['peak_g'] ?? $diag->vibration_peak_g;
                $rms = $vibFeat['rms_g'] ?? $diag->vibration_rms_g;
                $crest = $vibFeat['crest_factor'] ?? (($rms > 0 && $peak > 0)
                    ? round($peak / $rms, 3)
                    : null);

                fputcsv($handle, [
                    $diag->id,
                    $diag->session_id,
                    $diag->vehicle_id,
                    $diag->vehicle?->display_name ?? 'N/A',
                    $diag->vehicle?->engine_type ?? 'N/A',
                    $diag->vibration_source ?? 'physical',
                    $diag->sample_rate_hz,
                    $peak,
                    $rms,
                    $crest,
                    $vibFeat['dominant_freq_hz'] ?? null,
                    $vibFeat['spectral_entropy'] ?? null,
                    $audFeat['rms_mean'] ?? ($audFeat['rms_audio'] ?? null),
                    $audFeat['zcr_mean'] ?? null,
                    $audFeat['spectral_centroid_mean'] ?? null,
                    $audFeat['spectral_rolloff_mean'] ?? null,
                    $audFeat['mfcc_1_mean'] ?? null,
                    $audFeat['mfcc_2_mean'] ?? null,
                    $audFeat['mfcc_3_mean'] ?? null,
                    $audFeat['mfcc_4_mean'] ?? null,
                    $audFeat['mfcc_5_mean'] ?? null,
                    $diag->temperature_c,
                    $diag->rpm,
                    $diag->target_rpm,
                    $diag->load_state,
                    $diag->sensor_position,
                    $diag->mic_position,
                    $diag->operator,
                    $diag->verified_by,
                    $diag->voltage_v,
                    $diag->current_ma,
                    $diag->true_condition,
                    $diag->fault_detail,
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    /* ─────────────────────────────────────────────
       HELPERS
    ───────────────────────────────────────────── */

    private function authorizeOwner(Diagnostic $diagnostic): void
    {
        abort_if($diagnostic->user_id !== Auth::id(), 403);
    }

    /**
     * Parse a vibration CSV/JSON file and return [sampleCount, peakG, rmsG, inferredRateHz].
     * Dynamic vibration cancels the static 1G gravity/tilt vector (AC dynamic oscillation).
     * Expected CSV columns: timestamp/t_us, x, y, z  (or just: timestamp, magnitude)
     */
    private function parseVibrationSummary(string $filePath, string $ext): array
    {
        $inferredRateHz = null;
        try {
            if (strtolower($ext) === 'json') {
                $json = json_decode(file_get_contents($filePath), true) ?? [];
                $samples = is_array($json) ? $json : [];
                $xyz = [];
                $scalars = [];
                foreach ($samples as $s) {
                    if (is_array($s) && isset($s['x'], $s['y'], $s['z'])) {
                        $xyz[] = [(float) $s['x'], (float) $s['y'], (float) $s['z']];
                    } elseif (is_array($s) && (isset($s['magnitude']) || isset($s['z']))) {
                        $scalars[] = (float) ($s['magnitude'] ?? $s['z']);
                    } elseif (is_numeric($s)) {
                        $scalars[] = (float) $s;
                    }
                }
                // Remove static gravity/DC exactly like the CSV path below.
                $mags = [];
                if (! empty($xyz)) {
                    $n = count($xyz);
                    $mx = array_sum(array_column($xyz, 0)) / $n;
                    $my = array_sum(array_column($xyz, 1)) / $n;
                    $mz = array_sum(array_column($xyz, 2)) / $n;
                    foreach ($xyz as [$x, $y, $z]) {
                        $mags[] = sqrt((($x - $mx) ** 2) + (($y - $my) ** 2) + (($z - $mz) ** 2));
                    }
                } elseif (! empty($scalars)) {
                    $mean = array_sum($scalars) / count($scalars);
                    $mags = array_map(fn ($v) => abs($v - $mean), $scalars);
                }
            } else {
                // CSV: skip comment lines (#) and non-numeric header rows
                $lines = array_filter(array_map('trim', file($filePath)));
                $rows = [];
                $tVals = [];
                $isMicrosHeader = false;

                foreach ($lines as $line) {
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    $cols = str_getcsv($line);
                    if (empty($cols)) {
                        continue;
                    }
                    $firstCol = strtolower(trim((string) $cols[0]));
                    if (! is_numeric($firstCol)) {
                        if (str_contains($firstCol, 't_us') || str_contains($firstCol, 'micros')) {
                            $isMicrosHeader = true;
                        }

                        continue;
                    }

                    $tVals[] = (float) $cols[0];
                    if (count($cols) >= 4) {
                        $rows[] = [(float) $cols[1], (float) $cols[2], (float) $cols[3]];
                    } elseif (count($cols) >= 2) {
                        $rows[] = [(float) $cols[1]];
                    }
                }

                if (empty($rows)) {
                    return [0, null, null, null];
                }

                $count = count($rows);
                if (count($tVals) >= 10) {
                    $span = $tVals[count($tVals) - 1] - $tVals[0];
                    if ($span > 0) {
                        if ($isMicrosHeader || $span > 1e5) {
                            $rate = ($count - 1) / ($span / 1e6);
                        } else {
                            $rate = ($count - 1) / $span;
                        }
                        if ($rate >= 10 && $rate <= 50000) {
                            $inferredRateHz = (int) round($rate);
                        }
                    }
                }

                if (count($rows[0]) >= 3) {
                    // Compute DC mean vector (x, y, z) to cancel static 1.0G Earth gravity vector
                    $meanX = array_sum(array_column($rows, 0)) / $count;
                    $meanY = array_sum(array_column($rows, 1)) / $count;
                    $meanZ = array_sum(array_column($rows, 2)) / $count;

                    $mags = [];
                    foreach ($rows as $r) {
                        $dx = $r[0] - $meanX;
                        $dy = $r[1] - $meanY;
                        $dz = $r[2] - $meanZ;
                        $mags[] = sqrt(($dx ** 2) + ($dy ** 2) + ($dz ** 2));
                    }
                } else {
                    $mean = array_sum(array_column($rows, 0)) / $count;
                    $mags = array_map(fn ($r) => abs($r[0] - $mean), $rows);
                }
            }

            if (empty($mags)) {
                return [0, null, null, $inferredRateHz];
            }

            $count = count($mags);
            $peakG = round(max($mags), 4);
            $rmsG = round(sqrt(array_sum(array_map(fn ($v) => $v ** 2, $mags)) / $count), 4);

            return [$count, $peakG, $rmsG, $inferredRateHz];
        } catch (\Throwable) {
            return [0, null, null, null];
        }
    }
}
