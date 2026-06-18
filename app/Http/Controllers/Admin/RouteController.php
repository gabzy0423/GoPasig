<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\Stop;
use App\Models\SystemSetting;
use App\Models\DefaultRouteSetting;
use App\Models\Terminal;
use App\Services\BusinessLogicService;
use App\Services\ValidationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RouteController extends Controller
{
    /**
     * Store a newly created route in the database.
     * Issue 3.2.1: Now validates polyline coordinates
     */
    public function store(Request $request)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can create routes'
            ], 403);
        }
        $count = Route::count() + 1;
        $defaultPrefix = SystemSetting::get('default_route_name_prefix', 'Route ');

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'description' => 'sometimes|nullable|string',
            'color' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|string',
            'target_on_time_rate' => 'sometimes|required|integer|min:0|max:100',
            'target_headway_minutes' => 'sometimes|required|integer|min:1',
            'polyline_coordinates' => 'sometimes|nullable|array',
        ]);

        // NEW: Validate polyline if provided
        // Issue 3.2.1: Route polyline not validated
        if (!empty($request->polyline_coordinates)) {
            $polylineValidation = BusinessLogicService::validateRoutePolyline($request->polyline_coordinates);
            if (!$polylineValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid route polyline: ' . $polylineValidation['error']
                ], 422);
            }
        }

        $route = Route::create([
            'name' => $request->name ?? ($defaultPrefix . $count),
            'description' => $request->description,
            'color' => $request->color ?? SystemSetting::get('default_route_color', '#003F87'),
            'polyline_coordinates' => $request->polyline_coordinates ?? [],
            'status' => $request->status ?? SystemSetting::get('default_route_status', 'Active'),
            'target_on_time_rate' => $request->target_on_time_rate ?? 85,
            'target_headway_minutes' => $request->target_headway_minutes ?? 15,
        ]);

        // Create default stops for the route
        $routeDefaults = DefaultRouteSetting::first();
        $defaultOriginLabel = $routeDefaults?->default_origin_label
            ?? SystemSetting::get('default_route_origin_label', Terminal::getDefaultName());
        $defaultDestinationLabel = $routeDefaults?->default_destination_label
            ?? SystemSetting::get('default_route_destination_label', Terminal::findByName('New Terminus', 'New Terminus'));

        $fallbackLat = $routeDefaults?->default_latitude ?? SystemSetting::get('map_default_latitude', 14.5593);
        $fallbackLng = $routeDefaults?->default_longitude ?? SystemSetting::get('map_default_longitude', 121.0805);

        $originStop = Stop::create([
            'route_id' => $route->id,
            'name' => $defaultOriginLabel,
            'lat' => $fallbackLat,
            'lng' => $fallbackLng,
            'radius_meters' => 50,
            'sequence' => 1
        ]);

        $destinationStop = Stop::create([
            'route_id' => $route->id,
            'name' => $defaultDestinationLabel,
            'lat' => $fallbackLat + 0.002,
            'lng' => $fallbackLng + 0.002,
            'radius_meters' => 50,
            'sequence' => 2
        ]);

        // Set initial polyline from stops if not provided
        if (empty($request->polyline_coordinates)) {
            $route->update([
                'polyline_coordinates' => [
                    [$originStop->lat, $originStop->lng],
                    [$destinationStop->lat, $destinationStop->lng]
                ]
            ]);
        }

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
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can update routes'
            ], 403);
        }
        $allowedStatuses = explode(',', SystemSetting::get('allowed_route_statuses', 'Active,Suspended'));

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'description' => 'sometimes|nullable|string',
            'status' => ['sometimes', 'required', Rule::in($allowedStatuses)],
            'target_on_time_rate' => 'sometimes|required|integer|min:0|max:100',
            'target_headway_minutes' => 'sometimes|required|integer|min:1',
            'polyline_coordinates' => 'sometimes|nullable|array',
        ]);

        // NEW: Validate polyline if provided
        // Issue 3.2.1: Route polyline not validated
        if (isset($request->polyline_coordinates)) {
            $polylineValidation = BusinessLogicService::validateRoutePolyline($request->polyline_coordinates);
            if (!$polylineValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid route polyline: ' . $polylineValidation['error']
                ], 422);
            }
        }

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
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can delete routes'
            ], 403);
        }
        $route->delete();

        return response()->json([
            'success' => true,
            'message' => 'Route successfully deleted!'
        ]);
    }
}
