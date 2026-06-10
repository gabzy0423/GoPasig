<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    /**
     * Display a listing of schedules.
     */
    public function index()
    {
        $schedules = Schedule::with(['route', 'bus', 'driver'])->get();

        $formatted = $schedules->map(function ($s) {
            return [
                'id' => $s->id,
                'routeId' => (string) $s->route_id,
                'time' => substr($s->departure_time, 0, 5), // HH:MM
                'driver' => $s->driver ? $s->driver->initials : '',
                'driverName' => $s->driver ? ($s->driver->first_name . ' ' . $s->driver->last_name) : 'Unassigned',
                'driverId' => $s->driver_id,
                'bus' => $s->bus ? $s->bus->plate_number : 'Unassigned',
                'busId' => $s->bus_id,
                'pax' => $s->passengers,
                'status' => $s->status,
            ];
        });

        return response()->json([
            'success' => true,
            'schedules' => $formatted
        ]);
    }

    /**
     * Store a newly created schedule.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'route_id' => 'required|exists:routes,id',
            'bus_plate' => 'required|string',
            'driver_initials' => 'required|string',
            'departure_time' => 'required|string', // HH:MM
        ]);

        $bus = Bus::where('plate_number', $validated['bus_plate'])->first();
        if (!$bus) {
            return response()->json(['success' => false, 'message' => 'Bus not found.'], 404);
        }

        // Find driver by initials
        $driver = Driver::all()->first(fn($d) => $d->initials === $validated['driver_initials']);
        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 404);
        }

        // Compute estimated arrival time using route travel_time_minutes from the routes table
        $departure = $validated['departure_time'];
        $duration = $this->resolveRouteTravelDuration($validated['route_id']);

        $timeParts = explode(':', $departure);
        $totalMinutes = intval($timeParts[0]) * 60 + intval($timeParts[1]) + $duration;
        $arrivalHour = floor($totalMinutes / 60) % 24;
        $arrivalMinute = $totalMinutes % 60;
        $arrivalTime = sprintf('%02d:%02d', $arrivalHour, $arrivalMinute);

        $schedule = Schedule::create([
            'route_id' => $validated['route_id'],
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'departure_time' => $departure,
            'arrival_time' => $arrivalTime,
            'passengers' => 0,
            'status' => 'On time'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Schedule successfully created!',
            'schedule' => [
                'id' => $schedule->id,
                'routeId' => (string) $schedule->route_id,
                'time' => substr($schedule->departure_time, 0, 5),
                'driver' => $driver->initials,
                'driverName' => $driver->first_name . ' ' . $driver->last_name,
                'driverId' => $driver->id,
                'bus' => $bus->plate_number,
                'busId' => $bus->id,
                'pax' => $schedule->passengers,
                'status' => $schedule->status
            ]
        ], 201);
    }

    /**
     * Update the specified schedule.
     */
    public function update(Request $request, Schedule $schedule)
    {
        $validated = $request->validate([
            'route_id' => 'required|exists:routes,id',
            'bus_plate' => 'required|string',
            'driver_initials' => 'required|string',
            'departure_time' => 'required|string',
        ]);

        $bus = Bus::where('plate_number', $validated['bus_plate'])->first();
        if (!$bus) {
            return response()->json(['success' => false, 'message' => 'Bus not found.'], 404);
        }

        $driver = Driver::all()->first(fn($d) => $d->initials === $validated['driver_initials']);
        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 404);
        }

        // Compute estimated arrival time using route travel_time_minutes from the routes table
        $departure = $validated['departure_time'];
        $duration = $this->resolveRouteTravelDuration($validated['route_id']);

        $timeParts = explode(':', $departure);
        $totalMinutes = intval($timeParts[0]) * 60 + intval($timeParts[1]) + $duration;
        $arrivalHour = floor($totalMinutes / 60) % 24;
        $arrivalMinute = $totalMinutes % 60;
        $arrivalTime = sprintf('%02d:%02d', $arrivalHour, $arrivalMinute);

        $schedule->update([
            'route_id' => $validated['route_id'],
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'departure_time' => $departure,
            'arrival_time' => $arrivalTime,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Schedule successfully updated!',
            'schedule' => [
                'id' => $schedule->id,
                'routeId' => (string) $schedule->route_id,
                'time' => substr($schedule->departure_time, 0, 5),
                'driver' => $driver->initials,
                'driverName' => $driver->first_name . ' ' . $driver->last_name,
                'driverId' => $driver->id,
                'bus' => $bus->plate_number,
                'busId' => $bus->id,
                'pax' => $schedule->passengers,
                'status' => $schedule->status
            ]
        ]);
    }

    /**
     * Remove the specified schedule.
     */
    public function destroy(Schedule $schedule)
    {
        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Schedule successfully deleted!'
        ]);
    }

    /**
     * Resolve duration for a route from the routes table or fallback system setting.
     */
    protected function resolveRouteTravelDuration(int $routeId): int
    {
        $route = Route::find($routeId);
        $duration = $route?->travel_time_minutes;

        if ($duration === null) {
            // Use configurable fallback instead of hardcoded 30 minutes
            return (int) SystemSetting::get('default_travel_time_minutes', 30);
        }

        return (int) $duration;
    }
}
