<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! in_array($request->user()->role, $roles)) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->user()->role === 'driver') {
            if (! \App\Models\Driver::where('user_id', $request->user()->id)->exists()) {
                \Illuminate\Support\Facades\Auth::logout();
                return redirect()->route('login')->withErrors(['email' => 'No associated driver profile found.']);
            }
        }

        return $next($request);
    }
}
