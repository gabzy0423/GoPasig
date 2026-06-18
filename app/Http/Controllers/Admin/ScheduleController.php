<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\SystemSetting;
use App\Services\BusinessLogicService;
use App\Services\ScheduleConflictService;
use App\Services\ValidationService;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function create(Request $request)
    {
        // Get settings from SystemSetting instead of hardcoding
        $defaultTravelTime = (int) SystemSetting::get('schedule_default_travel_time_minutes', 30);
        $scheduleBuffer = (int) SystemSetting::get('driver_schedule_buffer_minutes', 15);
        return redirect('/admin/dashboard#schedules-create');
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
     * Issue 3.1.1: Enhanced with driver and bus availability checks
     */
    public function store(Request $request)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can create schedules'
            ], 403);
        }
        $validated = $request->validate([
            'route_id' => 'required|exists:routes,id',
            'bus_plate' => 'required|string',
            'driver_initials' => 'required|string',
            'departure_time' => 'required|date_format:H:i',
        ]);

        $bus = Bus::where('plate_number', $validated['bus_plate'])->first();
        if (!$bus) {
            return response()->json(['success' => false, 'message' => 'Bus not found.'], 404);
        }

        // NEW: Validate bus availability
        // Issue 3.1.1: Schedule conflict incomplete
        $busAvailable = BusinessLogicService::validateBusAvailability($bus->id);
        if (!$busAvailable['available']) {
            return response()->json(['success' => false, 'message' => $busAvailable['error']], 422);
        }

        // Find driver by initials (database query using first/last name)
        $firstName = substr($validated['driver_initials'], 0, 1);
        $lastName = strlen($validated['driver_initials']) > 1 ? substr($validated['driver_initials'], 1, 1) : '';
        $driver = Driver::where('first_name', 'like', $firstName . '%')
            ->where('last_name', 'like', $lastName . '%')
            ->first();
        // Fallback: search by computed initials (for flexibility)
        if (!$driver && strlen($validated['driver_initials']) === 2) {
            $allDrivers = Driver::all();
            $driver = $allDrivers->first(fn($d) => $d->initials === strtoupper($validated['driver_initials']));
        }
        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 404);
        }

        // NEW: Validate driver availability
        // Issue 3.1.1: Schedule conflict incomplete
        $driverAvailable = BusinessLogicService::validateDriverAvailability($driver->id);
        if (!$driverAvailable['available']) {
            return response()->json(['success' => false, 'message' => $driverAvailable['error']], 422);
        }

        // NEW: Check daily hours limit for driver
        $maxDailyHours = (int) SystemSetting::get('driver_max_daily_hours', 10);
        $dailyHoursCheck = BusinessLogicService::checkDriverDailyHours($driver->id, $validated['departure_time'], $this->resolveRouteTravelDuration($validated['route_id']), $maxDailyHours);
        if (!$dailyHoursCheck['allowed']) {
            return response()->json(['success' => false, 'message' => $dailyHoursCheck['error']], 422);
        }

        // NEW: Use enhanced conflict checking from BusinessLogicService
        // Issue 3.1.1: Schedule conflict incomplete
        $conflictCheck = BusinessLogicService::checkScheduleConflict(
            $bus->id,
            $driver->id,
            $validated['route_id'],
            $validated['departure_time'],
            null
        );

        if ($conflictCheck['conflict']) {
            return response()->json([
                'success' => false,
                'message' => $conflictCheck['message']
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
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can update schedules'
            ], 403);
        }
        $validated = $request->validate([
            'route_id' => 'required|exists:routes,id',
            'bus_plate' => 'required|string',
            'driver_initials' => 'required|string',
            'departure_time' => 'required|date_format:H:i',
        ]);

        $bus = Bus::where('plate_number', $validated['bus_plate'])->first();
        if (!$bus) {
            return response()->json(['success' => false, 'message' => 'Bus not found.'], 404);
        }

        // Find driver by initials (database query using first/last name)
        $firstName = substr($validated['driver_initials'], 0, 1);
        $lastName = strlen($validated['driver_initials']) > 1 ? substr($validated['driver_initials'], 1, 1) : '';
        $driver = Driver::where('first_name', 'like', $firstName . '%')
            ->where('last_name', 'like', $lastName . '%')
            ->first();
        // Fallback: search by computed initials (for flexibility)
        if (!$driver && strlen($validated['driver_initials']) === 2) {
            $allDrivers = Driver::all();
            $driver = $allDrivers->first(fn($d) => $d->initials === strtoupper($validated['driver_initials']));
        }
        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 404);
        }

        $conflictDetails = '';
        if ($this->hasConflict($bus->id, $driver->id, $validated['route_id'], $validated['departure_time'], $schedule->id, $conflictDetails)) {
            return response()->json(['success' => false, 'message' => $conflictDetails], 422);
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
     * Update schedule status (e.g., mark as delayed).
     */
    public function updateStatus(Request $request, Schedule $schedule)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can update schedule status'
            ], 403);
        }
        $validated = $request->validate([
            'status' => 'required|string|in:On time,delayed',
        ]);

        $oldStatus = $schedule->status;
        $newStatus = $validated['status'];

        $schedule->update(['status' => $newStatus]);

        // If status changed to delayed, recalculate driver performance score
        if ($newStatus === 'delayed' && $oldStatus !== 'delayed') {
            \App\Services\DriverPerformanceService::recalculate($schedule->driver_id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Schedule status updated successfully.'
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
                'message' => 'Unauthorized: Only admins can delete schedules'
            ], 403);
        }
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
            // Use configurable fallback travel time from settings
            return (int) SystemSetting::get('default_travel_time_minutes', 30);
        }

        return (int) $duration;
    }

    /**
     * Check if a bus or driver has a schedule conflict/overlap.
     */
    protected function hasConflict(int $busId, int $driverId, int $routeId, string $departureTime, $excludeScheduleId = null, &$conflictDetails = '')
    {
        $duration = $this->resolveRouteTravelDuration($routeId);
        $timeParts = explode(':', $departureTime);
        $startMin = intval($timeParts[0]) * 60 + intval($timeParts[1]);
        $endMin = $startMin + $duration;

        $schedulesQuery = Schedule::with(['route', 'bus', 'driver']);
        if ($excludeScheduleId) {
            $schedulesQuery->where('id', '!=', $excludeScheduleId);
        }
        $schedules = $schedulesQuery->get();

        foreach ($schedules as $s) {
            $isSameDriver = $s->driver_id == $driverId;
            $isSameBus = $s->bus_id == $busId;

            if ($isSameDriver || $isSameBus) {
                $sParts = explode(':', $s->departure_time);
                $sStart = intval($sParts[0]) * 60 + intval($sParts[1]);
                $sDuration = $this->resolveRouteTravelDuration($s->route_id);
                $sEnd = $sStart + $sDuration;

                $buffer = $isSameDriver ? (int) SystemSetting::get('driver_schedule_buffer_minutes', 15) : 0;
                if (($startMin < ($sEnd + $buffer)) && ($sStart < ($endMin + $buffer))) {
                    $entityType = $isSameDriver ? 'Driver' : 'Bus';
                    $entityName = $isSameDriver 
                        ? ($s->driver ? $s->driver->first_name . ' ' . $s->driver->last_name : 'Unassigned')
                        : ($s->bus ? $s->bus->plate_number : 'Unknown Bus');
                    
                    $sEndHour = floor($sEnd / 60) % 24;
                    $sEndMin = $sEnd % 60;
                    $sEndTimeStr = sprintf('%02d:%02d', $sEndHour, $sEndMin);
                    
                    $conflictDetails = sprintf(
                        "%s %s already assigned to Route %s at %s-%s",
                        $entityType,
                        $entityName,
                        $s->route ? $s->route->name : $s->route_id,
                        substr($s->departure_time, 0, 5),
                        $sEndTimeStr
                    );
                    return true;
                }
            }
        }
        return false;
    }
}
