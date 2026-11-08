<?php

namespace Tests\Feature;

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

class GroundTruthLabelingPhase1Test extends TestCase
{
    use RefreshDatabase;

    private function createVehicle(User $user): Vehicle
    {
        return Vehicle::create([
            'user_id' => $user->id,
            'make' => 'Toyota',
            'model' => 'Vios',
            'year' => 2021,
            'body_type' => 'sedan',
            'plate_number' => 'NAA 2021',
            'engine_type' => '1.5L 2NR-FE Gasoline',
        ]);
    }

    /**
     * A diagnostic without true_condition or with is_training_sample=false is excluded from export and retrain;
     * one with both is included and uses true_condition (never ml_condition) as the label.
     */
    public function test_unlabeled_diagnostics_excluded_and_labeled_training_samples_use_true_condition(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);

        // 1. Unlabeled diagnostic (has ml_condition prediction, but no true_condition)
        $unlabeled = Diagnostic::create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'vibration_source' => 'adxl345_live',
            'vibration_peak_g' => 1.25,
            'vibration_rms_g' => 0.31,
            'status' => 'completed',
            'ml_condition' => 'misfire',
            'ml_confidence' => 0.95,
            'true_condition' => null,
            'is_training_sample' => false,
        ]);

        // 2. Labeled diagnostic with is_training_sample = false (should also be excluded)
        $notTrainingSample = Diagnostic::create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'vibration_source' => 'adxl345_live',
            'vibration_peak_g' => 1.40,
            'vibration_rms_g' => 0.35,
            'status' => 'completed',
            'ml_condition' => 'normal',
            'ml_confidence' => 0.88,
            'true_condition' => 'loose_component',
            'is_training_sample' => false,
        ]);

        // 3. Verified training sample where ml_condition prediction ('normal') differs from true_condition ('valve_clearance')
        $verifiedSample = Diagnostic::create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'vibration_source' => 'adxl345_live',
            'vibration_peak_g' => 2.15,
            'vibration_rms_g' => 0.42,
            'status' => 'completed',
            'ml_condition' => 'normal',
            'ml_confidence' => 0.61,
            'true_condition' => 'valve_clearance',
            'session_id' => 'SESS-2026-VALVE-01',
            'fault_detail' => 'Exhaust valve #2 clearance measured at 0.40mm (loose)',
            'target_rpm' => 850,
            'load_state' => 'Idle, AC Off',
            'sensor_position' => 'Cylinder head bolt #2',
            'mic_position' => '15cm above valve cover',
            'operator' => 'Anton',
            'verified_by' => 'Master Tech Rivera',
            'sample_rate_hz' => 1000,
            'is_training_sample' => true,
        ]);

        // User CSV export: includes ONLY $verifiedSample and outputs true_condition ('valve_clearance'), never ml_condition ('normal')
        $userExport = $this->actingAs($user)->get(route('diagnostics.export.dataset'));
        $userExport->assertOk();
        $userCsv = $userExport->streamedContent();

        $this->assertStringNotContainsString("\n{$unlabeled->id},", $userCsv);
        $this->assertStringNotContainsString("\n{$notTrainingSample->id},", $userCsv);
        $this->assertStringContainsString("{$verifiedSample->id},SESS-2026-VALVE-01", $userCsv);
        $this->assertStringContainsString('valve_clearance', $userCsv);
        $this->assertStringNotContainsString(',normal,', $userCsv);

        // Admin CSV export: includes ONLY $verifiedSample and outputs true_condition ('valve_clearance')
        $admin = Admin::factory()->create();
        $adminExport = $this->actingAs($admin, 'admin')->get(route('admin.ml.export'));
        $adminExport->assertOk();
        $adminCsv = $adminExport->streamedContent();

        $this->assertStringNotContainsString("\n{$unlabeled->id},", $adminCsv);
        $this->assertStringNotContainsString("\n{$notTrainingSample->id},", $adminCsv);
        $this->assertStringContainsString("{$verifiedSample->id},SESS-2026-VALVE-01", $adminCsv);
        $this->assertStringContainsString('valve_clearance', $adminCsv);
        $this->assertStringNotContainsString(',normal,', $adminCsv);

        // Admin Retrain: sends ONLY $verifiedSample with condition='valve_clearance' and true_condition='valve_clearance'
        $mlMock = Mockery::mock(MlBackendService::class);
        $mlMock->shouldReceive('triggerRetrain')
            ->once()
            ->with(
                Mockery::on(function (array $records) {
                    return count($records) === 1
                        && $records[0]['true_condition'] === 'valve_clearance'
                        && $records[0]['condition'] === 'valve_clearance';
                }),
                Mockery::any(),
                Mockery::any()
            )
            ->andReturn([
                'status' => 'success',
                'test_accuracy' => 0.94,
                'n_samples' => 1,
            ]);
        $this->app->instance(MlBackendService::class, $mlMock);

        $retrainResp = $this->actingAs($admin, 'admin')->post(route('admin.ml.retrain'));
        $retrainResp->assertRedirect();
        $retrainResp->assertSessionHas('status');
    }

    /**
     * Ownership and authorization are enforced on label updates and dataset export.
     */
    public function test_ownership_and_authorization_enforced_for_labeling_and_export(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $vehicle = $this->createVehicle($owner);

        $diagnostic = Diagnostic::create([
            'user_id' => $owner->id,
            'vehicle_id' => $vehicle->id,
            'vibration_source' => 'adxl345_live',
            'status' => 'completed',
            'ml_condition' => 'normal',
            'ml_confidence' => 0.90,
        ]);

        // Intruder cannot update owner's diagnostic label (403 Forbidden)
        $forbiddenResp = $this->actingAs($intruder)->patch(route('diagnostics.label.update', $diagnostic), [
            'true_condition' => 'misfire',
            'is_training_sample' => 1,
        ]);
        $forbiddenResp->assertForbidden();
        $this->assertNull($diagnostic->fresh()->true_condition);
        $this->assertFalse($diagnostic->fresh()->is_training_sample);

        // Owner can update label and session metadata; ml_condition remains untouched
        $ownerResp = $this->actingAs($owner)->patch(route('diagnostics.label.update', $diagnostic), [
            'true_condition' => 'misfire',
            'session_id' => 'SESS-OWN-01',
            'fault_detail' => 'Cylinder 1 ignition coil fault',
            'target_rpm' => 800,
            'load_state' => 'Idle',
            'sensor_position' => 'Engine block front',
            'mic_position' => 'Intake manifold',
            'operator' => 'Owner Operator',
            'verified_by' => 'Chief Mechanic',
            'sample_rate_hz' => 1000,
            'is_training_sample' => 1,
        ]);
        $ownerResp->assertRedirect(route('diagnostics.show', $diagnostic));

        $fresh = $diagnostic->fresh();
        $this->assertEquals('misfire', $fresh->true_condition);
        $this->assertEquals('normal', $fresh->ml_condition); // Prediction never overwritten by ground truth
        $this->assertTrue($fresh->is_training_sample);
        $this->assertEquals('SESS-OWN-01', $fresh->session_id);
        $this->assertEquals(800, $fresh->target_rpm);
        $this->assertEquals(1000, $fresh->sample_rate_hz);

        // Intruder's dataset export does not include owner's diagnostic
        $intruderExport = $this->actingAs($intruder)->get(route('diagnostics.export.dataset'));
        $intruderExport->assertOk();
        $this->assertStringNotContainsString('SESS-OWN-01', $intruderExport->streamedContent());
    }

    /**
     * Store endpoint saves ground-truth metadata and does not invent RPM when omitted.
     */
    public function test_store_saves_metadata_and_does_not_invent_rpm(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);

        $audioFile = UploadedFile::fake()->create('idle_capture.wav', 64, 'audio/wav');

        $response = $this->actingAs($user)->post(route('diagnostics.store'), [
            'vehicle_id' => $vehicle->id,
            'audio_file' => $audioFile,
            'vibration_source' => 'skipped',
            'recording_mode' => 'revving',
            'true_condition' => 'loose_component',
            'session_id' => 'SESS-STORE-02',
            'target_rpm' => 2200,
            'sensor_position' => 'Timing cover',
            'mic_position' => 'Hood latch',
            'operator' => 'Anton',
            'sample_rate_hz' => 44100,
            'is_training_sample' => 1,
        ]);

        $diag = Diagnostic::where('vehicle_id', $vehicle->id)->firstOrFail();
        $response->assertRedirect(route('diagnostics.show', $diag));

        $this->assertNull($diag->rpm, 'RPM must not be invented when omitted from request');
        $this->assertEquals(2200, $diag->target_rpm);
        $this->assertEquals('loose_component', $diag->true_condition);
        $this->assertEquals('SESS-STORE-02', $diag->session_id);
        $this->assertTrue($diag->is_training_sample);
    }

    /**
     * Phase 2 Artisan importer (`haven:import-recording`) imports a validated recording folder
     * and sets true_condition and is_training_sample=true.
     */
    public function test_artisan_import_recording_command_sets_true_condition_and_training_sample(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);

        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'haven_rec_test_'.uniqid();
        mkdir($tempDir, 0777, true);

        try {
            // 1. Write metadata.json
            file_put_contents($tempDir.DIRECTORY_SEPARATOR.'metadata.json', json_encode([
                'session_id' => 'SESS-IMPORT-01',
                'vehicle_id' => $vehicle->id,
                'condition' => 'misfire',
                'rpm_target' => 850,
                'duration_target_sec' => 1.0,
                'operator' => 'Anton',
                'verified_by' => 'Master Tech Rivera',
                'sensor_position' => 'Cylinder head bolt #1',
                'mic_position' => '15cm above intake',
                'load_state' => 'Idle, AC Off',
                'fault_detail' => 'Injector #1 connector unplugged',
            ], JSON_PRETTY_PRINT));

            // 2. Write vibration.csv (100 samples @ 1000 Hz, dt = 1000 us)
            $csvLines = ['t_us,ax,ay,az'];
            for ($i = 0; $i < 100; $i++) {
                $tUs = 1000000 + ($i * 1000);
                $ax = round(0.25 * sin(2 * M_PI * 25 * ($i / 1000.0)), 4);
                $ay = round(0.10 * cos(2 * M_PI * 25 * ($i / 1000.0)), 4);
                $az = round(1.00 + 0.15 * sin(2 * M_PI * 50 * ($i / 1000.0)), 4);
                $csvLines[] = "{$tUs},{$ax},{$ay},{$az}";
            }
            file_put_contents($tempDir.DIRECTORY_SEPARATOR.'vibration.csv', implode("\n", $csvLines));

            // 3. Write minimal valid WAV file
            file_put_contents($tempDir.DIRECTORY_SEPARATOR.'audio.wav', str_repeat("\0", 1024));

            $this->artisan('haven:import-recording', [
                'folder' => $tempDir,
                '--vehicle-id' => $vehicle->id,
                '--verified-by' => 'Master Tech Rivera',
            ])->assertExitCode(0);

            $diag = Diagnostic::where('session_id', 'SESS-IMPORT-01')->firstOrFail();
            $this->assertEquals('misfire', $diag->true_condition);
            $this->assertTrue($diag->is_training_sample);
            $this->assertNull($diag->ml_condition);
            $this->assertEquals(1000, $diag->sample_rate_hz);
            $this->assertEquals(100, $diag->vibration_sample_count);
            $this->assertEquals(850, $diag->target_rpm);
            $this->assertEquals('Cylinder head bolt #1', $diag->sensor_position);
            $this->assertEquals('Master Tech Rivera', $diag->verified_by);
            Storage::disk('local')->assertExists($diag->audio_path);
            Storage::disk('local')->assertExists($diag->vibration_path);
        } finally {
            @unlink($tempDir.DIRECTORY_SEPARATOR.'metadata.json');
            @unlink($tempDir.DIRECTORY_SEPARATOR.'vibration.csv');
            @unlink($tempDir.DIRECTORY_SEPARATOR.'audio.wav');
            @rmdir($tempDir);
        }
    }

    /**
     * Uploading a Phase 3 ESP32 LOGGER CSV with `# HAVEN_LOGGER_V1` header comments
     * strips comment and header lines, computes DC-removed peak/RMS, and infers sample_rate_hz.
     */
    public function test_esp32_logger_csv_upload_strips_comments_and_infers_sample_rate(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);

        $csvLines = [
            '# HAVEN_LOGGER_V1 sensor=MPU6050(I2C_0x68) range_g=8 target_hz=1000 dlpf_hz=260',
            't_us,ax,ay,az',
        ];
        for ($i = 0; $i < 100; $i++) {
            $tUs = 2000000 + ($i * 1000); // 1000 Hz
            $ax = round(0.40 * sin(2 * M_PI * 30 * ($i / 1000.0)), 4);
            $ay = 0.0;
            $az = 1.00; // Static 1.0g gravity offset
            $csvLines[] = "{$tUs},{$ax},{$ay},{$az}";
        }
        $vibFile = UploadedFile::fake()->createWithContent('logger_capture.csv', implode("\n", $csvLines));

        $response = $this->actingAs($user)->post(route('diagnostics.store'), [
            'vehicle_id' => $vehicle->id,
            'vibration_file' => $vibFile,
            'vibration_source' => 'adxl345_live',
            'recording_mode' => 'idle',
        ]);

        $diag = Diagnostic::where('vehicle_id', $vehicle->id)->latest('id')->firstOrFail();
        $response->assertRedirect(route('diagnostics.show', $diag));

        $this->assertEquals(100, $diag->vibration_sample_count);
        $this->assertEquals(1000, $diag->sample_rate_hz);
        $this->assertEqualsWithDelta(0.40, (float) $diag->vibration_peak_g, 0.02);
        $this->assertEqualsWithDelta(0.40 / sqrt(2), (float) $diag->vibration_rms_g, 0.02);
    }

    /**
     * JSON vibration upload and ESP32 telemetry ingest both remove static 1.0g gravity
     * and persist sample_rate_hz and vibration_source.
     */
    public function test_json_vibration_upload_and_telemetry_ingest_remove_dc_gravity(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);
        $vehicle->update(['device_key' => 'hvn_dev_99887766554433221100aabbccddeeff']);

        // 1. JSON vibration upload with 1.0g static Z gravity + 0.30g dynamic X oscillation
        $jsonSamples = [];
        for ($i = 0; $i < 64; $i++) {
            $jsonSamples[] = [
                'x' => round(0.30 * sin(2 * M_PI * 4 * ($i / 64.0)), 4),
                'y' => 0.0,
                'z' => 1.00,
            ];
        }
        $jsonFile = UploadedFile::fake()->createWithContent('vib_capture.json', json_encode($jsonSamples));

        $this->actingAs($user)->post(route('diagnostics.store'), [
            'vehicle_id' => $vehicle->id,
            'vibration_file' => $jsonFile,
            'vibration_source' => 'csv_import',
            'recording_mode' => 'idle',
        ])->assertRedirect();

        $jsonDiag = Diagnostic::where('vehicle_id', $vehicle->id)->latest('id')->firstOrFail();
        $this->assertEquals(64, $jsonDiag->vibration_sample_count);
        $this->assertEqualsWithDelta(0.30, (float) $jsonDiag->vibration_peak_g, 0.02);
        $this->assertEqualsWithDelta(0.30 / sqrt(2), (float) $jsonDiag->vibration_rms_g, 0.02);

        // 2. Telemetry ingest with stationary sensor (1.0g static gravity -> 0.0g dynamic)
        $stationarySamples = array_fill(0, 32, ['x' => 0.0, 'y' => 0.0, 'z' => 1.0]);
        $ingestResp = $this->postJson('/api/v1/telemetry/ingest', [
            'vehicle_id' => $vehicle->id,
            'samples' => $stationarySamples,
            'sample_rate_hz' => 100,
            'save_session' => true,
            'auto_analyze' => false,
            'health' => ['mpu6050' => true],
        ], ['X-Haven-Device-Key' => $vehicle->device_key]);

        $ingestResp->assertStatus(201);
        $ingestedDiag = Diagnostic::findOrFail($ingestResp->json('diagnostic_id'));
        $this->assertEquals('device_sensor', $ingestedDiag->vibration_source);
        $this->assertEquals(100, $ingestedDiag->sample_rate_hz);
        $this->assertEqualsWithDelta(0.0, (float) $ingestedDiag->vibration_peak_g, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $ingestedDiag->vibration_rms_g, 0.001);
    }
}
