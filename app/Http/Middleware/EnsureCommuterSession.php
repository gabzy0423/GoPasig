<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\CommuterSession;
use Illuminate\Support\Str;

class EnsureCommuterSession
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
        $token = $request->cookie('commuter_session_token');

        $session = null;
        if ($token) {
            $session = CommuterSession::where('session_token', $token)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
                })
                ->first();
        }

        if (!$token || !$session) {
            $token = Str::random(40);

            CommuterSession::create([
                'session_token' => $token,
                'ip_address' => $request->ip(),
                'expires_at' => now()->addHours(24),
            ]);

            // Set cookie for 24 hours (1440 minutes)
            $response = $next($request);
            return $response->cookie('commuter_session_token', $token, 1440, null, null, true, true);
        }

        // Refresh session expiration and touch updated_at to track activity
        $session->expires_at = now()->addHours(24);
        $session->touch();
        $session->save();

        return $next($request);
    }
}
