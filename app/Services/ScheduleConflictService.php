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

        // Get driver's last completed schedule of the day
        $lastSchedule = Schedule::where('driver_id', $driverId)
            ->where('status', '!=', 'cancelled')
            ->when($excludeScheduleId, function ($q) use ($excludeScheduleId) {
                return $q->where('id', '!=', $excludeScheduleId);
            })
            ->orderBy('departure_time', 'desc')
            ->first();

        if (!$lastSchedule) {
            // No previous schedule, rest period OK
            return [
                'compliant' => true,
                'message' => 'No previous schedule - rest period OK'
            ];
        }

        // Calculate time between end of last schedule and start of new one
        $lastEndTime = Carbon::parse($lastSchedule->arrival_time);
        $newStartTime = Carbon::parse($departureTime);

        // If times are different days, assume overnight rest is OK
        if ($lastEndTime->isToday() === false || $newStartTime->isToday() === false) {
            // Different days - rest period assumed OK
            return [
                'compliant' => true,
                'message' => 'Different days - overnight rest assumed'
            ];
        }

        // Same day - check minimum rest hours
        $restHours = $lastEndTime->diffInHours($newStartTime);

        if ($restHours < $minRestHours) {
            return [
                'compliant' => false,
                'message' => "Driver needs {$minRestHours} hours rest between trips (currently: {$restHours} hours)"
            ];
        }

        return [
            'compliant' => true,
            'message' => "Rest period OK ({$restHours} hours available)"
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

        // Parse new schedule times
        $newDepParts = explode(':', $departureTime);
        $newDepMinutes = intval($newDepParts[0]) * 60 + intval($newDepParts[1]);
        $newArrMinutes = $newDepMinutes + $durationMinutes;

        // Find conflicting schedules
        $query = Schedule::query();

        if ($entityType === 'bus') {
            $query->where('bus_id', $entityId);
        } else {
            $query->where('driver_id', $entityId);
        }

        $query->where('status', '!=', 'cancelled')
            ->when($excludeScheduleId, function ($q) use ($excludeScheduleId) {
                return $q->where('id', '!=', $excludeScheduleId);
            });

        $existingSchedules = $query->get();

        foreach ($existingSchedules as $existing) {
            $existingDepParts = explode(':', $existing->departure_time);
            $existingDepMinutes = intval($existingDepParts[0]) * 60 + intval($existingDepParts[1]);

            $existingArrParts = explode(':', $existing->arrival_time);
            $existingArrMinutes = intval($existingArrParts[0]) * 60 + intval($existingArrParts[1]);

            // Check for overlap with buffer
            if (($newDepMinutes < ($existingArrMinutes + $bufferMinutes)) &&
                ($existingDepMinutes < ($newArrMinutes + $bufferMinutes))) {
                
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
        if ($bus->capacity < ($route->min_capacity ?? 30)) {
            return [
                'capable' => false,
                'message' => "Bus capacity ({$bus->capacity}) below route minimum ({$route->min_capacity})"
            ];
        }

        // Check 2: Driver assigned to route (optional - can be enforced or relaxed)
        // For now, allow any driver on any route

        return [
            'capable' => true,
            'message' => 'Bus and driver are suitable for this route'
        ];
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
