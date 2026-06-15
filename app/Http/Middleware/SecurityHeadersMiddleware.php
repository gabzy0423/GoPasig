<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Standard HTTP Security Headers
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Content Security Policy
        $isLocal = app()->environment('local');

        $scriptSrc = $isLocal 
            ? "script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:5173 http://127.0.0.1:5173 http://[::1]:5173 https://maps.googleapis.com https://challenges.cloudflare.com https://unpkg.com https://cdn.jsdelivr.net"
            : "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://maps.googleapis.com https://challenges.cloudflare.com https://unpkg.com https://cdn.jsdelivr.net";

        $styleSrc = $isLocal
            ? "style-src 'self' 'unsafe-inline' http://localhost:5173 http://127.0.0.1:5173 http://[::1]:5173 https://fonts.googleapis.com https://maps.googleapis.com https://unpkg.com"
            : "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://maps.googleapis.com https://unpkg.com";

        $connectSrc = $isLocal
            ? "connect-src 'self' http://localhost:5173 http://127.0.0.1:5173 ws://localhost:5173 ws://127.0.0.1:5173 ws://[::1]:5173 http://[::1]:5173 https://maps.googleapis.com https://challenges.cloudflare.com https://unpkg.com https://cdn.jsdelivr.net"
            : "connect-src 'self' https://maps.googleapis.com https://challenges.cloudflare.com https://unpkg.com https://cdn.jsdelivr.net";

        $fontSrc = $isLocal
            ? "font-src 'self' http://localhost:5173 http://127.0.0.1:5173 http://[::1]:5173 https://fonts.gstatic.com data:;"
            : "font-src 'self' https://fonts.gstatic.com data:;";

        $csp = "default-src 'self'; " .
               "{$scriptSrc}; " .
               "{$styleSrc}; " .
               "img-src 'self' data: https://maps.googleapis.com https://maps.gstatic.com https://*.googleapis.com *.tile.openstreetmap.org; " .
               "{$fontSrc}; " .
               "{$connectSrc}; " .
               "frame-src 'self' https://challenges.cloudflare.com;";

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
