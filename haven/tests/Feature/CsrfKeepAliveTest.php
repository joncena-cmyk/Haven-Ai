<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CsrfKeepAliveTest extends TestCase
{
    use RefreshDatabase;

    public function test_keep_alive_requires_authentication(): void
    {
        $response = $this->getJson(route('keep-alive'));
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_ping_keep_alive_to_refresh_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('keep-alive'));

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'csrf_token',
                     'user_id',
                 ])
                 ->assertJson([
                     'status' => 'alive',
                     'user_id' => $user->id,
                 ]);
    }

    public function test_diagnostic_store_supports_json_and_returns_redirect_url(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2023,
            'body_type' => 'sedan',
        ]);

        $audio = UploadedFile::fake()->create('engine.wav', 100, 'audio/wav');

        $response = $this->actingAs($user)->postJson(route('diagnostics.store'), [
            'vehicle_id' => $vehicle->id,
            'audio_file' => $audio,
            'vibration_source' => 'skipped',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'redirect_url',
                     'diagnostic_id',
                 ])
                 ->assertJson([
                     'success' => true,
                 ]);
    }
}
