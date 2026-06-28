<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\Driver;
use App\Models\Bus;
use App\Models\Route;
use App\Models\SystemSetting;
use Carbon\Carbon;

class ScheduleConflictService
{
    /**
     * Comprehensive schedule conflict check
     * Validates: driver availability, bus availability, rest periods, double-booking
     */
    public static function validateSchedule(
        int $routeId,
        int $busId,
        int $driverId,
        string $departureTime,
        int $durationMinutes,
        ?int $excludeScheduleId = null
    ): array
    {
        $route = Route::find($routeId);
        $bus = Bus::find($busId);
        $driver = Driver::find($driverId);

        if (!$route || !$bus || !$driver) {
            return [
                'valid' => false,
                'message' => 'Invalid route, bus, or driver'
            ];
        }

        // Check 1: Bus availability
        $busCheck = self::checkBusAvailability($busId, $departureTime, $durationMinutes, $excludeScheduleId);
        if (!$busCheck['available']) {
            return [
                'valid' => false,
                'message' => $busCheck['message']
            ];
        }

        // Check 2: Driver availability
        $driverCheck = self::checkDriverAvailability($driverId, $departureTime, $durationMinutes, $excludeScheduleId);
        if (!$driverCheck['available']) {
            return [
                'valid' => false,
                'message' => $driverCheck['message']
            ];
        }

        // Check 3: Driver rest periods
        $restCheck = self::checkDriverRestPeriod($driverId, $departureTime, $excludeScheduleId);
        if (!$restCheck['compliant']) {
            return [
                'valid' => false,
                'message' => $restCheck['message']
            ];
        }

        // Check 4: Driver daily hours limit
        $dailyHoursCheck = self::checkDailyHoursLimit($driverId, $departureTime, $durationMinutes, $excludeScheduleId);
        if (!$dailyHoursCheck['allowed']) {
            return [
                'valid' => false,
                'message' => $dailyHoursCheck['message']
            ];
        }

        // Check 5: Route capability (optional - can expand later)
        $routeCheck = self::checkRouteCapability($route, $bus, $driver);
        if (!$routeCheck['capable']) {
            return [
                'valid' => false,
                'message' => $routeCheck['message']
            ];
        }

        return [
            'valid' => true,
            'message' => 'Schedule is valid - no conflicts detected'
        ];
    }

    /**
     * Check if bus is available during the requested time slot
     * Bus cannot be assigned to multiple routes at same time
     */
    public static function checkBusAvailability(
        int $busId,
        string $departureTime,
        int $durationMinutes,
        ?int $excludeScheduleId = null
    ): array
    {
        $bus = Bus::find($busId);
        if (!$bus) {
            return ['available' => false, 'message' => 'Bus not found'];
        }

        // Bus under maintenance cannot be scheduled
        if ($bus->status === 'maintenance') {
            return ['available' => false, 'message' => "Bus {$bus->plate_number} is under maintenance"];
        }

        // Bus inactive cannot be scheduled
        if ($bus->status === 'inactive') {
            return ['available' => false, 'message' => "Bus {$bus->plate_number} is inactive"];
        }

        // Check time slot conflict with other schedules
        $timeSlotConflict = self::checkTimeSlotConflict(
            $busId,
            $departureTime,
            $durationMinutes,
            $excludeScheduleId,
            'bus'
        );

        if ($timeSlotConflict['conflict']) {
            return [
                'available' => false,
                'message' => "Bus {$bus->plate_number} already scheduled: {$timeSlotConflict['conflict_details']}"
            ];
        }

        return [
            'available' => true,
            'message' => "Bus {$bus->plate_number} is available"
        ];
    }

    /**
     * Check if driver is available during the requested time slot
     * Driver cannot be assigned to multiple routes at same time
     */
    public static function checkDriverAvailability(
        int $driverId,
        string $departureTime,
        int $durationMinutes,
        ?int $excludeScheduleId = null
    ): array
    {
        $driver = Driver::find($driverId);
        if (!$driver) {
            return ['available' => false, 'message' => 'Driver not found'];
        }

        // Driver suspended cannot be scheduled
        if ($driver->status === 'suspended') {
            return [
                'available' => false,
                'message' => "Driver {$driver->first_name} {$driver->last_name} is suspended"
            ];
        }

        // Driver inactive cannot be scheduled
        if ($driver->status === 'inactive') {
            return [
                'available' => false,
                'message' => "Driver {$driver->first_name} {$driver->last_name} is inactive"
            ];
        }

        // Check license expiry
        if ($driver->license_expiry && Carbon::parse($driver->license_expiry)->isPast()) {
            return [
                'available' => false,
                'message' => "Driver {$driver->first_name} {$driver->last_name} license has expired"
            ];
        }

        // Check time slot conflict with other schedules
        $timeSlotConflict = self::checkTimeSlotConflict(
            $driverId,
            $departureTime,
            $durationMinutes,
            $excludeScheduleId,
            'driver'
        );

        if ($timeSlotConflict['conflict']) {
            return [
                'available' => false,
                'message' => "Driver already scheduled: {$timeSlotConflict['conflict_details']}"
            ];
        }

        return [
            'available' => true,
            'message' => "Driver {$driver->first_name} {$driver->last_name} is available"
        ];
    }

    /**
     * Check if schedule violates driver rest period requirements
     * Minimum 8 hours between end of one trip and start of next
     */
    public static function checkDriverRestPeriod(
        int $driverId,
        string $departureTime,
        ?int $excludeScheduleId = null
    ): array
    {
        $minRestHours = (int) SystemSetting::get('driver_min_rest_hours', 8);
        $minRestMinutes = $minRestHours * 60;
        $isTimeOnly = (bool) preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $departureTime);
        $newStartTime = self::parseScheduleDateTime($departureTime);

        // Check every prior schedule with real dates so overnight trips still enforce rest.
        $schedules = Schedule::where('driver_id', $driverId)
            ->where('status', '!=', 'cancelled')
            ->when($excludeScheduleId, function ($q) use ($excludeScheduleId) {
                return $q->where('id', '!=', $excludeScheduleId);
            })
            ->get();

        if ($schedules->isEmpty()) {
            // No previous schedule, rest period OK
            return [
                'compliant' => true,
                'message' => 'No previous schedule - rest period OK'
            ];
        }

        // Estimate new schedule end time using route travel time for cross-day support
        $newDuration = (int) SystemSetting::get('schedule_default_travel_time_minutes', 30);
        $newEndTime = $newStartTime->copy()->addMinutes($newDuration);

        foreach ($schedules as $schedule) {
            $existingStart = self::scheduleStartDateTime($schedule);
            $existingEnd = self::scheduleEndDateTime($schedule, $existingStart);

            $currentNewStart = $newStartTime;
            $currentNewEnd = $newEndTime;

            if ($isTimeOnly) {
                $existingDateStr = $existingStart->toDateString();
                $currentNewStart = Carbon::parse($existingDateStr . ' ' . $departureTime);
                $currentNewEnd = $currentNewStart->copy()->addMinutes($newDuration);
            }

            // Case A: new trip starts AFTER existing trip ends — enforce rest after existing
            if ($currentNewStart->greaterThanOrEqualTo($existingEnd)) {
                $restMinutes = $existingEnd->diffInMinutes($currentNewStart);
                if ($restMinutes < $minRestMinutes) {
                    $restHours = round($restMinutes / 60, 1);
                    return [
                        'compliant' => false,
                        'message' => "Driver needs {$minRestHours}h rest after previous trip (currently: {$restHours}h)"
                    ];
                }
            }

            // Case B: new trip ends BEFORE existing trip starts — enforce rest before existing
            if ($currentNewEnd->lessThanOrEqualTo($existingStart)) {
                $restMinutes = $currentNewEnd->diffInMinutes($existingStart);
                if ($restMinutes < $minRestMinutes) {
                    $restHours = round($restMinutes / 60, 1);
                    return [
                        'compliant' => false,
                        'message' => "Driver needs {$minRestHours}h rest before next scheduled trip (currently: {$restHours}h)"
                    ];
                }
            }
        }

        return [
            'compliant' => true,
            'message' => 'Rest period OK'
        ];
    }

    /**
     * Check if driver exceeds maximum daily working hours
     * Default 10 hours per day (configurable)
     */
    public static function checkDailyHoursLimit(
        int $driverId,
        string $departureTime,
        int $newTripDurationMinutes,
        ?int $excludeScheduleId = null
    ): array
    {
        $maxDailyHours = (int) SystemSetting::get('driver_max_daily_hours', 10);
        $departureDay = Carbon::parse($departureTime)->toDateString();

        // Get all schedules for driver on this day
        $daySchedules = Schedule::where('driver_id', $driverId)
            ->whereDate('departure_time', $departureDay)
            ->where('status', '!=', 'cancelled')
            ->when($excludeScheduleId, function ($q) use ($excludeScheduleId) {
                return $q->where('id', '!=', $excludeScheduleId);
            })
            ->get();

        // Calculate total hours already scheduled
        $totalMinutes = 0;
        foreach ($daySchedules as $schedule) {
            $depTime = Carbon::parse($schedule->departure_time);
            $arrTime = Carbon::parse($schedule->arrival_time);
            $minutes = $depTime->diffInMinutes($arrTime);
            $totalMinutes += $minutes;
        }

        // Add new trip duration
        $totalMinutes += $newTripDurationMinutes;
        $totalHours = $totalMinutes / 60;

        if ($totalHours > $maxDailyHours) {
            $exceededBy = round($totalHours - $maxDailyHours, 1);
            return [
                'allowed' => false,
                'message' => "Adding this trip would exceed daily limit: {$totalHours}h > {$maxDailyHours}h (exceeds by {$exceededBy}h)"
            ];
        }

        return [
            'allowed' => true,
            'message' => "Daily hours OK: {$totalHours}h / {$maxDailyHours}h"
        ];
    }

    /**
     * Check time slot overlap between schedules
     * Allows configurable buffer time (default 15 minutes)
     */
    protected static function checkTimeSlotConflict(
        int $entityId,
        string $departureTime,
        int $durationMinutes,
        ?int $excludeScheduleId,
        string $entityType = 'bus' // 'bus' or 'driver'
    ): array
    {
        $bufferMinutes = (int) SystemSetting::get('schedule_buffer_minutes', 15);

        $newStart = self::parseScheduleDateTime($departureTime);
        $newEnd = $newStart->copy()->addMinutes($durationMinutes);

        // Find conflicting schedules
        $query = Schedule::query();

        if ($entityType === 'bus') {
            $query->where('bus_id', $entityId);
        } else {
            $query->where('driver_id', $entityId);
        }

        $query->whereNotIn('status', ['cancelled', Schedule::STATUS_CANCELLED])
            ->when($excludeScheduleId, function ($q) use ($excludeScheduleId) {
                return $q->where('id', '!=', $excludeScheduleId);
            });

        $existingSchedules = $query->get();

        foreach ($existingSchedules as $existing) {
            $existingStart = self::scheduleStartDateTime($existing);
            $existingEnd = self::scheduleEndDateTime($existing, $existingStart);

            // Check for overlap with buffer
            if ($newStart->lessThan($existingEnd->copy()->addMinutes($bufferMinutes)) &&
                $existingStart->lessThan($newEnd->copy()->addMinutes($bufferMinutes))) {
                
                $routeName = $existing->route->name ?? $existing->route_id;
                return [
                    'conflict' => true,
                    'conflict_details' => "Route {$routeName} at " .
                        substr($existing->departure_time, 0, 5) . '-' .
                        substr($existing->arrival_time, 0, 5)
                ];
            }
        }

        return ['conflict' => false];
    }

    /**
     * Check if bus/driver combination is suitable for route
     * Can extend with certifications, equipment requirements, etc.
     */
    protected static function checkRouteCapability(Route $route, Bus $bus, Driver $driver): array
    {
        // Check 1: Bus capacity vs route requirements
        $minimumCapacity = (int) ($route->min_capacity ?? SystemSetting::get('route_min_capacity_default', 30));
        if ($bus->capacity < $minimumCapacity) {
            return [
                'capable' => false,
                'message' => "Bus capacity ({$bus->capacity}) below route minimum ({$minimumCapacity})"
            ];
        }

        // Check 2: Driver assigned to route (optional - can be enforced or relaxed)
        // For now, allow any driver on any route

        return [
            'capable' => true,
            'message' => 'Bus and driver are suitable for this route'
        ];
    }

    protected static function parseScheduleDateTime(string $time): Carbon
    {
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time)) {
            // Use Carbon::today to respect the current default timezone (or test now)
            return Carbon::today()->setTimeFromTimeString($time);
        }

        return Carbon::parse($time);
    }

    protected static function scheduleStartDateTime(Schedule $schedule): Carbon
    {
        $date = $schedule->service_date
            ? Carbon::parse($schedule->service_date)->toDateString()
            : Carbon::today()->toDateString();

        return Carbon::parse($date . ' ' . substr($schedule->departure_time, 0, 8));
    }

    protected static function scheduleEndDateTime(Schedule $schedule, ?Carbon $start = null): Carbon
    {
        $start = $start ?: self::scheduleStartDateTime($schedule);
        $end = Carbon::parse($start->toDateString() . ' ' . substr($schedule->arrival_time, 0, 8));

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $end;
    }

    /**
     * Get conflicts for a specific driver on a specific day
     * Useful for dashboard visualization
     */
    public static function getDriverConflictsForDay(int $driverId, string $date): array
    {
        $conflicts = [];

        $schedules = Schedule::where('driver_id', $driverId)
            ->whereDate('departure_time', $date)
            ->where('status', '!=', 'cancelled')
            ->orderBy('departure_time')
            ->get();

        $bufferMinutes = (int) SystemSetting::get('schedule_buffer_minutes', 15);

        for ($i = 0; $i < count($schedules) - 1; $i++) {
            $current = $schedules[$i];
            $next = $schedules[$i + 1];

            $currentArrParts = explode(':', $current->arrival_time);
            $currentArrMinutes = intval($currentArrParts[0]) * 60 + intval($currentArrParts[1]);

            $nextDepParts = explode(':', $next->departure_time);
            $nextDepMinutes = intval($nextDepParts[0]) * 60 + intval($nextDepParts[1]);

            $gapMinutes = $nextDepMinutes - $currentArrMinutes;

            if ($gapMinutes < $bufferMinutes) {
                $currentRouteName = $current->route->name ?? $current->route_id;
                $nextRouteName = $next->route->name ?? $next->route_id;
                $conflicts[] = [
                    'type' => 'insufficient_buffer',
                    'schedule_1' => $current->id,
                    'schedule_2' => $next->id,
                    'current_route' => $currentRouteName,
                    'next_route' => $nextRouteName,
                    'current_end' => $current->arrival_time,
                    'next_start' => $next->departure_time,
                    'gap_minutes' => $gapMinutes,
                    'required_minutes' => $bufferMinutes
                ];
            }
        }

        return $conflicts;
    }
}
