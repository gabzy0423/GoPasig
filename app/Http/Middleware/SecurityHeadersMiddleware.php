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

        // ─────────────────────────────────────────────────────────────────
        // Content Security Policy
        //
        // Rules are kept environment-agnostic: $appUrl (from APP_URL in
        // .env) is always injected so the policy works correctly whether
        // the app is accessed locally, via ngrok, or via any other proxy.
        //
        // Vite dev-server origins (localhost:5173) are always included —
        // they are harmless no-ops when the dev server is not running but
        // are required for hot-reload during local development.
        //
        // Mobile browsers are strict about 'self' — when the request
        // arrives via ngrok, 'self' resolves to the ngrok origin, so we
        // must also explicitly list that origin in every directive that
        // might serve resources.
        // ─────────────────────────────────────────────────────────────────
        $appUrl  = rtrim(config('app.url'), '/');
        $appHost = parse_url($appUrl, PHP_URL_HOST) ?? '';

        // WebSocket equivalent of the app URL (for Vite HMR over ngrok if ever needed)
        $appWs = str_starts_with($appUrl, 'https://')
            ? 'wss://' . $appHost
            : 'ws://' . $appHost;

        $csp = implode('; ', [
            // Catch-all fallback: self + ngrok origin
            "default-src 'self' {$appUrl}",

            // Scripts: self, ngrok, Vite dev server, external CDNs
            "script-src 'self' {$appUrl} 'unsafe-inline' 'unsafe-eval'"
                . " http://localhost:5173 http://127.0.0.1:5173 http://[::1]:5173"
                . " https://maps.googleapis.com https://challenges.cloudflare.com"
                . " https://unpkg.com https://cdn.jsdelivr.net",

            // Styles: self, ngrok, Vite dev server, Google Fonts
            "style-src 'self' {$appUrl} 'unsafe-inline'"
                . " http://localhost:5173 http://127.0.0.1:5173 http://[::1]:5173"
                . " https://fonts.googleapis.com https://maps.googleapis.com https://unpkg.com",

            // Images: self, ngrok, data URIs, map tiles
            "img-src 'self' {$appUrl} data: blob:"
                . " https://maps.googleapis.com https://maps.gstatic.com"
                . " https://*.googleapis.com *.tile.openstreetmap.org",

            // Fonts: self, ngrok, Vite dev server, Google Fonts CDN, data URIs
            // tabler-icons.css references fonts via relative ./fonts/ path so
            // 'self' covers those; ngrok is needed for mobile.
            "font-src 'self' {$appUrl}"
                . " http://localhost:5173 http://127.0.0.1:5173 http://[::1]:5173"
                . " https://fonts.gstatic.com data:",

            // XHR / fetch / WebSocket: self, ngrok, Vite HMR, Google Maps
            "connect-src 'self' {$appUrl} {$appWs}"
                . " http://localhost:5173 http://127.0.0.1:5173"
                . " ws://localhost:5173 ws://127.0.0.1:5173 ws://[::1]:5173 http://[::1]:5173"
                . " https://maps.googleapis.com https://challenges.cloudflare.com"
                . " https://unpkg.com https://cdn.jsdelivr.net",

            // Frames: self + Cloudflare Turnstile
            "frame-src 'self' https://challenges.cloudflare.com",

            // Workers: self + blob for any inline workers
            "worker-src 'self' blob:",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        if ($this->shouldPreventBrowserCaching($request, $response)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
        }

        return $response;
    }
    private function shouldPreventBrowserCaching(Request $request, Response $response): bool
    {
        $route = $request->route();
        $routeName = $route?->getName();
        $routeMiddleware = $route ? $route->gatherMiddleware() : [];
        $contentType = $response->headers->get('Content-Type', '');

        $isHtmlResponse = $contentType === '' || str_contains($contentType, 'text/html');
        $isAuthRoute = in_array('auth', $routeMiddleware, true);
        $isAuthEntryPoint = in_array($routeName, ['login', 'logout'], true);

        return $isHtmlResponse && ($isAuthRoute || $isAuthEntryPoint);
    }
}

