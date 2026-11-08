<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Password Strength Guide');
        $response->assertSee('8+ characters');
        $response->assertSee('Upper &amp; lower case', false);
        $response->assertSee('At least 1 number');
        $response->assertSee('Special symbol (!@#$)');
        $response->assertSee('Enter Password');
    }

    public function test_new_users_can_register_and_verify_with_otp(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('register.verify-otp'));

        $user = \App\Models\User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);

        // Fetch OTP verification view
        $verifyView = $this->withSession(['auth.registration_user_id' => $user->id])
            ->get(route('register.verify-otp'));
        $verifyView->assertStatus(200);
        $verifyView->assertSee('Verify your email');

        // Submit matching code
        $code = (string) random_int(100000, 999999);
        $user->forceFill([
            'two_factor_code' => \Illuminate\Support\Facades\Hash::make($code),
            'two_factor_expires_at' => now()->addMinutes(10),
        ])->save();

        $submitResponse = $this->withSession(['auth.registration_user_id' => $user->id])
            ->post(route('register.verify-otp.store'), [
                'code' => $code,
            ]);

        $this->assertAuthenticatedAs($user);
        $submitResponse->assertRedirect(route('dashboard', absolute: false));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_registration_otp_locks_out_after_five_wrong_attempts(): void
    {
        $user = \App\Models\User::factory()->unverified()->create([
            'two_factor_code' => \Illuminate\Support\Facades\Hash::make('123456'),
            'two_factor_expires_at' => now()->addMinutes(10),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->withSession(['auth.registration_user_id' => $user->id])
                ->post(route('register.verify-otp.store'), ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        // 6th attempt even with valid code is blocked by rate limiter
        $response = $this->withSession(['auth.registration_user_id' => $user->id])
            ->post(route('register.verify-otp.store'), ['code' => '123456']);

        $this->assertGuest();
        $response->assertSessionHasErrors('code');
    }
}
