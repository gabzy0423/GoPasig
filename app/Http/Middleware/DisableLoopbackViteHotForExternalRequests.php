<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class DisableLoopbackViteHotForExternalRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useHotFile(public_path('hot'));

        $hotFile = public_path('hot');
        if (is_file($hotFile)) {
            $hotUrl = trim((string) file_get_contents($hotFile));
            $hotHost = parse_url($hotUrl, PHP_URL_HOST);

            if ($hotHost && $this->isLoopbackHost($hotHost) && ! $this->isLoopbackHost($request->getHost())) {
                Vite::useHotFile(storage_path('framework/vite-hot-disabled'));
            }
        }

        return $next($request);
    }

    private function isLoopbackHost(?string $host): bool
    {
        if (! $host) {
            return false;
        }

        $host = strtolower(trim($host, '[]'));

        return in_array($host, ['127.0.0.1', 'localhost', '::1', '0.0.0.0'], true);
    }
}