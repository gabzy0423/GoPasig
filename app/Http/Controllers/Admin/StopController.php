<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DefaultRouteSetting;
use App\Models\Route;
use App\Models\Stop;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class StopController extends Controller
{
    /**
     * Store a newly created stop on a route.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'route_id' => 'required|exists:routes,id',
            'name' => 'sometimes|nullable|string|max:100',
        ]);

        $routeId = $validated['route_id'];
        $routeDefaults = DefaultRouteSetting::first();
        $defaultOriginLabel = $routeDefaults?->default_origin_label ?? SystemSetting::get('default_route_origin_label', 'Pasig Terminal');
        $defaultDestinationLabel = $routeDefaults?->default_destination_label ?? SystemSetting::get('default_route_destination_label', 'New Terminus');
        $stopCount = Stop::where('route_id', $routeId)->count();
        $name = $validated['name'] ?? ($stopCount > 0 ? $defaultDestinationLabel : $defaultOriginLabel);

        // Get terminus stop (highest sequence)
        $terminusStop = Stop::where('route_id', $routeId)->orderBy('sequence', 'desc')->first();
        
        if ($terminusStop) {
            $sequence = $terminusStop->sequence;
            // Shift terminus sequence up by 1 to insert new stop right before it
            $terminusStop->update(['sequence' => $sequence + 1]);
        } else {
            $sequence = 1;
        }

        // Auto-calculate coordinates based on previous stop or route geometry
        $prevStop = Stop::where('route_id', $routeId)
            ->where('sequence', '<', $sequence)
            ->orderBy('sequence', 'desc')
            ->first();

        $route = Route::find($routeId);
        $fallbackLat = $routeDefaults?->default_latitude ?? SystemSetting::get('default_route_start_lat', 14.5593);
        $fallbackLng = $routeDefaults?->default_longitude ?? SystemSetting::get('default_route_start_lng', 121.0805);

        $routeStartLat = $route && is_array($route->polyline_coordinates) && count($route->polyline_coordinates)
            ? $route->polyline_coordinates[0][0]
            : $fallbackLat;
        $routeStartLng = $route && is_array($route->polyline_coordinates) && count($route->polyline_coordinates)
            ? $route->polyline_coordinates[0][1]
            : $fallbackLng;

        $lat = $prevStop ? $prevStop->lat + 0.002 : $routeStartLat;
        $lng = $prevStop ? $prevStop->lng + 0.002 : $routeStartLng;

        $stop = Stop::create([
            'route_id' => $routeId,
            'name' => $name,
            'lat' => $lat,
            'lng' => $lng,
            'radius_meters' => 50,
            'sequence' => $sequence
        ]);

        // Recalculate route polyline coordinates
        $this->updateRoutePolyline($routeId);

        return response()->json([
            'success' => true,
            'message' => 'Stop successfully added to route!',
            'stop' => $stop
        ], 201);
    }

    /**
     * Reorder stops for a route.
     */
    public function reorder(Request $request, Route $route)
    {
        $validated = $request->validate([
            'stop_ids' => 'required|array',
            'stop_ids.*' => 'exists:stops,id'
        ]);

        foreach ($validated['stop_ids'] as $index => $id) {
            Stop::where('id', $id)->where('route_id', $route->id)->update([
                'sequence' => $index + 1
            ]);
        }

        $this->updateRoutePolyline($route->id);

        return response()->json([
            'success' => true,
            'message' => 'Stop sequence successfully reordered!'
        ]);
    }

    /**
     * Remove the specified stop from a route.
     */
    public function destroy(Stop $stop)
    {
        $routeId = $stop->route_id;
        $stop->delete();

        // Re-sequence remaining stops
        $stops = Stop::where('route_id', $routeId)->orderBy('sequence')->get();
        foreach ($stops as $index => $s) {
            $s->update(['sequence' => $index + 1]);
        }

        $this->updateRoutePolyline($routeId);

        return response()->json([
            'success' => true,
            'message' => 'Stop successfully deleted!'
        ]);
    }

    /**
     * Helper to update the route's polyline coordinate array based on stop coordinates.
     */
    private function updateRoutePolyline($routeId)
    {
        $route = Route::find($routeId);
        if ($route) {
            $coords = Stop::where('route_id', $routeId)
                ->orderBy('sequence')
                ->get()
                ->map(function($s) {
                    return [$s->lat, $s->lng];
                })
                ->toArray();
            
            $route->update(['polyline_coordinates' => $coords]);
        }
    }
}
