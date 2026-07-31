<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class FleetManagerAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && in_array(auth()->user()->role, ['admin', 'fleet_manager'])) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized: Fleet Operations Manager access required.'
        ], 403);
    }
}
