<?php

namespace Tests\Feature;

use App\Models\Diagnostic;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DiagnosticAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AmbientNoiseFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_without_engine_vibration_or_telemetry_is_classified_as_ambient_noise(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2022,
            'body_type' => 'sedan',
        ]);

        $audio = UploadedFile::fake()->create('crowd_noise.wav', 100, 'audio/wav');

        // Post without vibration or active telemetry (i.e. recorded in open place)
        $response = $this->actingAs($user)->post(route('diagnostics.store'), [
            'vehicle_id' => $vehicle->id,
            'audio_file' => $audio,
            'vibration_source' => 'skipped',
        ]);

        $diagnostic = Diagnostic::latest()->first();
        $this->assertNotNull($diagnostic);
        $this->assertEquals('ambient_noise', $diagnostic->ml_condition);
        $this->assertEquals(0.95, $diagnostic->ml_confidence);
        $this->assertStringContainsString('Ambient', $diagnostic->condition_label);

        // Verify AI Copilot recommendations generate ambient noise guidance
        $aiService = app(DiagnosticAiService::class);
        $recs = $aiService->generateRecommendations($diagnostic);
        $this->assertEquals('INVALID CAPTURE · ENGINE NOT DETECTED', $recs['safety_status']);
        $this->assertStringContainsString('ambient', strtolower($recs['summary']));

        // Verify show page renders ambient noise banner
        $showResponse = $this->actingAs($user)->get(route('diagnostics.show', $diagnostic));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('AMBIENT ENVIRONMENTAL NOISE DETECTED');
        $showResponse->assertSee('Re-Capture at Engine Bay');
    }

    public function test_recording_with_active_engine_vibration_is_evaluated_as_powertrain(): void
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
        $csvContent = "timestamp,magnitude\n0.01,0.12\n0.02,0.15\n0.03,0.14\n0.04,0.13\n";
        $vibration = UploadedFile::fake()->createWithContent('vib.csv', $csvContent);

        $response = $this->actingAs($user)->post(route('diagnostics.store'), [
            'vehicle_id' => $vehicle->id,
            'audio_file' => $audio,
            'vibration_file' => $vibration,
            'vibration_source' => 'device_sensor',
            'rpm' => 850,
            'temperature_c' => 88.0,
        ]);

        $diagnostic = Diagnostic::latest()->first();
        $this->assertNotNull($diagnostic);
        // Engine presence verified -> should be classified into one of the 4 engine mechanical states (not ambient noise)
        $this->assertNotEquals('ambient_noise', $diagnostic->ml_condition);
        $this->assertEquals('normal', $diagnostic->ml_condition);
    }
}
