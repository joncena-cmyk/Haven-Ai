<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeDiagnosticJob;
use App\Models\Diagnostic;
use App\Models\Vehicle;
use App\Services\MlBackendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DiagnosticController extends Controller
{
    /* ─────────────────────────────────────────────
       LIST  /diagnostics
    ───────────────────────────────────────────── */
    public function index(): View
    {
        $diagnostics = Auth::user()
            ->diagnostics()
            ->with('vehicle')
            ->latest()
            ->paginate(15);

        return view('diagnostics.index', compact('diagnostics'));
    }

    /* ─────────────────────────────────────────────
       CREATE FORM  /diagnostics/create
    ───────────────────────────────────────────── */
    public function create(Request $request): View
    {
        $vehicles = Auth::user()->vehicles()->orderBy('make')->get();
        $selectedVehicleId = $request->query('vehicle_id');

        return view('diagnostics.create', compact('vehicles', 'selectedVehicleId'));
    }

    /* ─────────────────────────────────────────────
       STORE  POST /diagnostics
       Handles audio blob + vibration file upload.
    ───────────────────────────────────────────── */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'vehicle_id'     => ['required', 'integer', 'exists:vehicles,id'],
            'audio_blob'     => ['nullable', 'file', 'mimes:webm,ogg,wav,mp3,mp4', 'max:51200'], // 50 MB
            'audio_file'     => ['nullable', 'file', 'mimes:webm,ogg,wav,mp3,mp4,m4a', 'max:51200'],
            'vibration_file' => ['nullable', 'file', 'mimes:csv,json,txt', 'max:5120'], // 5 MB
            'notes'          => ['nullable', 'string', 'max:1000'],
            'recording_mode' => ['nullable', 'string', 'in:idle,revving'],
            'temperature_c'  => ['nullable', 'numeric', 'min:-40', 'max:200'],
            'rpm'            => ['nullable', 'integer', 'min:0', 'max:12000'],
            'voltage_v'      => ['nullable', 'numeric', 'min:0', 'max:50'],
            'current_ma'     => ['nullable', 'numeric', 'min:0', 'max:50000'],
        ]);

        // Ensure vehicle belongs to user
        $vehicle = Vehicle::where('id', $request->vehicle_id)
                          ->where('user_id', Auth::id())
                          ->firstOrFail();

        $data = [
            'user_id'        => Auth::id(),
            'vehicle_id'     => $vehicle->id,
            'notes'          => $request->notes,
            'recording_mode' => $request->input('recording_mode', 'idle'),
            'status'         => 'draft',
        ];

        if ($request->filled('temperature_c')) $data['temperature_c'] = $request->temperature_c;
        if ($request->filled('rpm')) {
            $data['rpm'] = $request->rpm;
        } else {
            // Calibrate default reference RPM based on recording mode if not provided by hardware sensor
            $data['rpm'] = ($data['recording_mode'] === 'revving') ? 2200 : 800;
        }
        if ($request->filled('voltage_v'))     $data['voltage_v']     = $request->voltage_v;
        if ($request->filled('current_ma'))    $data['current_ma']    = $request->current_ma;

        // ── Audio file: prefer blob (recorded) over uploaded file ──
        $audioFile = $request->file('audio_blob') ?? $request->file('audio_file');
        if ($audioFile) {
            $dir  = "diagnostics/{$vehicle->id}/audio";
            $path = $audioFile->store($dir, 'public');
            $data['audio_path']          = $path;
            $data['audio_original_name'] = $audioFile->getClientOriginalName();
            // Estimate duration (bytes / 16kbps) — actual duration set by JS via hidden input
            if ($request->filled('audio_duration')) {
                $data['audio_duration_sec'] = (int) $request->audio_duration;
            }
        }

        // ── Vibration file ──
        if ($request->hasFile('vibration_file')) {
            $vib  = $request->file('vibration_file');
            $dir  = "diagnostics/{$vehicle->id}/vibration";
            $path = $vib->store($dir, 'public');
            $data['vibration_path'] = $path;

            // Parse sample count and peak/RMS from CSV if provided
            [$sampleCount, $peakG, $rmsG] = $this->parseVibrationSummary($vib->getRealPath(), $vib->getClientOriginalExtension());
            $data['vibration_sample_count'] = $sampleCount;
            $data['vibration_peak_g']       = $peakG;
            $data['vibration_rms_g']        = $rmsG;
        }

        // Mark as submitted and auto-queue analysis if telemetry or files are present
        $hasData = !empty($data['audio_path']) || !empty($data['vibration_path']) || !empty($data['temperature_c']) || !empty($data['rpm']);
        if ($hasData) {
            $data['status'] = 'submitted';
        }

        $diagnostic = Diagnostic::create($data);

        if ($data['status'] === 'submitted') {
            AnalyzeDiagnosticJob::dispatch($diagnostic);
            $msg = 'Diagnostic session created! Analysis queued for dual-tier ML processing in the background.';
        } else {
            $msg = 'Diagnostic saved as draft. Add audio, vibration data, or telemetry to run analysis.';
        }

        return redirect()->route('diagnostics.show', $diagnostic)->with('success', $msg);
    }

    /* ─────────────────────────────────────────────
       SHOW  /diagnostics/{diagnostic}
    ───────────────────────────────────────────── */
    public function show(Diagnostic $diagnostic, \App\Services\DiagnosticAiService $aiService): View
    {
        $this->authorizeOwner($diagnostic);
        $diagnostic->load('vehicle');

        $aiRecommendations = ($diagnostic->status === 'completed' && $diagnostic->ml_condition)
            ? $aiService->generateRecommendations($diagnostic)
            : null;

        return view('diagnostics.show', compact('diagnostic', 'aiRecommendations'));
    }

    /* ─────────────────────────────────────────────
       REPORT  GET /diagnostics/{diagnostic}/report
    ───────────────────────────────────────────── */
    public function report(Diagnostic $diagnostic): \Illuminate\View\View
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
            'id'             => $diagnostic->id,
            'status'         => $diagnostic->status,
            'status_label'   => $diagnostic->status_label,
            'status_badge'   => $diagnostic->status_badge,
            'ml_condition'   => $diagnostic->ml_condition,
            'ml_confidence'  => $diagnostic->ml_confidence,
            'confidence_pct' => $diagnostic->ml_result_raw['confidence_pct'] ?? null,
            'processed_at'   => $diagnostic->ml_processed_at?->diffForHumans(),
            'updated_at'     => $diagnostic->updated_at->toIso8601String(),
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

        // Synchronous fallback if requested or if sync queue driver
        if ($request->has('sync') || config('queue.default') === 'sync') {
            try {
                $result = $ml->analyze($diagnostic);

                $diagnostic->update([
                    'status'          => 'completed',
                    'ml_condition'    => $result['condition']    ?? null,
                    'ml_confidence'   => $result['confidence']   ?? null,
                    'ml_result_raw'   => $result,
                    'ml_processed_at' => now(),
                ]);

                $condition  = ucfirst($result['condition']  ?? 'unknown');
                $confidence = $result['confidence_pct'] ?? '';

                if ($request->wantsJson()) {
                    return response()->json([
                        'success'   => true,
                        'status'    => 'completed',
                        'condition' => $condition,
                        'confidence'=> $confidence,
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

        \App\Services\AuditLogger::log('diagnostic.analysis_queued', [
            'diagnostic_id' => $diagnostic->id,
            'vehicle_id'    => $diagnostic->vehicle_id,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success'    => true,
                'status'     => 'processing',
                'message'    => 'Diagnostic analysis queued successfully.',
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

        \App\Services\AuditLogger::log('diagnostic.deleted', [
            'diagnostic_id' => $diagnostic->id,
            'vehicle_id'    => $diagnostic->vehicle_id,
        ]);

        // Clean up stored files
        if ($diagnostic->audio_path) {
            Storage::disk('public')->delete($diagnostic->audio_path);
        }
        if ($diagnostic->vibration_path) {
            Storage::disk('public')->delete($diagnostic->vibration_path);
        }

        $diagnostic->delete();

        return redirect()->route('diagnostics.index')->with('success', 'Diagnostic session deleted.');
    }

    /* ─────────────────────────────────────────────
       EXPORT DATASET  GET /diagnostics/export/dataset
    ───────────────────────────────────────────── */
    public function exportDataset(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $diagnostics = Auth::user()->diagnostics()->with('vehicle')->whereNotNull('ml_condition')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="haven_training_dataset_' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($diagnostics) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'diagnostic_id', 'vehicle', 'engine', 'peak_g', 'rms_g', 'crest_factor',
                'temperature_c', 'rpm', 'voltage_v', 'current_ma', 'condition', 'confidence'
            ]);

            foreach ($diagnostics as $diag) {
                $crest = ($diag->vibration_rms_g > 0 && $diag->vibration_peak_g > 0)
                    ? round($diag->vibration_peak_g / $diag->vibration_rms_g, 3)
                    : null;

                fputcsv($handle, [
                    $diag->id,
                    $diag->vehicle?->display_name ?? 'N/A',
                    $diag->vehicle?->engine_type ?? 'N/A',
                    $diag->vibration_peak_g,
                    $diag->vibration_rms_g,
                    $crest,
                    $diag->temperature_c,
                    $diag->rpm,
                    $diag->voltage_v,
                    $diag->current_ma,
                    $diag->ml_condition,
                    $diag->ml_confidence,
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
     * Parse a vibration CSV/JSON file and return [sampleCount, peakG, rmsG].
     * Expected CSV columns: timestamp, x, y, z  (or just: timestamp, magnitude)
     */
    private function parseVibrationSummary(string $filePath, string $ext): array
    {
        try {
            if (strtolower($ext) === 'json') {
                $json    = json_decode(file_get_contents($filePath), true) ?? [];
                $samples = is_array($json) ? $json : [];
                $mags    = array_map(fn($s) => (float) ($s['magnitude'] ?? $s['z'] ?? 0), $samples);
            } else {
                // CSV: skip header
                $lines   = array_filter(array_map('trim', file($filePath)));
                array_shift($lines); // header row
                $mags = [];
                foreach ($lines as $line) {
                    $cols = str_getcsv($line);
                    // Use magnitude column if present, else compute from x,y,z
                    if (count($cols) >= 4) {
                        $mags[] = sqrt(($cols[1] ** 2) + ($cols[2] ** 2) + ($cols[3] ** 2));
                    } elseif (count($cols) >= 2) {
                        $mags[] = abs((float) $cols[1]);
                    }
                }
            }

            if (empty($mags)) return [0, null, null];

            $count  = count($mags);
            $peakG  = round(max($mags), 4);
            $rmsG   = round(sqrt(array_sum(array_map(fn($v) => $v ** 2, $mags)) / $count), 4);

            return [$count, $peakG, $rmsG];
        } catch (\Throwable) {
            return [0, null, null];
        }
    }
}
