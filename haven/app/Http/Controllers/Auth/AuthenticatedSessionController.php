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
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $requires2fa = $request->authenticate();

        if ($requires2fa) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'requires_2fa' => true,
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
