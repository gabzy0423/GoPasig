<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\MaintenanceRecord;
use App\Models\Schedule;
use App\Models\Trip;
use App\Models\SystemSetting;
use App\Models\Route;
use App\Services\ValidationService;
use App\Services\BusStateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BusController extends Controller
{
    /**
     * Show the form for registering a new bus.
     */
    public function create()
    {
        $routes = Route::all();
        // Get all capacity settings from SystemSetting instead of hardcoding
        $defaultCapacity = (int) SystemSetting::get('default_bus_capacity', 45);
        $minCapacity = (int) SystemSetting::get('bus_capacity_min', 10);
        $maxCapacity = (int) SystemSetting::get('bus_capacity_max', 150);

        return view('admin.buses.create', compact('routes', 'defaultCapacity', 'minCapacity', 'maxCapacity'));
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
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can create buses'
            ], 403);
        }
        // Get capacity constraints from SystemSetting instead of hardcoding
        $minCapacity = (int) SystemSetting::get('bus_capacity_min', 10);
        $maxCapacity = (int) SystemSetting::get('bus_capacity_max', 150);

        $validated = $request->validate([
            'plate_number' => 'required|string|max:20|unique:buses,plate_number',
            'route_id' => 'nullable|exists:routes,id',
            'driver_name' => 'nullable|string|max:100',
            'capacity' => "required|integer|min:{$minCapacity}|max:{$maxCapacity}",
            'status' => 'required|in:active,inactive,maintenance',
        ]);

        // Get default coordinates from SystemSetting instead of hardcoding
        $defaultLat = (float) SystemSetting::get('map_default_latitude', 14.5593);
        $defaultLng = (float) SystemSetting::get('map_default_longitude', 121.0805);

        // Validate GPS coordinates (Issue 5.1.1)
        $gpsValidation = ValidationService::validateGPSCoordinates($defaultLat, $defaultLng);
        if (!$gpsValidation['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid default GPS coordinates: ' . $gpsValidation['message']
            ], 422);
        }

        $bus = Bus::create([
            'plate_number' => $validated['plate_number'],
            'route_id' => $validated['route_id'] ?: null,
            'driver_name' => $validated['driver_name'] ?: Bus::DEFAULT_DRIVER_NAME,
            'capacity' => $validated['capacity'],
            'status' => $validated['status'],
            'speed' => Bus::getInitialSpeed(),
            'passengers' => Bus::getInitialPassengers(),
            'next_stop' => Bus::getDefaultNextStop(),
            'eta' => Bus::getInitialEta(),
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
     * Issue 4.2.2: Validates bus status transitions
     */
    public function update(Request $request, Bus $bus)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can update buses'
            ], 403);
        }
        // Get capacity constraints from SystemSetting instead of hardcoding
        $minCapacity = (int) SystemSetting::get('bus_capacity_min', 10);
        $maxCapacity = (int) SystemSetting::get('bus_capacity_max', 150);

        $validated = $request->validate([
            'plate_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('buses')->ignore($bus->id),
            ],
            'route_id' => 'nullable|exists:routes,id',
            'driver_name' => 'nullable|string|max:100',
            'capacity' => "required|integer|min:{$minCapacity}|max:{$maxCapacity}",
            'status' => 'required|in:active,inactive,maintenance',
        ]);

        // Status transition and field edits are wrapped together so a failed
        // field update rolls back the status change too.
        try {
            DB::transaction(function () use ($bus, $validated) {
                if ($validated['status'] !== $bus->status) {
                    BusStateService::transition($bus, $validated['status'], 'Status update via admin');
                    $bus->refresh();
                }

                $bus->update([
                    'plate_number' => $validated['plate_number'],
                    'route_id' => $validated['route_id'] ?: null,
                    'driver_name' => $validated['driver_name'] ?: Bus::DEFAULT_DRIVER_NAME,
                    'capacity' => $validated['capacity'],
                ]);
            });
        } catch (InvalidStatusTransitionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'valid_transitions' => $e->validTransitions,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bus successfully updated!',
            'bus' => $bus->fresh()
        ]);
    }

    /**
     * Remove the specified bus from the database.
     */
    public function destroy(Bus $bus)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can delete buses'
            ], 403);
        }
        // Guard: block deletion if bus is currently in use
        $ongoingTrip = Trip::where('bus_id', $bus->id)->where('status', 'ongoing')->exists();
        if ($ongoingTrip) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete bus: it has an ongoing trip in progress.',
            ], 422);
        }

        $activeSchedule = Schedule::where('bus_id', $bus->id)
            ->where('status', '!=', Schedule::STATUS_CANCELLED)
            ->exists();
        if ($activeSchedule) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete bus: it is assigned to an active or pending schedule.',
            ], 422);
        }

        $openMaintenance = MaintenanceRecord::where('bus_id', $bus->id)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->exists();
        if ($openMaintenance) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete bus: it has an open maintenance record.',
            ], 422);
        }

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
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can assign routes'
            ], 403);
        }
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
