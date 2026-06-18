<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DispatcherAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && in_array(auth()->user()->role, ['admin', 'dispatcher'])) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized: Dispatcher access required.'
        ], 403);
    }
}
