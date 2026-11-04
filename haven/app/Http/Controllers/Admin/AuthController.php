<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'admin-login|'.Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            AuditLogger::log('admin.login_throttled', [
                'email' => $credentials['email'],
            ], null, 'failure');

            throw ValidationException::withMessages([
                'email' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        // Administrators never get a persistent "remember me" session.
        if (! Auth::guard('admin')->attempt($credentials, false)) {
            RateLimiter::hit($throttleKey);

            AuditLogger::log('admin.login_failed', [
                'email' => $credentials['email'],
            ], null, 'failure', Admin::where('email', $credentials['email'])->value('id'));

            throw ValidationException::withMessages([
                'email' => 'These administrator credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $admin = Auth::guard('admin')->user();
        $admin->forceFill(['last_login_at' => now()])->save();

        AuditLogger::log('admin.login', [
            'email' => $admin->email,
        ], null, 'success', $admin->id);

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        if ($admin) {
            AuditLogger::log('admin.logout', [
                'email' => $admin->email,
            ], null, 'success', $admin->id);
        }

        Auth::guard('admin')->logout();

        // Keep any driver session in this browser alive; only rotate identifiers.
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}

