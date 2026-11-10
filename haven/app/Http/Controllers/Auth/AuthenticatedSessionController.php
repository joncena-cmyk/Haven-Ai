<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Redirect standalone /login requests to the homepage pop-up login modal.
     */
    public function create(Request $request): RedirectResponse
    {
        $request->session()->reflash();

        return redirect()->to(url('/?login=1'));
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $requires2fa = $request->authenticate();

        if ($requires2fa) {
            if ($request->wantsJson()) {
                $twoFactorUser = \App\Models\User::find($request->session()->get('auth.two_factor_user_id'));
                $activeChannel = $request->session()->get('auth.two_factor_active_channel', $twoFactorUser?->two_factor_channel ?? 'email');
                $target = $activeChannel === 'sms' && ! empty($twoFactorUser?->phone)
                    ? substr($twoFactorUser->phone, 0, 4).'****'.substr($twoFactorUser->phone, -3)
                    : substr((string) $twoFactorUser?->email, 0, 2).'****@'.(explode('@', (string) $twoFactorUser?->email)[1] ?? '***');

                return response()->json([
                    'success' => true,
                    'requires_2fa' => true,
                    'channel' => $activeChannel,
                    'target' => $target,
                    'user_phone' => $twoFactorUser?->phone,
                    'has_phone' => ! empty($twoFactorUser?->phone),
                    'redirect' => route('two-factor.challenge', absolute: false),
                ]);
            }

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        AuditLogger::log('auth.login', ['email' => $request->email]);

        $intendedUrl = session()->pull('url.intended', route('dashboard', absolute: false));
        if (str_contains($intendedUrl, '/api/')) {
            $intendedUrl = route('dashboard', absolute: false);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => $intendedUrl,
            ]);
        }

        return redirect()->to($intendedUrl);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        AuditLogger::log('auth.logout', []);

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
