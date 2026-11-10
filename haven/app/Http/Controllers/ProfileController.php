<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use App\Services\BrevoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $vehicles = $request->user()->vehicles()->get();
        foreach ($vehicles as $veh) {
            if (empty($veh->device_key)) {
                $veh->update(['device_key' => Vehicle::generateDeviceKey()]);
            }
        }

        return view('profile.edit', [
            'user' => $request->user(),
            'auditLogs' => $request->user()->auditLogs()->latest()->take(10)->get(),
            'vehicles' => $vehicles,
            'latestTelemetry' => Cache::get('telemetry:live:user:'.$request->user()->id),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        AuditLogger::log('profile.updated', [
            'email' => $request->user()->email,
        ]);

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Normalize a user-supplied phone number into E.164 format (+639XXXXXXXXX, +1..., etc.).
     */
    public static function normalizePhone(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $cleaned = preg_replace('/[\s\-\(\)\.]+/', '', trim($raw));
        if ($cleaned === '' || $cleaned === '+') {
            return null;
        }

        if (str_starts_with($cleaned, '00') && strlen($cleaned) > 4) {
            $cleaned = '+'.substr($cleaned, 2);
        } elseif (preg_match('/^09\d{9}$/', $cleaned)) {
            // Standard Philippine local mobile format: 09171234567 -> +639171234567
            $cleaned = '+63'.substr($cleaned, 1);
        } elseif (preg_match('/^9\d{9}$/', $cleaned)) {
            // 10-digit PH mobile format: 9171234567 -> +639171234567
            $cleaned = '+63'.$cleaned;
        } elseif (preg_match('/^639\d{9}$/', $cleaned)) {
            // PH country code without leading +: 639171234567 -> +639171234567
            $cleaned = '+'.$cleaned;
        }

        return $cleaned;
    }

    /**
     * Update the user's Two-Factor Authentication (2FA) settings.
     */
    public function updateTwoFactor(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'two_factor_enabled' => ['required', 'boolean'],
            'two_factor_channel' => ['required', 'string', 'in:email,sms'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user = $request->user();
        $enabled = (bool) $validated['two_factor_enabled'];
        $channel = $validated['two_factor_channel'];
        $rawPhone = isset($validated['phone']) ? trim((string) $validated['phone']) : '';
        $normalizedPhone = $rawPhone !== ''
            ? self::normalizePhone($rawPhone)
            : self::normalizePhone($user->phone);

        // Validate E.164 phone format whenever a phone is entered or SMS channel is selected
        if ($rawPhone !== '' && ($normalizedPhone === null || ! preg_match('/^\+[1-9]\d{7,14}$/', $normalizedPhone))) {
            return Redirect::route('profile.edit')
                ->withInput()
                ->withErrors(['phone' => 'Please enter a valid mobile number (e.g. +639171234567 or 09171234567).'])
                ->withFragment('security-2fa');
        }

        // If user enables SMS 2FA, a valid phone number is strictly required
        if ($enabled && $channel === 'sms' && (empty($normalizedPhone) || ! preg_match('/^\+[1-9]\d{7,14}$/', $normalizedPhone))) {
            return Redirect::route('profile.edit')
                ->withInput()
                ->withErrors(['phone' => 'A valid mobile phone number (e.g. +639171234567 or 09171234567) is required when enabling SMS 2FA.'])
                ->withFragment('security-2fa');
        }

        $user->forceFill([
            'two_factor_enabled' => $enabled,
            'two_factor_channel' => $channel,
            'phone' => $normalizedPhone ?? $user->phone,
        ])->save();

        AuditLogger::log('profile.2fa_updated', [
            'two_factor_enabled' => $user->two_factor_enabled,
            'two_factor_channel' => $user->two_factor_channel,
            'phone' => $user->phone ? substr($user->phone, 0, 4).'****' : null,
        ]);

        return Redirect::route('profile.edit')
            ->with('status', 'two-factor-updated')
            ->withFragment('security-2fa');
    }

    /**
     * Send a test 6-digit verification code to verify the user's 2FA configuration.
     */
    public function sendTestTwoFactor(Request $request, BrevoService $brevo): RedirectResponse
    {
        $user = $request->user();
        $channel = $request->input('two_factor_channel', $user->two_factor_channel ?? 'email');
        $rawPhone = trim((string) $request->input('phone', $user->phone ?? ''));
        $normalizedPhone = self::normalizePhone($rawPhone);

        if ($normalizedPhone && preg_match('/^\+[1-9]\d{7,14}$/', $normalizedPhone) && $normalizedPhone !== $user->phone) {
            $user->forceFill(['phone' => $normalizedPhone])->save();
        }

        $code = $user->generateTwoFactorCode();

        if ($channel === 'sms') {
            $targetPhone = $normalizedPhone ?: $user->phone;
            if (empty($targetPhone) || ! preg_match('/^\+[1-9]\d{7,14}$/', $targetPhone)) {
                return Redirect::route('profile.edit')
                    ->withInput()
                    ->withErrors(['phone' => 'Please enter a valid mobile number (e.g. +639171234567 or 09171234567) before testing SMS delivery.'])
                    ->withFragment('security-2fa');
            }

            $smsResult = $brevo->sendSmsOtp($targetPhone, $code, '2FA Test');
            if ($smsResult['success']) {
                return Redirect::route('profile.edit')
                    ->with('two_factor_test_status', "Test SMS code ({$code}) dispatched to {$targetPhone}.")
                    ->withFragment('security-2fa');
            }

            // Fallback to email so the user still receives the test OTP and sees why SMS failed
            $emailResult = $brevo->sendEmailOtp($user->email, $user->name, $code, 'Two-Factor Authentication (SMS Fallback)');
            $smsErr = $smsResult['error'] ?: 'SMS provider unavailable or requires SMS credits';

            return Redirect::route('profile.edit')
                ->with('two_factor_test_status', "Phone {$targetPhone} saved. Note: Direct SMS gateway reported ({$smsErr}), so a backup 6-digit code was sent to {$user->email}" . ($emailResult['success'] ? '' : " (Test code: {$code})") . '.')
                ->withFragment('security-2fa');
        }

        $emailResult = $brevo->sendEmailOtp($user->email, $user->name, $code, 'Two-Factor Authentication Test');
        if ($emailResult['success']) {
            return Redirect::route('profile.edit')
                ->with('two_factor_test_status', "A 6-digit test verification code has been sent to {$user->email}.")
                ->withFragment('security-2fa');
        }

        return Redirect::route('profile.edit')
            ->withErrors(['two_factor_enabled' => 'Could not send test email: '.($emailResult['error'] ?? 'Unknown error')])
            ->withFragment('security-2fa');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        AuditLogger::log('profile.deleted', [
            'email' => $user->email,
        ], $user->id);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
