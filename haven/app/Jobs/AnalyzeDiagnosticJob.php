<?php

namespace App\Jobs;

use App\Models\Diagnostic;
use App\Services\MlBackendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeDiagnosticJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * Maximum seconds the job may run before timing out.
     */
    public int $timeout = 90;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Diagnostic $diagnostic
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MlBackendService $ml): void
    {
        Log::info("Starting AnalyzeDiagnosticJob for Diagnostic #{$this->diagnostic->id}");

        // Mark as processing
        $this->diagnostic->update(['status' => 'processing']);

        try {
            $isAsyncRetryable = ($this->job !== null)
                && (config('queue.default') !== 'sync')
                && ($this->attempts() < $this->tries);

            $result = $isAsyncRetryable
                ? $ml->analyze($this->diagnostic, false)
                : $ml->analyze($this->diagnostic);

            $vibFeats = is_array($result['vibration_features'] ?? null) ? $result['vibration_features'] : [];
            $updatePayload = [
                'status'          => 'completed',
                'ml_condition'    => $result['condition']    ?? 'normal',
                'ml_confidence'   => $result['confidence']   ?? null,
                'ml_result_raw'   => $result,
                'ml_processed_at' => now(),
            ];
            if (isset($vibFeats['peak_g']) && is_numeric($vibFeats['peak_g']) && (float) $vibFeats['peak_g'] > 0) {
                $updatePayload['vibration_peak_g'] = round((float) $vibFeats['peak_g'], 4);
            }
            if (isset($vibFeats['rms_g']) && is_numeric($vibFeats['rms_g']) && (float) $vibFeats['rms_g'] > 0) {
                $updatePayload['vibration_rms_g'] = round((float) $vibFeats['rms_g'], 4);
            }
            if (empty($this->diagnostic->sample_rate_hz) && !empty($vibFeats['sample_rate_hz']) && (float) $vibFeats['sample_rate_hz'] > 0) {
                $updatePayload['sample_rate_hz'] = (int) round((float) $vibFeats['sample_rate_hz']);
            }

            $this->diagnostic->update($updatePayload);

            Log::info("AnalyzeDiagnosticJob completed for Diagnostic #{$this->diagnostic->id}: Condition={$result['condition']}");

        } catch (\Throwable $e) {
            $attempt = $this->attempts();
            Log::error("AnalyzeDiagnosticJob error for Diagnostic #{$this->diagnostic->id} (Attempt {$attempt} of {$this->tries}): {$e->getMessage()}");

            // Only mark failed if all retry attempts have been exhausted
            if ($attempt >= $this->tries) {
                $this->diagnostic->update([
                    'status'        => 'failed',
                    'ml_result_raw' => [
                        'error'     => $e->getMessage(),
                        'failed_at' => now()->toIso8601String(),
                    ],
                ]);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("AnalyzeDiagnosticJob permanently failed for Diagnostic #{$this->diagnostic->id}: {$exception->getMessage()}");

        $this->diagnostic->update([
            'status'        => 'failed',
            'ml_result_raw' => [
                'error'     => $exception->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
