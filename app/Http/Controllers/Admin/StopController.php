<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Stop;
use App\Models\Route;
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
            'name' => 'required|string|max:100',
        ]);

        $routeId = $validated['route_id'];
        $name = $validated['name'];

        // Get terminus stop (highest sequence)
        $terminusStop = Stop::where('route_id', $routeId)->orderBy('sequence', 'desc')->first();
        
        if ($terminusStop) {
            $sequence = $terminusStop->sequence;
            // Shift terminus sequence up by 1 to insert new stop right before it
            $terminusStop->update(['sequence' => $sequence + 1]);
        } else {
            $sequence = 1;
        }

        // Auto-calculate coordinates based on previous stop
        $prevStop = Stop::where('route_id', $routeId)
            ->where('sequence', '<', $sequence)
            ->orderBy('sequence', 'desc')
            ->first();

        $lat = $prevStop ? $prevStop->lat + 0.002 : 14.5593;
        $lng = $prevStop ? $prevStop->lng + 0.002 : 121.0805;

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
