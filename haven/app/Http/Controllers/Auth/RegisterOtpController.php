<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BrevoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterOtpController extends Controller
{
    /**
     * Display the registration OTP verification view.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $userId = $request->session()->get('auth.registration_user_id');

        if (! $userId) {
            return redirect()->route('register');
        }

        $user = User::find($userId);

        if (! $user) {
            $request->session()->forget('auth.registration_user_id');

            return redirect()->route('register');
        }

        return view('auth.verify-registration-otp', [
            'email' => $user->email,
        ]);
    }

    /**
     * Verify the 6-digit registration OTP code.
     */
    public function store(Request $request, BrevoService $brevoService): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $userId = $request->session()->get('auth.registration_user_id');

        if (! $userId) {
            return redirect()->route('register');
        }

        $user = User::find($userId);

        if (! $user) {
            $request->session()->forget('auth.registration_user_id');

            return redirect()->route('register');
        }

        $attemptKey = 'reg-otp-attempt:'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Too many incorrect attempts. Please wait '.RateLimiter::availableIn($attemptKey).' seconds or request a new code.',
            ]);
        }

        if (! $user->verifyTwoFactorCode($request->code)) {
            RateLimiter::hit($attemptKey, 300);
            throw ValidationException::withMessages([
                'code' => __('The verification code is invalid or has expired.'),
            ]);
        }

        // Code is valid: mark user verified and log them in
        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        $user->resetTwoFactorCode();

        $request->session()->forget('auth.registration_user_id');

        Auth::login($user);

        $request->session()->regenerate();

        AuditLogger::log('auth.registered_verified', [
            'email' => $user->email,
            'user_id' => $user->id,
        ], $user->id);

        return redirect()->intended(route('dashboard', absolute: false))
            ->with('status', 'Your email has been successfully verified! Welcome to Haven AI.');
    }

    /**
     * Resend a fresh 6-digit OTP code to the registering user.
     */
    public function resend(Request $request, BrevoService $brevoService): RedirectResponse
    {
        $userId = $request->session()->get('auth.registration_user_id');

        if (! $userId) {
            return redirect()->route('register');
        }

        $user = User::find($userId);

        if (! $user) {
            $request->session()->forget('auth.registration_user_id');

            return redirect()->route('register');
        }

        $throttleKey = 'resend-reg-otp:'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'code' => "Please wait {$seconds} seconds before requesting another code.",
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        $code = $user->generateTwoFactorCode();
        $brevoService->sendEmailOtp($user->email, $user->name, $code, 'Registration Verification');

        return back()->with('status', 'A new 6-digit verification code has been sent to your email.');
    }
}
