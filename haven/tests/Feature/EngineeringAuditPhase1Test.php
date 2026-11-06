<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeDiagnosticJob;
use App\Models\Admin;
use App\Models\Diagnostic;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\MlBackendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class EngineeringAuditPhase1Test extends TestCase
{
    use RefreshDatabase;

    private function createVehicle(User $user): Vehicle
    {
        return Vehicle::create([
            'user_id'      => $user->id,
            'make'         => 'Honda',
            'model'        => 'Civic',
            'year'         => 2023,
            'body_type'    => 'sedan',
            'plate_number' => 'ABC 9999',
            'engine_type'  => '1.5L Turbo',
        ]);
    }

    /**
     * B1: Vibration source stored and rendered with transparency and demo notices.
     */
    public function test_vibration_source_stored_and_rendered_with_transparency(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);

        // 1. Simulated Diagnostic
        $simDiagnostic = Diagnostic::create([
            'user_id'                => $user->id,
            'vehicle_id'             => $vehicle->id,
            'audio_path'             => 'diagnostics/test/audio.webm',
            'vibration_path'         => 'diagnostics/test/sim.csv',
            'vibration_source'       => 'simulated',
            'vibration_sample_count' => 150,
            'vibration_peak_g'       => 1.35,
            'vibration_rms_g'        => 0.22,
            'status'                 => 'completed',
            'ml_condition'           => 'normal',
            'ml_confidence'          => 0.92,
        ]);

        $this->assertEquals('Simulated Waveform (Demo)', $simDiagnostic->vibration_source_label);
        $this->assertEquals('h-badge-amber', $simDiagnostic->vibration_source_badge);

        $response = $this->actingAs($user)->get(route('diagnostics.show', $simDiagnostic));
        $response->assertOk();
        $response->assertSee('Simulated Waveform (Demo)');
        $response->assertSee('Synthesized browser waveform. Excluded from machine learning retraining datasets.');

        // 2. Skipped Diagnostic
        $skipDiagnostic = Diagnostic::create([
            'user_id'          => $user->id,
            'vehicle_id'       => $vehicle->id,
            'audio_path'       => 'diagnostics/test/audio2.webm',
            'vibration_path'   => null,
            'vibration_source' => 'skipped',
            'status'           => 'completed',
            'ml_condition'     => 'misfiring',
            'ml_confidence'    => 0.88,
        ]);

        $this->assertEquals('Skipped (Audio Only)', $skipDiagnostic->vibration_source_label);
        $responseSkip = $this->actingAs($user)->get(route('diagnostics.show', $skipDiagnostic));
        $responseSkip->assertOk();
        $responseSkip->assertSee('Skipped (Audio Only)');
        $responseSkip->assertSee('Vibration capture was skipped (Audio-only session).');
    }

    /**
     * B1: Simulated records are strictly excluded from dataset exports and admin retraining.
     */
    public function test_simulated_vibration_excluded_from_dataset_exports_and_admin_retraining(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);

        // Physical record (device sensor)
        $realDiag = Diagnostic::create([
            'user_id'                => $user->id,
            'vehicle_id'             => $vehicle->id,
            'audio_path'             => 'diagnostics/test/audio_real.webm',
            'vibration_path'         => 'diagnostics/test/real.csv',
            'vibration_source'       => 'device_sensor',
            'vibration_sample_count' => 500,
            'vibration_peak_g'       => 1.45,
            'vibration_rms_g'        => 0.30,
            'status'                 => 'completed',
            'ml_condition'           => 'knocking',
            'ml_confidence'          => 0.94,
            'true_condition'         => 'valve_clearance',
            'is_training_sample'     => true,
        ]);

        // Simulated record
        $simDiag = Diagnostic::create([
            'user_id'                => $user->id,
            'vehicle_id'             => $vehicle->id,
            'audio_path'             => 'diagnostics/test/audio_sim.webm',
            'vibration_path'         => 'diagnostics/test/sim.csv',
            'vibration_source'       => 'simulated',
            'vibration_sample_count' => 150,
            'vibration_peak_g'       => 1.20,
            'vibration_rms_g'        => 0.20,
            'status'                 => 'completed',
            'ml_condition'           => 'normal',
            'ml_confidence'          => 0.91,
            'true_condition'         => 'normal',
            'is_training_sample'     => true,
        ]);

        // 1. User Dataset Export
        $userExport = $this->actingAs($user)->get(route('diagnostics.export.dataset'));
        $userExport->assertOk();
        $userContent = $userExport->streamedContent();
        $this->assertStringContainsString("{$realDiag->id},", $userContent);
        $this->assertStringNotContainsString("{$simDiag->id},", $userContent);
        $this->assertStringContainsString('vibration_source', $userContent);
        $this->assertStringContainsString('device_sensor', $userContent);
        $this->assertStringNotContainsString('simulated', $userContent);

        // 2. Admin Dataset Export
        $admin = Admin::create([
            'name'     => 'Lead Auditor',
            'email'    => 'lead.audit@haven.ph',
            'password' => bcrypt('AuditorPass2026!'),
            'role'     => 'superadmin',
        ]);

        $adminExport = $this->actingAs($admin, 'admin')->get(route('admin.ml.export'));
        $adminExport->assertOk();
        $adminContent = $adminExport->streamedContent();
        $this->assertStringContainsString("{$realDiag->id},", $adminContent);
        $this->assertStringNotContainsString("{$simDiag->id},", $adminContent);
        $this->assertStringContainsString('device_sensor', $adminContent);
        $this->assertStringNotContainsString('simulated', $adminContent);

        // 3. Admin Retrain excludes simulated diagnostics
        $mlMock = Mockery::mock(MlBackendService::class);
        $mlMock->shouldReceive('triggerRetrain')
            ->once()
            ->with(
                Mockery::on(fn($records) => count($records) === 1 && $records[0]['condition'] === 'valve_clearance'),
                Mockery::any(),
                Mockery::any()
            )
            ->andReturn([
                'status'        => 'success',
                'test_accuracy' => 0.985,
                'n_samples'     => 1,
            ]);
        $this->app->instance(MlBackendService::class, $mlMock);

        $retrainResp = $this->actingAs($admin, 'admin')->post(route('admin.ml.retrain'));
        $retrainResp->assertRedirect();
        $retrainResp->assertSessionHas('status');
    }

    /**
     * B1: User can submit diagnostic with skipped vibration (Audio-only flow).
     */
    public function test_skip_vibration_flow_allows_submission_without_vibration_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);

        $audioFile = UploadedFile::fake()->create('engine_sample.wav', 64, 'audio/wav');

        $response = $this->actingAs($user)->post(route('diagnostics.store'), [
            'vehicle_id'       => $vehicle->id,
            'audio_file'       => $audioFile,
            'audio_duration'   => 10,
            'vibration_source' => 'skipped',
            'recording_mode'   => 'idle',
        ]);

        $this->assertDatabaseHas('diagnostics', [
            'vehicle_id'       => $vehicle->id,
            'vibration_source' => 'skipped',
            'vibration_path'   => null,
        ]);

        $diag = Diagnostic::where('vehicle_id', $vehicle->id)->first();
        $this->assertNotNull($diag);
        $this->assertContains($diag->status, ['submitted', 'completed']);
        $response->assertRedirect(route('diagnostics.show', $diag));
    }

    /**
     * B2: Diagnostics show renders direct synchronous analysis button and status polling when queued.
     */
    public function test_queue_resilience_renders_direct_mode_button_and_polling(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);

        $diagnostic = Diagnostic::create([
            'user_id'          => $user->id,
            'vehicle_id'       => $vehicle->id,
            'audio_path'       => 'diagnostics/test/audio.webm',
            'vibration_path'   => null,
            'vibration_source' => 'skipped',
            'status'           => 'submitted',
        ]);

        $response = $this->actingAs($user)->get(route('diagnostics.show', $diagnostic));
        $response->assertOk();
        $response->assertSee('Run Fast Analysis Now (Direct)');
        $response->assertSee(route('diagnostics.status', $diagnostic));

        // Status endpoint returns valid JSON for polling
        $statusResp = $this->actingAs($user)->get(route('diagnostics.status', $diagnostic));
        $statusResp->assertOk();
        $statusResp->assertJson([
            'id'     => $diagnostic->id,
            'status' => 'submitted',
        ]);

        // Direct synchronous analysis execution via sync=1
        $mlMock = Mockery::mock(MlBackendService::class);
        $mlMock->shouldReceive('analyze')
            ->once()
            ->with(Mockery::on(fn($d) => $d->id === $diagnostic->id))
            ->andReturn([
                'condition'      => 'normal',
                'confidence'     => 0.96,
                'confidence_pct' => '96%',
            ]);
        $this->app->instance(MlBackendService::class, $mlMock);

        $analyzeResp = $this->actingAs($user)->post(route('diagnostics.analyze', $diagnostic), [
            'sync' => 1,
        ]);

        $analyzeResp->assertRedirect(route('diagnostics.show', $diagnostic));
        $this->assertDatabaseHas('diagnostics', [
            'id'           => $diagnostic->id,
            'status'       => 'completed',
            'ml_condition' => 'normal',
        ]);
    }

    /**
     * B2: AnalyzeDiagnosticJob permanent failure callback marks diagnostic failed.
     */
    public function test_analyze_job_permanent_failure_marks_diagnostic_failed(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);

        $diagnostic = Diagnostic::create([
            'user_id'          => $user->id,
            'vehicle_id'       => $vehicle->id,
            'audio_path'       => 'diagnostics/test/audio.webm',
            'vibration_path'   => null,
            'vibration_source' => 'skipped',
            'status'           => 'processing',
        ]);

        $job = new AnalyzeDiagnosticJob($diagnostic);
        $job->failed(new \RuntimeException('Fatal pipeline crash'));

        $this->assertEquals('failed', $diagnostic->fresh()->status);
        $this->assertEquals('Fatal pipeline crash', $diagnostic->fresh()->ml_result_raw['error']);
    }
}
