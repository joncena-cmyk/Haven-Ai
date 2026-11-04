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
        // When a session or CSRF token expires on logout, cleanly redirect to homepage
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            if ($request->is('logout') || $request->is('admin/logout')) {
                \Illuminate\Support\Facades\Auth::guard('web')->logout();
                \Illuminate\Support\Facades\Auth::guard('admin')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/');
            }

            return redirect()->route('login')->with('status', 'Your session has expired. Please sign in again.');
        });
    })->create();
