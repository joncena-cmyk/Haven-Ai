<?php

namespace Tests\Feature;

use App\Models\Diagnostic;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DiagnosticHistoryAndSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnostic_store_completes_synchronously_in_dev_and_populates_results(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2022,
            'body_type' => 'sedan',
        ]);

        $audio = UploadedFile::fake()->create('engine.wav', 100, 'audio/wav');

        $response = $this->actingAs($user)->post(route('diagnostics.store'), [
            'vehicle_id' => $vehicle->id,
            'audio_file' => $audio,
            'vibration_source' => 'skipped',
            'rpm' => 850,
            'temperature_c' => 88.5,
        ]);

        $diagnostic = Diagnostic::latest()->first();
        $this->assertNotNull($diagnostic);
        $this->assertEquals('completed', $diagnostic->status);
        $this->assertNotNull($diagnostic->ml_condition);
        $this->assertNotNull($diagnostic->ml_confidence);
        $this->assertNotEmpty($diagnostic->ml_result_raw);

        $response->assertRedirect(route('diagnostics.show', $diagnostic));
    }

    public function test_diagnostic_history_displays_all_vehicles_by_default(): void
    {
        $user = User::factory()->create();

        $vehicle1 = Vehicle::create([
            'user_id' => $user->id,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2022,
            'body_type' => 'sedan',
        ]);

        $vehicle2 = Vehicle::create([
            'user_id' => $user->id,
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2023,
            'body_type' => 'sedan',
        ]);

        $diag1 = Diagnostic::create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle1->id,
            'status' => 'completed',
            'ml_condition' => 'normal',
        ]);

        $diag2 = Diagnostic::create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle2->id,
            'status' => 'completed',
            'ml_condition' => 'misfire',
        ]);

        // Access /diagnostics with no vehicle_id parameter -> should show both vehicles
        $response = $this->actingAs($user)->get(route('diagnostics.index'));
        $response->assertStatus(200);
        $response->assertSee('Session #'.$diag1->id);
        $response->assertSee('Session #'.$diag2->id);
        $response->assertSee('All Vehicles (Fleet)');

        // Access /diagnostics with specific vehicle_id=vehicle2
        $responseFiltered = $this->actingAs($user)->get(route('diagnostics.index', ['vehicle_id' => $vehicle2->id]));
        $responseFiltered->assertStatus(200);
        $responseFiltered->assertSee('Session #'.$diag2->id);
        $responseFiltered->assertDontSee('Session #'.$diag1->id);
    }

    public function test_diagnostic_show_highlights_component_faults_and_callout_badges(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'make' => 'Mazda',
            'model' => 'Miata',
            'year' => 2021,
            'body_type' => 'coupe',
        ]);

        // 1. Valve Clearance
        $diagValve = Diagnostic::create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'completed',
            'ml_condition' => 'valve_clearance',
        ]);
        $responseValve = $this->actingAs($user)->get(route('diagnostics.show', $diagValve));
        $responseValve->assertStatus(200);
        $responseValve->assertSee('fault-active valve');
        $responseValve->assertSee('MECHANICAL TARGET: VALVETRAIN CAMSHAFT');

        // 2. Misfire
        $diagMisfire = Diagnostic::create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'completed',
            'ml_condition' => 'misfire',
        ]);
        $responseMisfire = $this->actingAs($user)->get(route('diagnostics.show', $diagMisfire));
        $responseMisfire->assertStatus(200);
        $responseMisfire->assertSee('fault-active misfire');
        $responseMisfire->assertSee('COMBUSTION TARGET: CYLINDER #3 CHAMBER');

        // 3. Loose Component
        $diagLoose = Diagnostic::create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'completed',
            'ml_condition' => 'loose_component',
        ]);
        $responseLoose = $this->actingAs($user)->get(route('diagnostics.show', $diagLoose));
        $responseLoose->assertStatus(200);
        $responseLoose->assertSee('fault-active mounts');
        $responseLoose->assertSee('STRUCTURAL TARGET: POWERTRAIN SUBFRAME ENGINE MOUNT');

        // 4. Normal Condition
        $diagNormal = Diagnostic::create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'completed',
            'ml_condition' => 'normal',
        ]);
        $responseNormal = $this->actingAs($user)->get(route('diagnostics.show', $diagNormal));
        $responseNormal->assertStatus(200);
        $responseNormal->assertSee('normal-active');
        $responseNormal->assertSee('ALL POWERTRAIN SUB-ASSEMBLIES IN-SPEC');
    }
}
