<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BrevoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    /**
     * Show the 2FA challenge screen.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $userId = $request->session()->get('auth.two_factor_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user) {
            $request->session()->forget(['auth.two_factor_user_id', 'auth.two_factor_remember', 'auth.two_factor_active_channel']);
            return redirect()->route('login');
        }

        $activeChannel = $request->session()->get('auth.two_factor_active_channel', $user->two_factor_channel ?? 'email');

        // Mask recipient destination for security display
        $target = $activeChannel === 'sms' && ! empty($user->phone)
            ? substr($user->phone, 0, 4) . '****' . substr($user->phone, -3)
            : substr($user->email, 0, 2) . '****@' . (explode('@', $user->email)[1] ?? '***');

        $firebaseConfig = [
            'apiKey' => config('services.firebase.api_key'),
            'authDomain' => config('services.firebase.auth_domain'),
            'projectId' => config('services.firebase.project_id'),
            'appId' => config('services.firebase.app_id'),
        ];

        return view('auth.two-factor-challenge', [
            'channel' => $activeChannel,
            'target' => $target,
            'userPhone' => $user->phone,
            'hasPhone' => ! empty($user->phone),
            'firebaseConfig' => $firebaseConfig,
        ]);
    }

    /**
     * Verify the 2FA code (or Firebase ID token) and authenticate user.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $userId = $request->session()->get('auth.two_factor_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user) {
            $request->session()->forget(['auth.two_factor_user_id', 'auth.two_factor_remember', 'auth.two_factor_active_channel']);
            return redirect()->route('login');
        }

        $throttleKey = '2fa-attempt:' . $user->id . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'code' => "Too many incorrect attempts. Please wait {$seconds} seconds before trying again.",
            ]);
        }

        $verified = false;

        // Path A: Firebase Phone Auth Token verification
        if ($request->filled('firebase_token')) {
            $apiKey = config('services.firebase.api_key');
            try {
                $response = Http::post("https://identitytoolkit.googleapis.com/v1/accounts:lookup?key={$apiKey}", [
                    'idToken' => $request->firebase_token,
                ]);

                if ($response->successful()) {
                    $phoneFromToken = $response->json('users.0.phoneNumber');
                    // The verified phone MUST belong to this account; otherwise any Firebase token
                    // (e.g. from an attacker's own phone) would satisfy the second factor.
                    $normalize = fn ($p) => preg_replace('/\D+/', '', (string) $p);
                    if (! empty($phoneFromToken) && ! empty($user->phone)
                        && $normalize($phoneFromToken) === $normalize($user->phone)) {
                        $verified = true;
                    }
                }
            } catch (\Throwable $e) {
                // Network or API failure fallback
            }
        }

        // Path B: Server-side 6-digit OTP verification (Brevo Email OTP or fallback)
        if (! $verified && $request->filled('code')) {
            $verified = $user->verifyTwoFactorCode((string) $request->code);
        }

        if (! $verified) {
            RateLimiter::hit($throttleKey, 300);

            AuditLogger::log('auth.2fa_failed', [
                'user_id' => $user->id,
            ], $user->id, 'failure');

            throw ValidationException::withMessages([
                'code' => __('The verification code is invalid or has expired.'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        $user->resetTwoFactorCode();

        $remember = (bool) $request->session()->get('auth.two_factor_remember', false);
        $request->session()->forget(['auth.two_factor_user_id', 'auth.two_factor_remember', 'auth.two_factor_active_channel']);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        AuditLogger::log('auth.2fa_success', [
            'user_id' => $user->id,
            'email' => $user->email,
        ], $user->id);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => session()->pull('url.intended', route('dashboard', absolute: false)),
            ]);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Resend a fresh 2FA code via the active channel.
     */
    public function resend(Request $request, BrevoService $brevoService): RedirectResponse
    {
        $userId = $request->session()->get('auth.two_factor_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user) {
            $request->session()->forget(['auth.two_factor_user_id', 'auth.two_factor_remember', 'auth.two_factor_active_channel']);
            return redirect()->route('login');
        }

        $throttleKey = 'resend-2fa:' . $user->id . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'code' => "Please wait {$seconds} seconds before requesting a new code.",
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        $code = $user->generateTwoFactorCode();
        $brevoService->sendEmailOtp($user->email, $user->name, $code, 'Two-Factor Authentication');

        return back()->with('status', 'A fresh 6-digit code has been dispatched to your email.');
    }

    /**
     * Switch between Email and SMS verification modes.
     */
    public function switchChannel(Request $request, BrevoService $brevoService): RedirectResponse
    {
        $userId = $request->session()->get('auth.two_factor_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user) {
            return redirect()->route('login');
        }

        $current = $request->session()->get('auth.two_factor_active_channel', $user->two_factor_channel ?? 'email');
        $newChannel = $current === 'email' ? 'sms' : 'email';

        if ($newChannel === 'sms' && empty($user->phone)) {
            return back()->withErrors(['code' => 'No mobile phone is registered for your account. Please use Email verification.']);
        }

        $request->session()->put('auth.two_factor_active_channel', $newChannel);

        if ($newChannel === 'email') {
            $code = $user->generateTwoFactorCode();
            $brevoService->sendEmailOtp($user->email, $user->name, $code, 'Two-Factor Authentication');
            return back()->with('status', 'Switched to Email verification. A 6-digit code has been sent to your email.');
        }

        return back()->with('status', 'Switched to SMS Phone verification.');
    }
}
