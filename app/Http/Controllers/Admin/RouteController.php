<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\Stop;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RouteController extends Controller
{
    /**
     * Store a newly created route in the database.
     */
    public function store(Request $request)
    {
        $count = Route::count() + 1;
        $defaultPrefix = SystemSetting::get('default_route_name_prefix', 'Route ');

        $route = Route::create([
            'name' => $request->name ?? ($defaultPrefix . $count),
            'description' => $request->description,
            'color' => $request->color ?? SystemSetting::get('default_route_color', '#003F87'),
            'polyline_coordinates' => $request->polyline_coordinates ?? [],
            'status' => $request->status ?? SystemSetting::get('default_route_status', 'Active'),
        ]);


        return response()->json([
            'success' => true,
            'message' => 'Route successfully created!',
            'route' => $route
        ], 201);
    }

    /**
     * Update the specified route details or status in the database.
     */
    public function update(Request $request, Route $route)
    {
        $allowedStatuses = explode(',', SystemSetting::get('allowed_route_statuses', 'Active,Suspended'));

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'description' => 'sometimes|nullable|string',
            'status' => ['sometimes', 'required', Rule::in($allowedStatuses)],
        ]);

        $route->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Route successfully updated!',
            'route' => $route
        ]);
    }

    /**
     * Remove the specified route from the database.
     */
    public function destroy(Route $route)
    {
        $route->delete();

        return response()->json([
            'success' => true,
            'message' => 'Route successfully deleted!'
        ]);
    }
}
