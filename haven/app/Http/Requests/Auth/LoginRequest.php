<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BrevoService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     * Returns true if 2FA verification is required before full authentication.
     *
     * @throws ValidationException
     */
    public function authenticate(): bool
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            $existingUserId = User::where('email', (string) $this->input('email'))->value('id');
            AuditLogger::log('auth.login_failed', [
                'email' => (string) $this->input('email'),
            ], $existingUserId, 'failure');

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->isSuspended()) {
            $suspendedId = Auth::id();
            Auth::logout();

            AuditLogger::log('auth.login_blocked_suspended', [
                'email' => (string) $this->input('email'),
            ], $suspendedId, 'failure');

            throw ValidationException::withMessages([
                'email' => 'This account has been suspended. Contact an administrator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        // Check if user has enabled Two-Factor Authentication
        if ($user->two_factor_enabled) {
            $userId = $user->id;
            $remember = $this->boolean('remember');

            // Log out temporarily until 2FA challenge is solved
            Auth::logout();

            $this->session()->put('auth.two_factor_user_id', $userId);
            $this->session()->put('auth.two_factor_remember', $remember);

            // Generate 6-digit OTP and dispatch via Brevo only when channel is email (SMS uses Firebase client-side)
            if ($user->two_factor_channel !== 'sms' || empty($user->phone)) {
                $code = $user->generateTwoFactorCode();
                $brevo = app(BrevoService::class);
                $brevo->sendEmailOtp($user->email, $user->name, $code, 'Two-Factor Authentication');
            }

            AuditLogger::log('auth.2fa_challenge_prompted', [
                'email' => $user->email,
                'channel' => $user->two_factor_channel,
            ], $user->id);

            return true;
        }

        return false;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
