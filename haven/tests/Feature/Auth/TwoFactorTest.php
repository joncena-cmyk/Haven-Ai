<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_2fa_logs_in_directly(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'two_factor_enabled' => false,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_user_with_2fa_is_redirected_to_two_factor_challenge(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'two_factor_enabled' => true,
            'two_factor_channel' => 'email',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Should not be authenticated yet
        $this->assertGuest();
        $response->assertRedirect(route('two-factor.challenge'));
        $this->assertTrue(session()->has('auth.two_factor_user_id'));

        // View renders
        $challengeView = $this->get(route('two-factor.challenge'));
        $challengeView->assertStatus(200);
        $challengeView->assertSee('Two-Factor Authentication');

        // Submit valid OTP
        $code = (string) random_int(100000, 999999);
        $user->forceFill([
            'two_factor_code' => Hash::make($code),
            'two_factor_expires_at' => now()->addMinutes(10),
        ])->save();

        $verifyResponse = $this->post(route('two-factor.challenge.store'), [
            'code' => $code,
        ]);

        $this->assertAuthenticatedAs($user);
        $verifyResponse->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_invalid_two_factor_code_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'two_factor_enabled' => true,
        ]);

        $user->forceFill([
            'two_factor_code' => Hash::make('123456'),
            'two_factor_expires_at' => now()->addMinutes(10),
        ])->save();

        $response = $this->withSession(['auth.two_factor_user_id' => $user->id])
            ->post(route('two-factor.challenge.store'), [
                'code' => '999999',
            ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('code');
    }

    public function test_user_can_toggle_two_factor_in_profile_settings(): void
    {
        $user = User::factory()->create([
            'two_factor_enabled' => false,
            'two_factor_channel' => 'email',
        ]);

        $response = $this->actingAs($user)->put(route('profile.two-factor.update'), [
            'two_factor_enabled' => 1,
            'two_factor_channel' => 'email',
        ]);

        $response->assertSessionHas('status', 'two-factor-updated');
        $user->refresh();
        $this->assertTrue($user->two_factor_enabled);
        $this->assertSame('email', $user->two_factor_channel);
    }

    public function test_user_can_switch_2fa_channel_on_challenge_screen(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'phone' => '+639171234567',
            'two_factor_enabled' => true,
            'two_factor_channel' => 'sms',
        ]);

        $this->withSession([
            'auth.two_factor_user_id' => $user->id,
            'auth.two_factor_active_channel' => 'sms',
        ]);

        $response = $this->post(route('two-factor.challenge.switch'));

        $response->assertSessionHas('status');
        $this->assertSame('email', session('auth.two_factor_active_channel'));
    }

    public function test_firebase_sms_token_must_match_user_phone_number(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'phone' => '+639171234567',
            'two_factor_enabled' => true,
            'two_factor_channel' => 'sms',
        ]);

        Http::fake([
            'identitytoolkit.googleapis.com/*' => Http::sequence()
                ->push(['users' => [['phoneNumber' => '+639998887777']]], 200)
                ->push(['users' => [['phoneNumber' => '+639171234567']]], 200),
        ]);

        // Mismatched phone token is rejected
        $badResponse = $this->withSession(['auth.two_factor_user_id' => $user->id])
            ->post(route('two-factor.challenge.store'), [
                'firebase_token' => 'fake-token-wrong-phone',
            ]);

        $this->assertGuest();
        $badResponse->assertSessionHasErrors('code');

        // Matching phone token succeeds
        $goodResponse = $this->withSession(['auth.two_factor_user_id' => $user->id])
            ->post(route('two-factor.challenge.store'), [
                'firebase_token' => 'fake-token-right-phone',
            ]);

        $this->assertAuthenticatedAs($user);
        $goodResponse->assertRedirect(route('dashboard', absolute: false));
    }
}
