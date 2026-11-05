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
            $result = $ml->analyze($this->diagnostic);

            $this->diagnostic->update([
                'status'          => 'completed',
                'ml_condition'    => $result['condition']    ?? 'normal',
                'ml_confidence'   => $result['confidence']   ?? null,
                'ml_result_raw'   => $result,
                'ml_processed_at' => now(),
            ]);

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
