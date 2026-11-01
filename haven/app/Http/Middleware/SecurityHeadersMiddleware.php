<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request and apply ISO 27001 compliant HTTP security headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 1. Anti-Clickjacking: Disallow embedding in foreign iframes
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // 2. MIME Sniffing Protection
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // 3. XSS Filter (legacy browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // 4. Referrer Policy: Send full URL on same-origin, domain-only on cross-origin
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 5. Hardware Feature Policy: Allow microphone for engine audio, disallow camera/geolocation
        $response->headers->set('Permissions-Policy', 'microphone=(self), camera=(), geolocation=(), payment=()');

        // 6. Cross-Origin Resource Policy
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-site');

        return $response;
    }
}
