<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->statefulApi();
        $middleware->validateCsrfTokens(except: [
            'logout',
            'admin/logout',
        ]);
        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdminAuthenticated::class,
        ]);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureAccountIsActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Universal graceful 419 / CSRF TokenMismatch handler
        $handle419 = function (\Illuminate\Http\Request $request) {
            if ($request->is('logout') || $request->is('admin/logout')) {
                \Illuminate\Support\Facades\Auth::guard('web')->logout();
                \Illuminate\Support\Facades\Auth::guard('admin')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/');
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'CSRF token mismatch or session expired. Please refresh and try again.',
                    'session_expired' => true,
                ], 419);
            }

            if (\Illuminate\Support\Facades\Auth::check()) {
                return redirect()->back()->with('error', 'Your session or security token was refreshed. Please try submitting again.');
            }

            return redirect()->route('login')->with('status', 'Your session has expired. Please sign in again.');
        };

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) use ($handle419) {
            if ($e->getStatusCode() === 419) {
                return $handle419($request);
            }
        });

        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) use ($handle419) {
            return $handle419($request);
        });
    })->create();
