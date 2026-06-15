<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\DefaultRouteSetting;
use App\Models\SystemSetting;

class BusController extends Controller
{
    /**
     * Show the form for registering a new bus.
     */
    public function create()
    {
        return redirect('/admin/dashboard#buses');
    }

    /**
     * Show the form for editing the specified bus.
     */
    public function edit(Bus $bus)
    {
        return redirect('/admin/dashboard#buses');
    }

    /**
     * Store a newly created bus in the database.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'plate_number' => 'required|string|max:20|unique:buses,plate_number',
            'route_id' => 'nullable|exists:routes,id',
            'driver_name' => 'nullable|string|max:100',
            'capacity' => 'required|integer|min:1|max:150',
            'status' => 'required|in:active,inactive,maintenance',
        ]);

        // Default starting coordinates centered on the default terminal for map mapping
        // Resolve default coordinates from DefaultRouteSetting or SystemSetting
        $routeDefaults = DefaultRouteSetting::latest()->first();
        $defaultLat = $routeDefaults?->default_latitude ?? (float) SystemSetting::get('default_route_start_lat', 14.5593);
        $defaultLng = $routeDefaults?->default_longitude ?? (float) SystemSetting::get('default_route_start_lng', 121.0805);

        $bus = Bus::create([
            'plate_number' => $validated['plate_number'],
            'route_id' => $validated['route_id'] ?: null,
            'driver_name' => $validated['driver_name'] ?: 'Unassigned',
            'capacity' => $validated['capacity'],
            'status' => strtolower($validated['status']),
            'speed' => 0,
            'passengers' => 0,
            'next_stop' => 'None',
            'eta' => 0,
            'lat' => $defaultLat,
            'lng' => $defaultLng,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bus successfully registered!',
            'bus' => $bus
        ], 201);
    }

    /**
     * Update the specified bus in the database.
     */
    public function update(Request $request, Bus $bus)
    {
        $validated = $request->validate([
            'plate_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('buses')->ignore($bus->id),
            ],
            'route_id' => 'nullable|exists:routes,id',
            'driver_name' => 'nullable|string|max:100',
            'capacity' => 'required|integer|min:1|max:150',
            'status' => 'required|in:active,inactive,maintenance',
        ]);

        $bus->update([
            'plate_number' => $validated['plate_number'],
            'route_id' => $validated['route_id'] ?: null,
            'driver_name' => $validated['driver_name'] ?: 'Unassigned',
            'capacity' => $validated['capacity'],
            'status' => strtolower($validated['status']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bus successfully updated!',
            'bus' => $bus
        ]);
    }

    /**
     * Remove the specified bus from the database.
     */
    public function destroy(Bus $bus)
    {
        $bus->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bus successfully deleted!'
        ]);
    }

    /**
     * Assign a route to the specified bus.
     */
    public function assignRoute(Bus $bus, Request $request)
    {
        $validated = $request->validate([
            'route_id' => 'nullable|exists:routes,id',
        ]);

        $bus->update(['route_id' => $validated['route_id']]);

        return response()->json([
            'success' => true,
            'message' => 'Bus route assignment updated successfully!'
        ]);
    }
}
