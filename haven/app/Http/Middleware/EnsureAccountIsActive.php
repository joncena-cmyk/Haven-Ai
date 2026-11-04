<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Sign out any authenticated user whose account has been suspended.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Always check the driver (web) guard; administrators live on the separate `admin` guard.
        $user = Auth::guard('web')->user();

        if ($user && $user->isSuspended()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'This account has been suspended. Contact an administrator.']);
        }

        return $next($request);
    }
}

