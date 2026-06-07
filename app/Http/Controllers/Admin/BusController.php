<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusController extends Controller
{
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

        // Default starting coordinates centered on SPED Terminal for map mapping
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
            'lat' => 14.5593,
            'lng' => 121.0805,
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
