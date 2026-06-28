<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\SystemSetting;
use App\Services\BusinessLogicService;
use App\Services\DriverPerformanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function create(Request $request)
    {
        // Get settings from SystemSetting instead of hardcoding
        $defaultTravelTime = (int) SystemSetting::get('schedule_default_travel_time_minutes', 30);
        $scheduleBuffer = (int) SystemSetting::get('driver_schedule_buffer_minutes', 15);
        $busScheduleBuffer = (int) SystemSetting::get('bus_schedule_buffer_minutes', 15);

        return response()->json([
            'success' => true,
            'settings' => [
                'defaultTravelTime' => $defaultTravelTime,
                'scheduleBuffer' => $scheduleBuffer,
                'busScheduleBuffer' => $busScheduleBuffer,
            ],
        ]);
    }

    /**
     * Show the conflict check page.
     */
    public function conflict()
    {
        return redirect('/admin/dashboard#schedules-conflict');
    }

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
                'driverName' => $s->driver ? ($s->driver->first_name.' '.$s->driver->last_name) : 'Unassigned',
                'driverId' => $s->driver_id,
                'bus' => $s->bus ? $s->bus->plate_number : 'Unassigned',
                'busId' => $s->bus_id,
                'pax' => $s->passengers,
                'status' => $s->status,
            ];
        });

        return response()->json([
            'success' => true,
            'schedules' => $formatted,
        ]);
    }

    /**
     * Store a newly created schedule.
     * Issue 3.1.1: Enhanced with driver and bus availability checks
     */
    public function store(Request $request)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can create schedules',
            ], 403);
        }
        $validated = $request->validate([
            'route_id' => 'required|exists:routes,id',
            'bus_plate' => 'required|string',
            'driver_id' => 'required|integer|exists:drivers,id',
            'service_date' => 'nullable|date',
            'departure_time' => 'required|date_format:H:i',
        ]);

        $bus = Bus::where('plate_number', $validated['bus_plate'])->first();
        if (! $bus) {
            return response()->json(['success' => false, 'message' => 'Bus not found.'], 404);
        }

        // NEW: Validate bus availability
        // Issue 3.1.1: Schedule conflict incomplete
        $busAvailable = BusinessLogicService::validateBusAvailability($bus->id);
        if (! $busAvailable['available']) {
            return response()->json(['success' => false, 'message' => $busAvailable['error']], 422);
        }

        $driver = Driver::find($validated['driver_id']);
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 404);
        }

        // NEW: Validate driver availability
        // Issue 3.1.1: Schedule conflict incomplete
        $driverAvailable = BusinessLogicService::validateDriverAvailability($driver->id);
        if (! $driverAvailable['available']) {
            return response()->json(['success' => false, 'message' => $driverAvailable['error']], 422);
        }

        // NEW: Check daily hours limit for driver
        $maxDailyHours = (int) SystemSetting::get('driver_max_daily_hours', 10);
        $dailyHoursCheck = BusinessLogicService::checkDriverDailyHours(
            $driver->id,
            $validated['departure_time'],
            $this->resolveRouteTravelDuration($validated['route_id']),
            $maxDailyHours,
            null,
            $validated['service_date'] ?? now('Asia/Manila')->toDateString()
        );
        if (! $dailyHoursCheck['allowed']) {
            return response()->json(['success' => false, 'message' => $dailyHoursCheck['error']], 422);
        }

        // NEW: Use enhanced conflict checking from BusinessLogicService
        // Issue 3.1.1: Schedule conflict incomplete
        $conflictCheck = BusinessLogicService::checkScheduleConflict(
            $bus->id,
            $driver->id,
            $validated['route_id'],
            $validated['departure_time'],
            null,
            $validated['service_date'] ?? now('Asia/Manila')->toDateString()
        );

        if ($conflictCheck['conflict']) {
            return response()->json([
                'success' => false,
                'message' => $conflictCheck['message'],
            ], 422);
        }

        // Compute estimated arrival time using route duration from the database or settings
        $departure = $validated['departure_time'];
        $duration = $this->resolveRouteTravelDuration($validated['route_id']);

        $timeParts = explode(':', $departure);
        $totalMinutes = intval($timeParts[0]) * 60 + intval($timeParts[1]) + $duration;
        $arrivalHour = floor($totalMinutes / 60) % 24;
        $arrivalMinute = $totalMinutes % 60;
        $arrivalTime = sprintf('%02d:%02d', $arrivalHour, $arrivalMinute);

        $schedule = Schedule::create([
            'route_id' => $validated['route_id'],
            'service_date' => $validated['service_date'] ?? now('Asia/Manila')->toDateString(),
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'departure_time' => $departure,
            'arrival_time' => $arrivalTime,
            'passengers' => 0,
            'status' => 'On time',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Schedule successfully created!',
            'schedule' => [
                'id' => $schedule->id,
                'routeId' => (string) $schedule->route_id,
                'time' => substr($schedule->departure_time, 0, 5),
                'driver' => $driver->initials,
                'driverName' => $driver->first_name.' '.$driver->last_name,
                'driverId' => $driver->id,
                'bus' => $bus->plate_number,
                'busId' => $bus->id,
                'pax' => $schedule->passengers,
                'status' => $schedule->status,
            ],
        ], 201);
    }

    /**
     * Update the specified schedule.
     */
    public function update(Request $request, Schedule $schedule)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can update schedules',
            ], 403);
        }
        $validated = $request->validate([
            'route_id' => 'required|exists:routes,id',
            'bus_plate' => 'required|string',
            'driver_id' => 'required|integer|exists:drivers,id',
            'service_date' => 'nullable|date',
            'departure_time' => 'required|date_format:H:i',
        ]);

        $bus = Bus::where('plate_number', $validated['bus_plate'])->first();
        if (! $bus) {
            return response()->json(['success' => false, 'message' => 'Bus not found.'], 404);
        }

        $busAvailable = BusinessLogicService::validateBusAvailability($bus->id);
        if (! $busAvailable['available']) {
            return response()->json(['success' => false, 'message' => $busAvailable['error']], 422);
        }

        $driver = Driver::find($validated['driver_id']);
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 404);
        }

        $driverAvailable = BusinessLogicService::validateDriverAvailability($driver->id);
        if (! $driverAvailable['available']) {
            return response()->json(['success' => false, 'message' => $driverAvailable['error']], 422);
        }

        $maxDailyHours = (int) SystemSetting::get('driver_max_daily_hours', 10);
        $dailyHoursCheck = BusinessLogicService::checkDriverDailyHours(
            $driver->id,
            $validated['departure_time'],
            $this->resolveRouteTravelDuration($validated['route_id']),
            $maxDailyHours,
            $schedule->id,
            $validated['service_date'] ?? $schedule->service_date ?? now('Asia/Manila')->toDateString()
        );
        if (! $dailyHoursCheck['allowed']) {
            return response()->json(['success' => false, 'message' => $dailyHoursCheck['error']], 422);
        }

        // Use enhanced conflict checking from BusinessLogicService
        $conflictCheck = BusinessLogicService::checkScheduleConflict(
            $bus->id,
            $driver->id,
            $validated['route_id'],
            $validated['departure_time'],
            $schedule->id,
            $validated['service_date'] ?? $schedule->service_date ?? now('Asia/Manila')->toDateString()
        );

        if ($conflictCheck['conflict']) {
            return response()->json([
                'success' => false,
                'message' => $conflictCheck['message'],
            ], 422);
        }

        // Compute estimated arrival time using route duration from the database or settings
        $departure = $validated['departure_time'];
        $duration = $this->resolveRouteTravelDuration($validated['route_id']);

        $timeParts = explode(':', $departure);
        $totalMinutes = intval($timeParts[0]) * 60 + intval($timeParts[1]) + $duration;
        $arrivalHour = floor($totalMinutes / 60) % 24;
        $arrivalMinute = $totalMinutes % 60;
        $arrivalTime = sprintf('%02d:%02d', $arrivalHour, $arrivalMinute);

        $schedule->update([
            'route_id' => $validated['route_id'],
            'service_date' => $validated['service_date'] ?? $schedule->service_date ?? now('Asia/Manila')->toDateString(),
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
                'driverName' => $driver->first_name.' '.$driver->last_name,
                'driverId' => $driver->id,
                'bus' => $bus->plate_number,
                'busId' => $bus->id,
                'pax' => $schedule->passengers,
                'status' => $schedule->status,
            ],
        ]);
    }

    /**
     * Update schedule status (e.g., mark as delayed).
     */
    public function updateStatus(Request $request, Schedule $schedule)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can update schedule status',
            ], 403);
        }
        $validated = $request->validate([
            'status' => 'required|string|in:On time,Delayed,delayed,Cancelled,cancelled',
        ]);

        $oldStatus = $schedule->status;
        $newStatus = match (strtolower($validated['status'])) {
            'delayed' => Schedule::STATUS_DELAYED,
            'cancelled' => Schedule::STATUS_CANCELLED,
            default => Schedule::STATUS_ON_TIME,
        };

        $schedule->update(['status' => $newStatus]);

        // If status changed to delayed, recalculate driver performance score
        if ($newStatus === Schedule::STATUS_DELAYED && $oldStatus !== Schedule::STATUS_DELAYED) {
            DriverPerformanceService::recalculate($schedule->driver_id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Schedule status updated successfully.',
        ]);
    }

    /**
     * Return today's dispatch queue for the overview panel.
     */
    public function getTodayDispatchQueue()
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can view the dispatch queue',
            ], 403);
        }

        $today = Carbon::now('Asia/Manila')->toDateString();

        $dispatches = Schedule::with(['route', 'bus', 'driver'])
            ->whereDate('service_date', $today)
            ->whereNotIn('status', [Schedule::STATUS_CANCELLED, 'cancelled'])
            ->orderBy('departure_time')
            ->get()
            ->map(function (Schedule $schedule) {
                return [
                    'id' => $schedule->id,
                    'routeId' => (string) $schedule->route_id,
                    'routeName' => $schedule->route?->name ?? 'Unassigned',
                    'busId' => $schedule->bus_id,
                    'busPlate' => $schedule->bus?->plate_number ?? 'Unassigned',
                    'driverId' => $schedule->driver_id,
                    'driverName' => $schedule->driver?->name ?? 'Unassigned',
                    'driverInitials' => $schedule->driver?->initials ?? '',
                    'departureTime' => substr($schedule->departure_time, 0, 5),
                    'arrivalTime' => $schedule->arrival_time ? substr($schedule->arrival_time, 0, 5) : null,
                    'status' => $schedule->status,
                ];
            });

        return response()->json([
            'success' => true,
            'date' => $today,
            'dispatches' => $dispatches,
        ]);
    }

    /**
     * Remove the specified schedule.
     */
    public function destroy(Schedule $schedule)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can delete schedules',
            ], 403);
        }
        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Schedule successfully deleted!',
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
            // Use configurable fallback travel time from settings
            return (int) SystemSetting::get('schedule_default_travel_time_minutes', 30);
        }

        return (int) $duration;
    }
}
