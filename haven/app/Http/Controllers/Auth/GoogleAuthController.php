<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BrevoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public static function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    protected function resolveRedirectUrl(Request $request): string
    {
        $host = $request->getHost();
        if (str_contains($host, 'ngrok')) {
            return 'https://'.$host.'/auth/google/callback';
        }

        return config('services.google.redirect', 'http://localhost:8000/auth/google/callback');
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (! self::isConfigured()) {
            return redirect()->to(url('/?login=1'))->withErrors([
                'email' => 'Google Sign-In is not configured yet. Ask an administrator to set GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET.',
            ]);
        }

        $redirectUrl = $this->resolveRedirectUrl($request);

        return Socialite::driver('google')
            ->redirectUrl($redirectUrl)
            ->scopes(['openid', 'profile', 'email'])
            ->stateless()
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! self::isConfigured()) {
            return redirect()->to(url('/?login=1'));
        }

        if ($request->filled('error')) {
            return redirect()->to(url('/?login=1'))->withErrors(['email' => 'Google sign-in was cancelled.']);
        }

        try {
            $redirectUrl = $this->resolveRedirectUrl($request);
            $g = Socialite::driver('google')
                ->redirectUrl($redirectUrl)
                ->stateless()
                ->user();
        } catch (\Throwable $e) {
            Log::warning('Google OAuth callback failed: '.get_class($e).' - '.$e->getMessage());

            return redirect()->to(url('/?login=1'))->withErrors(['email' => 'Google sign-in failed. Please try again.']);
        }

        $email = strtolower((string) $g->getEmail());
        $verified = (bool) ($g->user['email_verified'] ?? $g->user['verified_email'] ?? false);
        if ($email === '' || ! $verified) {
            return redirect()->to(url('/?login=1'))->withErrors(['email' => 'Your Google account email is missing or not verified.']);
        }

        $user = User::where('google_id', $g->getId())->first()
            ?? User::where('email', $email)->first();

        if ($user) {
            if ($user->isSuspended()) {
                AuditLogger::log('auth.login_blocked_suspended', ['email' => $email, 'provider' => 'google'], $user->id, 'failure');

                return redirect()->to(url('/?login=1'))->withErrors(['email' => 'This account has been suspended. Contact an administrator.']);
            }
            // Link Google identity (email is Google-verified).
            $user->forceFill([
                'google_id' => $user->google_id ?: $g->getId(),
                'avatar_url' => $g->getAvatar() ?: $user->avatar_url,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            // Random unusable password; user can set one later via "forgot password".
            $user = new User([
                'name' => $g->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::random(40),
            ]);
            $user->forceFill([
                'google_id' => $g->getId(),
                'avatar_url' => $g->getAvatar(),
                'email_verified_at' => now(),
            ])->save();
            AuditLogger::log('auth.register_google', ['email' => $email], $user->id);
        }

        if ($user->two_factor_enabled) {
            $request->session()->put('auth.two_factor_user_id', $user->id);
            $request->session()->put('auth.two_factor_remember', true);
            $request->session()->put('auth.two_factor_active_channel', $user->two_factor_channel ?? 'email');

            // Email OTP uses Brevo; SMS OTP is dispatched client-side by Firebase on the challenge screen
            if ($user->two_factor_channel !== 'sms' || empty($user->phone)) {
                $code = $user->generateTwoFactorCode();
                $brevo = app(BrevoService::class);
                $brevo->sendEmailOtp($user->email, $user->name, $code, 'Two-Factor Authentication');
            }

            AuditLogger::log('auth.2fa_challenge_prompted', [
                'email' => $user->email,
                'channel' => $user->two_factor_channel,
                'provider' => 'google',
            ], $user->id);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        AuditLogger::log('auth.login', ['email' => $email, 'provider' => 'google'], $user->id);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}

