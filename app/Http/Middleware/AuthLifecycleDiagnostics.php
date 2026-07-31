<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Temporary, secret-safe diagnostics for the browser auth/session lifecycle.
 * Remove after the asymmetric 419 is identified.
 */
class AuthLifecycleDiagnostics
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isTarget($request)) {
            return $next($request);
        }

        $startedAt = microtime(true);
        $request->attributes->set('auth_lifecycle_request_id', substr(bin2hex(random_bytes(8)), 0, 12));
        $before = $this->snapshot($request);
        Log::info('AUTH_LIFECYCLE before', $before);

        $response = $next($request);

        $after = $this->snapshot($request);
        $after['elapsed_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
        $after['response_status'] = $response->getStatusCode();
        $after['redirect_target'] = $response->headers->get('Location');
        $after['response_content_type'] = $response->headers->get('Content-Type');
        $after['response_cookie_metadata'] = $this->responseCookieMetadata($response);

        if ($request->is('login') && str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            $after['login_markup'] = $this->loginMarkupSnapshot($response->getContent() ?: '');
        }

        if ($request->is('admin/dashboard') && str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            $after['admin_logout_markup'] = $this->logoutMarkupSnapshot($response->getContent() ?: '');
        }

        Log::info('AUTH_LIFECYCLE after', $after);

        return $response;
    }

    private function isTarget(Request $request): bool
    {
        return $request->is('admin/dashboard')
            || $request->is('admin/api/*')
            || $request->is('fleet/api/*')
            || $request->is('livewire/*')
            || $request->is('driver')
            || $request->is('driver/*')
            || $request->is('api/driver')
            || $request->is('api/driver/*')
            || $request->is('login')
            || $request->is('logout');
    }

    private function snapshot(Request $request): array
    {
        $session = $request->hasSession() ? $request->session() : null;
        $user = $request->user();
        $submittedToken = $request->isMethod('POST') ? $request->input('_token') : null;

        return [
            'path' => '/' . ltrim($request->path(), '/'),
            'method' => $request->method(),
            'host' => $request->getHost(),
            'request_id' => $request->attributes->get('auth_lifecycle_request_id'),
            'navigation_headers' => [
                'referer' => $request->headers->get('referer'),
                'sec_fetch_site' => $request->headers->get('sec-fetch-site'),
                'sec_fetch_mode' => $request->headers->get('sec-fetch-mode'),
                'sec_fetch_dest' => $request->headers->get('sec-fetch-dest'),
                'purpose' => $request->headers->get('purpose'),
                'x_purpose' => $request->headers->get('x-purpose'),
                'x_requested_with' => $request->headers->get('x-requested-with'),
                'accept' => $request->headers->get('accept'),
                'user_agent' => $request->userAgent(),
            ],
            'user_id' => $user?->id,
            'role' => $user?->role,
            'session_id_hash' => $this->hashValue($session?->getId()),
            'session_token_hash' => $this->hashValue($session?->token()),
            'submitted_token_hash' => $this->hashValue($submittedToken),
            'session_cookie_names' => array_keys($request->cookies->all()),
            'session_cookie_present' => $request->cookies->has(config('session.cookie')),
            'request_cookie_hashes' => array_map(fn ($value) => $this->hashValue((string) $value), array_intersect_key($request->cookies->all(), array_flip(['XSRF-TOKEN', config('session.cookie')]))),
            'session_cookie_name' => config('session.cookie'),
            'session_cookie_config' => [
                'path' => config('session.path'),
                'domain' => config('session.domain'),
                'secure' => config('session.secure'),
                'same_site' => config('session.same_site'),
            ],
        ];
    }

    private function responseCookieMetadata(Response $response): array
    {
        return array_map(static function ($cookie): array {
            return [
                'name' => $cookie->getName(),
                'path' => $cookie->getPath(),
                'domain' => $cookie->getDomain(),
                'secure' => $cookie->isSecure(),
                'same_site' => $cookie->getSameSite(),
                'value_hash' => substr(hash('sha256', $cookie->getValue()), 0, 12),
            ];
        }, $response->headers->getCookies());
    }

    private function logoutMarkupSnapshot(string $html): array
    {
        preg_match_all('/<form\b[^>]*>/i', $html, $forms);
        preg_match('/<form\b[^>]*id=["\x27]admin-logout-form["\x27][^>]*>/i', $html, $logoutForm);
        preg_match('/<form\b[^>]*id=["\x27]admin-logout-form["\x27][^>]*action=["\x27]([^"\x27]+)["\x27]/i', $html, $action);
        preg_match('/<form\b[^>]*id=["\x27]admin-logout-form["\x27][^>]*method=["\x27]([^"\x27]+)["\x27]/i', $html, $method);
        preg_match('/<form\b[^>]*id=["\x27]admin-logout-form["\x27][^>]*>.*?<input\b[^>]*name=["\x27]_token["\x27][^>]*value=["\x27]([^"\x27]+)["\x27]/is', $html, $token);

        return [
            'form_count' => count($forms[0] ?? []),
            'admin_logout_form_count' => preg_match_all('/id=["\x27]admin-logout-form["\x27]/i', $html),
            'form_present' => isset($logoutForm[0]),
            'action' => $action[1] ?? null,
            'method' => strtoupper($method[1] ?? ''),
            'token_hash' => $this->hashValue($token[1] ?? null),
            'nested_form_markup_detected' => $this->hasNestedLogoutForm($html),
        ];
    }

    private function loginMarkupSnapshot(string $html): array
    {
        preg_match_all('/<form\b[^>]*>/i', $html, $forms);
        preg_match_all('/<input\b[^>]*name=["\x27]_token["\x27][^>]*value=["\x27]([^"\x27]+)["\x27]/i', $html, $tokens);
        preg_match_all('/<meta\b[^>]*name=["\x27]csrf-token["\x27][^>]*content=["\x27]([^"\x27]+)["\x27]/i', $html, $metaTokens);

        return [
            'form_count' => count($forms[0] ?? []),
            'token_input_count' => count($tokens[1] ?? []),
            'token_input_hashes' => array_map(fn (string $token) => $this->hashValue($token), $tokens[1] ?? []),
            'csrf_meta_count' => count($metaTokens[1] ?? []),
            'csrf_meta_hashes' => array_map(fn (string $token) => $this->hashValue($token), $metaTokens[1] ?? []),
        ];
    }
    private function hasNestedLogoutForm(string $html): bool
    {
        if (! class_exists(\DOMDocument::class)) {
            return false;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);
        return $xpath->query('//form[@id="admin-logout-form"]//form')->length > 0;
    }
    private function hashValue(?string $value): ?string
    {
        return $value === null || $value === '' ? null : substr(hash('sha256', $value), 0, 12);
    }
}