<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    /**
     * Allow only sessions authenticated on the dedicated `admin` guard.
     * A signed-in driver probing /admin is turned away and the attempt is audited.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('admin')->check()) {
            $driver = Auth::guard('web')->user();

            if ($driver) {
                AuditLogger::log('admin.access_denied', [
                    'path' => $request->path(),
                ], $driver->id, 'failure');
            }

            if ($request->expectsJson()) {
                abort(401, 'Administrator authentication required.');
            }

            return redirect()->route('admin.login');
        }

        // Make `$request->user()` / `auth()->user()` resolve to the administrator for this request.
        Auth::shouldUse('admin');

        return $next($request);
    }
}

