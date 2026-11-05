<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Diagnostic;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\MlBackendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CapstoneImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private function createVehicle(User $user): Vehicle
    {
        return Vehicle::create([
            'user_id'      => $user->id,
            'make'         => 'Toyota',
            'model'        => 'Corolla',
            'year'         => 2024,
            'body_type'    => 'sedan',
            'plate_number' => 'ABC 1234',
            'engine_type'  => '2.0L Dynamic Force',
            'device_key'   => 'hvn_dev_testkey123456789',
        ]);
    }

    /**
     * Pillar 1 & 3: Diagnostic show page displays Explainable AI & Baseline Comparison.
     */
    public function test_diagnostic_show_displays_explainable_ai_and_baseline_comparison(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);

        // Baseline diagnostic
        $baseline = Diagnostic::create([
            'vehicle_id'       => $vehicle->id,
            'user_id'          => $user->id,
            'ml_condition'     => 'normal',
            'ml_confidence'    => 0.95,
            'vibration_peak_g' => 1.2,
            'vibration_rms_g'  => 0.25,
            'temperature_c'    => 82.0,
            'rpm'              => 800,
            'voltage_v'        => 13.9,
            'status'           => 'completed',
        ]);

        // Current diagnostic with abnormal valvetrain lash
        $current = Diagnostic::create([
            'vehicle_id'       => $vehicle->id,
            'user_id'          => $user->id,
            'ml_condition'     => 'valve_clearance',
            'ml_confidence'    => 0.89,
            'vibration_peak_g' => 3.8,
            'vibration_rms_g'  => 0.55,
            'temperature_c'    => 90.0,
            'rpm'              => 800,
            'voltage_v'        => 13.7,
            'ml_result_raw'    => [
                'feature_contributions' => [
                    [
                        'feature'     => 'crest_factor',
                        'label'       => 'Crest Factor (Impulsiveness)',
                        'value'       => 4.5,
                        'unit'        => 'ratio',
                        'impact_pct'  => 44.2,
                        'explanation' => 'Extreme impulse-to-steady vibration ratio.',
                    ],
                ],
            ],
            'status'           => 'completed',
        ]);

        $response = $this->actingAs($user)->get(route('diagnostics.show', $current));

        $response->assertOk();
        $response->assertSee('Explainable AI (XAI)', false);
        $response->assertSee('Baseline vs. Current', false);
        $response->assertSee('vs base', false);
    }

    /**
     * Pillar 3: Vehicle details displays 10-session telemetry trend chart.
     */
    public function test_vehicle_show_displays_trend_chart(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);

        for ($i = 0; $i < 3; $i++) {
            Diagnostic::create([
                'vehicle_id'    => $vehicle->id,
                'user_id'       => $user->id,
                'ml_condition'  => 'normal',
                'confidence'    => 0.90,
                'peak_g'        => 1.2 + ($i * 0.1),
                'temperature_c' => 80.0 + $i,
                'rpm'           => 750 + ($i * 10),
                'status'        => 'completed',
            ]);
        }

        $response = $this->actingAs($user)->get(route('vehicles.show', $vehicle));

        $response->assertOk();
        $response->assertSee('Health &amp; Telemetry Trend', false);
        $response->assertSee('Vibration Peak (g)', false);
    }

    /**
     * Pillar 2: Telemetry SSE stream endpoint works.
     */
    public function test_telemetry_stream_endpoint(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);

        $response = $this->actingAs($user)->get('/api/v1/telemetry/stream?vehicle_id=' . $vehicle->id);

        $response->assertOk();
        $this->assertTrue(str_contains($response->headers->get('Content-Type'), 'text/event-stream'));
    }

    /**
     * Pillar 3: Learning progress quiz submit persists to database.
     */
    public function test_quiz_submit_persists_to_learning_progress(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('learning.quiz.submit'), [
            'score' => 9,
            'total' => 10,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success'    => true,
            'score'      => 9,
            'total'      => 10,
            'percentage' => 90,
            'best_score' => 9,
            'tier'       => 'Senior Diagnostic Specialist',
        ]);

        $this->assertDatabaseHas('learning_progress', [
            'user_id'         => $user->id,
            'quiz_best_score' => 9,
            'quiz_attempts'   => 1,
        ]);

        // Retake quiz with lower score - best score remains 9, attempts increments to 2
        $response2 = $this->actingAs($user)->postJson(route('learning.quiz.submit'), [
            'score' => 7,
            'total' => 10,
        ]);

        $response2->assertOk();
        $this->assertDatabaseHas('learning_progress', [
            'user_id'         => $user->id,
            'quiz_best_score' => 9,
            'quiz_attempts'   => 2,
        ]);

        // Viewing a learning module auto-records completion
        $modResponse = $this->actingAs($user)->get(route('learning.show', 'valve-clearance'));
        $modResponse->assertOk();
        $stats = $user->fresh()->getLearningStats();
        $this->assertContains('valve-clearance', $stats['completed_modules']);
        $this->assertGreaterThanOrEqual(1, $stats['completed_count']);
    }

    /**
     * Pillar 4: Admin ML index, dataset CSV export, and retrain routes.
     */
    public function test_admin_can_access_ml_management_and_trigger_retrain(): void
    {
        $admin = Admin::factory()->create();

        $mockMl = Mockery::mock(MlBackendService::class);
        $mockMl->shouldReceive('getHealthAndInfo')->andReturn([
            'online'       => true,
            'url'          => 'http://127.0.0.1:8001',
            'mode'         => 'random_forest_fft',
            'model_loaded' => true,
        ]);
        $mockMl->shouldReceive('triggerRetrain')->andReturn([
            'status'        => 'success',
            'test_accuracy' => 0.988,
            'n_samples'     => 400,
            'message'       => 'Random Forest model retrained successfully',
        ]);
        $this->app->instance(MlBackendService::class, $mockMl);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.ml.index'));

        $response->assertOk();
        $response->assertSee('Machine Learning &amp; Signal Processing Engine', false);
        $response->assertSee('Confusion Matrix (4&times;4)', false);
        $response->assertSee('Per-Class Precision, Recall &amp; F1 Report', false);
        $response->assertSee('Global Random Forest Feature Weights', false);

        $exportResponse = $this->actingAs($admin, 'admin')->get(route('admin.ml.export'));
        $exportResponse->assertOk();
        $this->assertTrue(str_contains($exportResponse->headers->get('Content-Type'), 'text/csv'));

        $retrainResponse = $this->actingAs($admin, 'admin')->post(route('admin.ml.retrain'));
        $retrainResponse->assertSessionHas('status');

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $admin->id,
            'event'    => 'admin.ml_retrained',
        ]);
    }

    /**
     * Pillar 4: Admin Security index, password change and provisioning.
     */
    public function test_admin_security_and_password_rotation(): void
    {
        $admin = Admin::factory()->create([
            'password' => bcrypt('AdminSecret123!'),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.security.index'));
        $response->assertOk();
        $response->assertSee('Active Administrators Roster', false);

        // Rotate password
        $pwdResponse = $this->actingAs($admin, 'admin')->post(route('admin.security.password'), [
            'current_password'      => 'AdminSecret123!',
            'password'              => 'NewStrongPassword456!',
            'password_confirmation' => 'NewStrongPassword456!',
        ]);

        $pwdResponse->assertSessionHas('status');

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $admin->id,
            'event'    => 'admin.password_changed',
        ]);

        // Provision new admin
        $storeResponse = $this->actingAs($admin, 'admin')->post(route('admin.security.store'), [
            'name'                  => 'Second Administrator',
            'email'                 => 'second.admin@haven.ai',
            'password'              => 'AnotherSecret789!',
            'password_confirmation' => 'AnotherSecret789!',
        ]);

        $storeResponse->assertSessionHas('status');

        $this->assertDatabaseHas('admins', [
            'email' => 'second.admin@haven.ai',
        ]);
    }

    /**
     * Verify integrated fix/diagnostics-flow UI & local auth modal coexist cleanly.
     */
    public function test_diagnostics_flow_and_ui_improvements(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::create([
            'user_id'      => $user->id,
            'make'         => 'Toyota',
            'model'        => 'Corolla',
            'year'         => 2022,
            'body_type'    => 'sedan',
            'engine_type'  => '1.8L 4-Cylinder Gasoline',
            'transmission' => 'CVT',
            'plate_number' => 'ABC-1234',
        ]);

        // 1. Diagnostics Create page (Groupmate's Diagnose UI + 3D/2D + 5-step flow with Vibration)
        $createResponse = $this->actingAs($user)->get(route('diagnostics.create', ['vehicle_id' => $vehicle->id]));
        $createResponse->assertOk();
        $createResponse->assertSee('id="vehicle-pill"', false);
        $createResponse->assertSee('id="vehicle-menu"', false);
        $createResponse->assertSee('class="h-diagnosis-flow"', false);
        $createResponse->assertSee('data-flow-step="capture"', false);
        $createResponse->assertSee('data-flow-step="vibration"', false);
        $createResponse->assertSee('id="btn-start-vib"', false);
        $createResponse->assertSee('id="btn-stop-vib"', false);
        $createResponse->assertSee('id="vibration-strip"', false);
        $createResponse->assertSee('id="canvas-channels-legend"', false);
        $createResponse->assertSee('id="view-mode-3d"', false);
        $createResponse->assertSee('id="view-mode-2d"', false);
        $createResponse->assertSee('id="diagnostic-sketchfab-stage"', false);
        $createResponse->assertSee('<span>Diagnose</span>', false);
        $createResponse->assertSee('<span>Diagnostic History</span>', false);

        // 2. Diagnostics Index page (Groupmate's History search & dropdown)
        $indexResponse = $this->actingAs($user)->get(route('diagnostics.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('id="vehicle-pill"', false);
        $indexResponse->assertSee('focusHistorySearch()', false);

        // 3. Guest Homepage (Local auth modal popup + Google button)
        $homeResponse = $this->post('/logout');
        $guestHome = $this->get('/');
        $guestHome->assertOk();
        $guestHome->assertSee('id="haven-auth-modal"', false);
        $guestHome->assertSee('Continue with Google', false);
    }
}
