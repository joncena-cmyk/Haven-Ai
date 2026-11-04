<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        } elseif (isset($_SERVER['HTTP_HOST']) && str_contains($_SERVER['HTTP_HOST'], 'ngrok')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        RateLimiter::for('ai-ask', function (Request $request) {
            return Limit::perMinute(15)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('telemetry-ingest', function (Request $request) {
            $vehicleKey = (string) ($request->input('vehicle_id') ?? 'unknown');
            return Limit::perMinute(30)->by($vehicleKey . '|' . $request->ip());
        });
    }
}
