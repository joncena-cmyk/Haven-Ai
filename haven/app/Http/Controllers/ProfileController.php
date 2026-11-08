<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Vehicle;
use App\Services\AuditLogger;
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
     * Update the user's Two-Factor Authentication (2FA) settings.
     */
    public function updateTwoFactor(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'two_factor_enabled' => ['required', 'boolean'],
            'two_factor_channel' => ['required', 'string', 'in:email,sms'],
            'phone' => ['nullable', 'string', 'max:25'],
        ]);

        $channel = $validated['two_factor_channel'];
        $phone = ! empty($validated['phone']) ? trim($validated['phone']) : null;

        // If user enables SMS 2FA, phone is strictly required
        if ((bool) $validated['two_factor_enabled'] && $channel === 'sms' && empty($phone) && empty($request->user()->phone)) {
            return Redirect::route('profile.edit')
                ->withErrors(['phone' => 'A valid mobile phone number with country code is required when choosing SMS (Firebase) 2FA.'])
                ->withFragment('security-2fa');
        }

        $user = $request->user();
        $user->forceFill([
            'two_factor_enabled' => (bool) $validated['two_factor_enabled'],
            'two_factor_channel' => $channel,
            'phone' => $phone ?? $user->phone,
        ])->save();

        AuditLogger::log('profile.2fa_updated', [
            'two_factor_enabled' => $user->two_factor_enabled,
            'two_factor_channel' => $user->two_factor_channel,
            'phone' => $user->phone ? substr($user->phone, 0, 4) . '****' : null,
        ]);

        return Redirect::route('profile.edit')
            ->with('status', 'two-factor-updated')
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
