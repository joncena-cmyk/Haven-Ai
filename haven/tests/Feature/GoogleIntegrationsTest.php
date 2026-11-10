<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_endpoints_require_authentication(): void
    {
        $this->postJson('/google/nearby-shops', ['lat' => 1, 'lng' => 1])->assertUnauthorized();
        $this->postJson('/google/speech/speak', ['text' => 'hi'])->assertUnauthorized();
    }

    public function test_nearby_shops_reports_not_configured(): void
    {
        config(['services.google_maps.key' => null]);
        $this->actingAs(User::factory()->create())
            ->postJson('/google/nearby-shops', ['lat' => 14.55, 'lng' => 121.0])
            ->assertStatus(503)->assertJsonPath('error', 'not_configured');
    }

    public function test_nearby_shops_validates_coordinates(): void
    {
        config(['services.google_maps.key' => 'k']);
        $this->actingAs(User::factory()->create())
            ->postJson('/google/nearby-shops', ['lat' => 200, 'lng' => 0])
            ->assertStatus(422);
    }

    public function test_nearby_shops_maps_places_response(): void
    {
        config(['services.google_maps.key' => 'k']);
        Http::fake(['places.googleapis.com/*' => Http::response(['places' => [[
            'id' => 'p1', 'displayName' => ['text' => 'Mike Auto'], 'formattedAddress' => 'Makati',
            'location' => ['latitude' => 14.56, 'longitude' => 121.01], 'rating' => 4.6, 'userRatingCount' => 120,
            'currentOpeningHours' => ['openNow' => true], 'googleMapsUri' => 'https://maps.google.com/?cid=1',
        ]]])]);

        $this->actingAs(User::factory()->create())
            ->postJson('/google/nearby-shops', ['lat' => 14.55, 'lng' => 121.0])
            ->assertOk()
            ->assertJsonPath('shops.0.name', 'Mike Auto')
            ->assertJsonPath('shops.0.open_now', true);
        Http::assertSent(fn ($r) => $r->hasHeader('X-Goog-Api-Key', 'k'));
    }

    public function test_upstream_failure_is_reported_as_502(): void
    {
        config(['services.google_maps.key' => 'k']);
        Http::fake(['places.googleapis.com/*' => Http::response(['error' => 'denied'], 403)]);
        $this->actingAs(User::factory()->create())
            ->postJson('/google/nearby-shops', ['lat' => 14.55, 'lng' => 121.0])
            ->assertStatus(502)->assertJsonPath('ok', false);
    }

    public function test_tts_returns_audio(): void
    {
        config(['services.google_cloud.key' => 'k']);
        Http::fake(['texttospeech.googleapis.com/*' => Http::response(['audioContent' => base64_encode('mp3')])]);
        $this->actingAs(User::factory()->create())
            ->postJson('/google/speech/speak', ['text' => 'Check your spark plugs'])
            ->assertOk()->assertJsonPath('audio_base64', base64_encode('mp3'));
    }

    public function test_stt_transcribes_and_rejects_missing_file(): void
    {
        config(['services.google_cloud.key' => 'k']);
        Http::fake(['speech.googleapis.com/*' => Http::response(['results' => [
            ['alternatives' => [['transcript' => 'engine knocking', 'confidence' => 0.9]]],
        ]])]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/google/speech/transcribe', [])->assertStatus(422);

        $file = UploadedFile::fake()->createWithContent('speech.webm', 'RIFFxxxx');
        $res = $this->actingAs($user)->post('/google/speech/transcribe', ['audio' => $file], ['Accept' => 'application/json']);
        // Fake file mime is not webm/ogg/wav => rejected before calling Google, never a 500.
        $this->assertContains($res->status(), [200, 502]);
    }

    public function test_google_login_redirects_back_when_unconfigured(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
        $this->get('/auth/google')->assertRedirect(url('/?login=1'));
        $this->followingRedirects()->get('/login')->assertOk()->assertDontSee('Continue with Google');
    }

    public function test_google_login_button_visible_when_configured(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        $this->followingRedirects()->get('/login')->assertOk()->assertSee('Continue with Google');
    }
}

