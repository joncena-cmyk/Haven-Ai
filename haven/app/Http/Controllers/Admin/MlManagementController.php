<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Diagnostic;
use App\Services\AuditLogger;
use App\Services\MlBackendService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MlManagementController extends Controller
{
    /**
     * Display ML Model Status, Dataset Class Balance & Queue Health.
     */
    public function index(MlBackendService $mlService): View
    {
        $mlInfo = $mlService->getHealthAndInfo();

        // Database class balance for thesis 4 fault classes (strictly verified ground-truth training samples)
        $classCounts = [
            'normal' => Diagnostic::where('is_training_sample', true)->where('true_condition', 'normal')->count(),
            'misfire' => Diagnostic::where('is_training_sample', true)->where('true_condition', 'misfire')->count(),
            'loose_component' => Diagnostic::where('is_training_sample', true)->where('true_condition', 'loose_component')->count(),
            'valve_clearance' => Diagnostic::where('is_training_sample', true)->where('true_condition', 'valve_clearance')->count(),
        ];
        $totalSessions = Diagnostic::count();
        $labeledCount = array_sum($classCounts);

        // Queue worker status
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        // Local model artifact status
        $modelPath = base_path('../ml_backend/model.pkl');
        $modelExists = file_exists($modelPath);
        $modelMtime = $modelExists ? date('Y-m-d H:i:s T', filemtime($modelPath)) : null;
        $modelSizeKb = $modelExists ? round(filesize($modelPath) / 1024, 1) : 0;

        // Retraining history from audit logs
        $retrainHistory = AuditLog::with('admin')
            ->where('event', 'admin.ml_retrained')
            ->latest('id')
            ->limit(6)
            ->get();

        return view('admin.ml.index', [
            'mlInfo' => $mlInfo,
            'classCounts' => $classCounts,
            'totalSessions' => $totalSessions,
            'labeledCount' => $labeledCount,
            'pendingJobs' => $pendingJobs,
            'failedJobs' => $failedJobs,
            'modelExists' => $modelExists,
            'modelMtime' => $modelMtime,
            'modelSizeKb' => $modelSizeKb,
            'retrainHistory' => $retrainHistory,
        ]);
    }

    /**
     * Trigger One-Click Hybrid Retraining of the Random Forest classifier.
     */
    public function retrain(Request $request, MlBackendService $mlService): RedirectResponse
    {
        $diagnostics = Diagnostic::where('is_training_sample', true)
            ->whereNotNull('true_condition')
            ->whereIn('true_condition', Diagnostic::TRAINING_CONDITIONS)
            ->where(function ($q) {
                $q->whereNull('vibration_source')
                    ->orWhere('vibration_source', '!=', 'simulated');
            })
            ->limit(500)
            ->get();

        $records = [];
        foreach ($diagnostics as $d) {
            $raw = is_array($d->ml_result_raw) ? $d->ml_result_raw : [];
            $audioFeat = is_array($raw['audio_features'] ?? null) ? $raw['audio_features'] : [];
            $vibFeat = is_array($raw['vibration_features'] ?? null) ? $raw['vibration_features'] : [];

            $audioExistsOnDisk = $d->hasAudio() && (
                Storage::disk('local')->exists($d->audio_path) || Storage::disk('public')->exists($d->audio_path)
            );
            $vibExistsOnDisk = $d->hasVibration() && (
                Storage::disk('local')->exists($d->vibration_path) || Storage::disk('public')->exists($d->vibration_path)
            );

            $missingAudioFeats = $audioExistsOnDisk && empty($audioFeat);
            $missingVibFeats = $vibExistsOnDisk && ! isset($vibFeat['dominant_freq_hz']);

            if ($missingAudioFeats || $missingVibFeats) {
                try {
                    // Never allow the simulated offline fallback to overwrite verified ground-truth records.
                    $extracted = $mlService->analyze($d, false);
                    if (is_array($extracted) && ! str_contains((string) ($extracted['mode'] ?? ''), 'mock') && (! empty($extracted['audio_features']) || ! empty($extracted['vibration_features']))) {
                        $raw = $extracted;
                        $audioFeat = is_array($raw['audio_features'] ?? null) ? $raw['audio_features'] : $audioFeat;
                        $vibFeat = is_array($raw['vibration_features'] ?? null) ? $raw['vibration_features'] : $vibFeat;
                        $d->update(['ml_result_raw' => $extracted]);
                    }
                } catch (\Throwable) {
                    // Proceed with available stored metrics if live DSP extraction is unavailable
                }
            }

            $peakG = $vibFeat['peak_g'] ?? $d->vibration_peak_g ?? null;
            $rmsG = $vibFeat['rms_g'] ?? $d->vibration_rms_g ?? null;
            $crest = $vibFeat['crest_factor'] ?? (($rmsG !== null && $rmsG > 0 && $peakG !== null)
                ? round($peakG / $rmsG, 4)
                : null);

            $records[] = array_merge($audioFeat, $vibFeat, [
                'session_id' => $d->session_id ?? ('diag_'.$d->id),
                'vehicle_id' => $d->vehicle_id !== null ? (string) $d->vehicle_id : null,
                'true_condition' => $d->true_condition,
                'condition' => $d->true_condition,
                'peak_g' => $peakG,
                'rms_g' => $rmsG,
                'crest_factor' => $crest,
                'dominant_freq_hz' => $vibFeat['dominant_freq_hz'] ?? null,
                'spectral_entropy' => $vibFeat['spectral_entropy'] ?? null,
            ]);
        }

        $result = $mlService->triggerRetrain($records, true, 120);

        $isSuccess = ($result['status'] ?? '') === 'success' || ! empty($result['success']);

        AuditLogger::log('admin.ml_retrained', [
            'status' => $isSuccess ? 'success' : 'failure',
            'test_accuracy' => $result['test_accuracy'] ?? null,
            'n_samples' => $result['n_samples'] ?? 0,
            'message' => $result['message'] ?? ($result['error'] ?? 'Model Retraining Pipeline'),
        ], null, $isSuccess ? 'success' : 'failure', auth('admin')->id());

        if ($isSuccess) {
            $acc = (isset($result['test_accuracy']) && is_numeric($result['test_accuracy']))
                ? round($result['test_accuracy'] * 100, 1).'%'
                : 'verified';

            return back()->with('status', "Retraining Complete! Random Forest model updated with {$acc} accuracy across 4 fault classes.");
        }

        return back()->with('error', 'Retraining Error: '.($result['error'] ?? 'Unknown pipeline error.'));
    }

    /**
     * Export all labeled vehicle diagnostic sessions as a CSV training dataset.
     */
    public function exportDataset(): StreamedResponse
    {
        $diagnostics = Diagnostic::with('vehicle')
            ->where('is_training_sample', true)
            ->whereNotNull('true_condition')
            ->whereIn('true_condition', Diagnostic::TRAINING_CONDITIONS)
            ->where(function ($q) {
                $q->whereNull('vibration_source')
                    ->orWhere('vibration_source', '!=', 'simulated');
            })
            ->orderBy('id', 'asc')
            ->get();

        $filename = 'haven_admin_ml_dataset_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($diagnostics) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'diagnostic_id', 'session_id', 'vehicle_id', 'vehicle', 'vibration_source', 'sample_rate_hz',
                'peak_g', 'rms_g', 'crest_factor',
                'dominant_freq_hz', 'spectral_entropy',
                'rms_mean', 'zcr_mean', 'spectral_centroid_mean', 'spectral_rolloff_mean',
                'mfcc_1_mean', 'mfcc_2_mean', 'mfcc_3_mean', 'mfcc_4_mean', 'mfcc_5_mean',
                'temperature_c', 'rpm', 'target_rpm',
                'load_state', 'sensor_position', 'mic_position', 'operator', 'verified_by',
                'voltage_v', 'current_ma', 'true_condition', 'fault_detail', 'created_at',
            ]);

            foreach ($diagnostics as $d) {
                $raw = is_array($d->ml_result_raw) ? $d->ml_result_raw : [];
                $audFeat = is_array($raw['audio_features'] ?? null) ? $raw['audio_features'] : [];
                $vibFeat = is_array($raw['vibration_features'] ?? null) ? $raw['vibration_features'] : [];
                $peak = $vibFeat['peak_g'] ?? $d->vibration_peak_g ?? null;
                $rms = $vibFeat['rms_g'] ?? $d->vibration_rms_g ?? null;
                $crest = $vibFeat['crest_factor'] ?? (($rms > 0 && $peak > 0) ? round($peak / $rms, 3) : null);

                fputcsv($out, [
                    $d->id,
                    $d->session_id,
                    $d->vehicle_id,
                    $d->vehicle?->display_name ?? 'Vehicle #'.$d->vehicle_id,
                    $d->vibration_source ?? 'physical',
                    $d->sample_rate_hz,
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
                    $d->temperature_c,
                    $d->rpm,
                    $d->target_rpm,
                    $d->load_state,
                    $d->sensor_position,
                    $d->mic_position,
                    $d->operator,
                    $d->verified_by,
                    $d->voltage_v,
                    $d->current_ma,
                    $d->true_condition,
                    $d->fault_detail,
                    $d->created_at?->toISOString(),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
