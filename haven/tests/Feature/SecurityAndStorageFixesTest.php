<?php

namespace Tests\Feature;

use App\Models\Diagnostic;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GlobalAiAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAndStorageFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_telemetry_sketch_and_latest_require_auth_and_scope_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $vehicle = Vehicle::create([
            'user_id'    => $owner->id,
            'make'       => 'Toyota',
            'model'      => 'Corolla',
            'year'       => 2020,
            'device_key' => 'hvn_dev_aabbccddeeff00112233445566778899',
        ]);

        // Unauthenticated requests must be rejected
        $this->getJson('/api/v1/telemetry/sketch')->assertStatus(401);
        $this->getJson('/api/v1/telemetry/latest')->assertStatus(401);

        // Other user cannot access owner's sketch or telemetry
        $this->actingAs($other)
            ->getJson('/api/v1/telemetry/sketch?vehicle_id=' . $vehicle->id)
            ->assertStatus(403);

        $this->actingAs($other)
            ->getJson('/api/v1/telemetry/latest?vehicle_id=' . $vehicle->id)
            ->assertStatus(403);

        // Owner can access their own sketch
        $this->actingAs($owner)
            ->getJson('/api/v1/telemetry/sketch?vehicle_id=' . $vehicle->id)
            ->assertOk()
            ->assertJsonPath('vehicle_id', $vehicle->id);
    }

    public function test_telemetry_ingest_rejects_fallback_key_and_enforces_size_limits(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Cache::flush();

        $owner = User::factory()->create();
        $other = User::factory()->create();

        $vehicle = Vehicle::create([
            'user_id'    => $owner->id,
            'make'       => 'Honda',
            'model'      => 'Civic',
            'year'       => 2021,
            'device_key' => 'hvn_dev_11223344556677889900aabbccddeeff',
        ]);

        // Hardcoded fallback key hvn_dev_test must fail
        $this->postJson('/api/v1/telemetry/ingest', [
            'vehicle_id' => $vehicle->id,
            'rpm'        => 850,
        ], ['X-Haven-Device-Key' => 'hvn_dev_test'])->assertStatus(401);

        // Oversized csv_data must fail validation
        $this->postJson('/api/v1/telemetry/ingest', [
            'vehicle_id' => $vehicle->id,
            'csv_data'   => str_repeat('a', 600000),
        ], ['X-Haven-Device-Key' => $vehicle->device_key])->assertStatus(422);

        // Valid device key succeeds and does not leak to other users or global cache
        $this->postJson('/api/v1/telemetry/ingest', [
            'vehicle_id'   => $vehicle->id,
            'csv_data'     => "t,x,y,z\n0,0.1,0.2,0.9\n1,0.1,0.2,1.0",
            'save_session' => true,
            'auto_analyze' => false,
        ], ['X-Haven-Device-Key' => $vehicle->device_key])->assertStatus(201);

        $this->assertNull(Cache::get('telemetry:live:latest'));
        $this->assertNotNull(Cache::get('telemetry:live:' . $vehicle->id));

        // Other user polling /latest does not see owner's live telemetry
        $this->actingAs($other)
            ->getJson('/api/v1/telemetry/latest')
            ->assertOk()
            ->assertJsonPath('online', false);

        // Ingested file is stored on private ('local') disk, not 'public'
        $diag = Diagnostic::firstOrFail();
        Storage::disk('local')->assertExists($diag->vibration_path);
        Storage::disk('public')->assertMissing($diag->vibration_path);
    }

    public function test_ai_ask_requires_auth_and_strips_device_keys_and_pii(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::create([
            'user_id'    => $user->id,
            'make'       => 'Mazda',
            'model'      => '3',
            'year'       => 2022,
            'device_key' => 'hvn_dev_deadbeefcafebabe1234567890abcdef',
        ]);

        // Unauthenticated request to /ai/ask is blocked
        $this->postJson('/ai/ask', ['question' => 'show my garage'])->assertStatus(401);

        // Test offline expert engine response does not expose device_key
        config([
            'services.groq.key'   => null,
            'services.gemini.key' => null,
            'services.openai.key' => null,
        ]);

        $res = $this->actingAs($user)
            ->postJson('/ai/ask', ['question' => 'show my garage'])
            ->assertOk();

        $answer = $res->json('answer');
        $this->assertStringContainsString('2022 Mazda 3', $answer);
        $this->assertStringNotContainsString($vehicle->device_key, $answer);

        // Prompt sanitizer strips device keys, emails, and VINs
        $sanitized = GlobalAiAssistantService::sanitizePromptText(
            'Key hvn_dev_deadbeefcafebabe1234567890abcdef email user@example.com VIN 1HGCM82633A004352'
        );
        $this->assertStringNotContainsString('hvn_dev_deadbeef', $sanitized);
        $this->assertStringNotContainsString('user@example.com', $sanitized);
        $this->assertStringNotContainsString('1HGCM82633A004352', $sanitized);
    }

    public function test_uploaded_audio_and_vibration_are_private_and_owner_checked(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $vehicle = Vehicle::create([
            'user_id' => $owner->id,
            'make'    => 'Subaru',
            'model'   => 'Impreza',
            'year'    => 2019,
        ]);

        $audio = UploadedFile::fake()->create('engine.wav', 64, 'audio/wav');
        $vib   = UploadedFile::fake()->createWithContent('vib.csv', "t,x,y,z\n0,0.1,0.1,1.0\n");

        $this->actingAs($owner)->post('/diagnostics', [
            'vehicle_id'     => $vehicle->id,
            'audio_file'     => $audio,
            'vibration_file' => $vib,
        ])->assertRedirect();

        $diagnostic = Diagnostic::firstOrFail();

        Storage::disk('local')->assertExists($diagnostic->audio_path);
        Storage::disk('local')->assertExists($diagnostic->vibration_path);
        Storage::disk('public')->assertMissing($diagnostic->audio_path);
        Storage::disk('public')->assertMissing($diagnostic->vibration_path);

        // Owner can stream both files
        $this->actingAs($owner)
            ->get(route('diagnostics.audio', $diagnostic))
            ->assertOk();
        $this->actingAs($owner)
            ->get(route('diagnostics.vibration', $diagnostic))
            ->assertOk();

        // Another user receives 403 Forbidden
        $this->actingAs($intruder)
            ->get(route('diagnostics.audio', $diagnostic))
            ->assertStatus(403);
        $this->actingAs($intruder)
            ->get(route('diagnostics.vibration', $diagnostic))
            ->assertStatus(403);
    }
}

