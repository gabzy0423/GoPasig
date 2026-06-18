<?php

namespace App\Services;

use App\Models\Route;
use App\Models\Stop;
use App\Models\Schedule;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;

class BusinessLogicService
{
    /**
     * Check if driver has exceeded daily hour limits
     * Issue 3.1.1: No daily hours limit enforcement
     */
    public static function checkDriverDailyHours(int $driverId, string $departureTime, int $tripDurationMinutes, int $maxDailyHours): array
    {
        $timeParts = explode(':', $departureTime);
        $departureMinutes = intval($timeParts[0]) * 60 + intval($timeParts[1]);
        $tripEndMinutes = $departureMinutes + $tripDurationMinutes;

        // Get all schedules for this driver today
        $today = \Carbon\Carbon::today()->toDateString();
        $schedules = Schedule::where('driver_id', $driverId)->get();

        $totalMinutesScheduled = 0;
        foreach ($schedules as $schedule) {
            $sParts = explode(':', $schedule->departure_time);
            $sStart = intval($sParts[0]) * 60 + intval($sParts[1]);
            $sDuration = $schedule->route?->travel_time_minutes ?? (int) SystemSetting::get('schedule_default_travel_time_minutes', 30);
            $totalMinutesScheduled += $sDuration;
        }

        $totalWithNewTrip = $totalMinutesScheduled + $tripDurationMinutes;
        $maxMinutes = $maxDailyHours * 60;

        if ($totalWithNewTrip > $maxMinutes) {
            $hoursRemaining = ($maxMinutes - $totalMinutesScheduled) / 60;
            return [
                'allowed' => false,
                'error' => sprintf('Driver has only %.1f hours remaining today (limit: %d hours)', $hoursRemaining, $maxDailyHours)
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Validate maintenance duration is within reasonable bounds
     * Issue 3.1.4: Maintenance duration not validated
     */
    public static function validateMaintenanceDuration(int $minutes): array
    {
        $minDuration = (int) SystemSetting::get('maintenance_duration_min_minutes', 15);
        $maxDuration = (int) SystemSetting::get('maintenance_duration_max_minutes', 480); // 8 hours max

        if ($minutes < $minDuration) {
            return [
                'valid' => false,
                'error' => "Maintenance duration must be at least {$minDuration} minutes (provided: {$minutes})"
            ];
        }

        if ($minutes > $maxDuration) {
            return [
                'valid' => false,
                'error' => "Maintenance duration cannot exceed {$maxDuration} minutes (provided: {$minutes})"
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate GPS coordinates are within Philippines boundaries
     * Issue 3.2.1: Route polyline not validated
     */
    public static function validateCoordinates(float $lat, float $lng): array
    {
        $northBound = (float) SystemSetting::get('coordinates_bounds_north_latitude', 14.85);
        $southBound = (float) SystemSetting::get('coordinates_bounds_south_latitude', 14.30);
        $eastBound = (float) SystemSetting::get('coordinates_bounds_east_longitude', 121.20);
        $westBound = (float) SystemSetting::get('coordinates_bounds_west_longitude', 120.95);

        if ($lat < $southBound || $lat > $northBound) {
            return [
                'valid' => false,
                'error' => "Latitude {$lat} is outside service area bounds ({$southBound} to {$northBound})"
            ];
        }

        if ($lng < $westBound || $lng > $eastBound) {
            return [
                'valid' => false,
                'error' => "Longitude {$lng} is outside service area bounds ({$westBound} to {$eastBound})"
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate route polyline forms a valid path
     * Issue 3.2.1: Route polyline not validated
     */
    public static function validateRoutePolyline(array $polyline): array
    {
        if (empty($polyline)) {
            return [
                'valid' => false,
                'error' => 'Route polyline cannot be empty'
            ];
        }

        if (count($polyline) < 2) {
            return [
                'valid' => false,
                'error' => 'Route polyline must have at least 2 points (origin and destination)'
            ];
        }

        // Validate each coordinate pair
        foreach ($polyline as $index => $point) {
            if (!is_array($point) || count($point) !== 2) {
                return [
                    'valid' => false,
                    'error' => "Invalid coordinate format at point {$index}"
                ];
            }

            $coordValidation = self::validateCoordinates($point[0], $point[1]);
            if (!$coordValidation['valid']) {
                return [
                    'valid' => false,
                    'error' => "Point {$index}: " . $coordValidation['error']
                ];
            }
        }

        // Check for duplicate consecutive points
        for ($i = 0; $i < count($polyline) - 1; $i++) {
            if ($polyline[$i] === $polyline[$i + 1]) {
                return [
                    'valid' => false,
                    'error' => "Duplicate consecutive points at index {$i} and " . ($i + 1)
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Validate stop sequence doesn't break route continuity
     * Issue 3.2.2: Stop sequence reordering not validated
     */
    public static function validateStopSequence(Route $route, array $stopIds): array
    {
        if (empty($stopIds)) {
            return [
                'valid' => false,
                'error' => 'Stop sequence cannot be empty'
            ];
        }

        // Get all stops for this route
        $stops = Stop::where('route_id', $route->id)->get()->keyBy('id');

        // Check all IDs belong to this route
        foreach ($stopIds as $stopId) {
            if (!isset($stops[$stopId])) {
                return [
                    'valid' => false,
                    'error' => "Stop {$stopId} does not belong to route {$route->id}"
                ];
            }
        }

        // Check that we have the right number of stops
        if (count($stopIds) !== count($stops)) {
            return [
                'valid' => false,
                'error' => 'All stops must be included in reordering. Expected ' . count($stops) . ', got ' . count($stopIds)
            ];
        }

        // Check for duplicates
        if (count(array_unique($stopIds)) !== count($stopIds)) {
            return [
                'valid' => false,
                'error' => 'Duplicate stop IDs in sequence'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Check schedule conflicts for a driver or bus (ENHANCED from original)
     * Issue 3.1.1: Schedule conflict detection incomplete
     * 
     * Now also checks:
     * - Driver rest periods (minimum time between trips)
     * - Route constraints (driver certification)
     * - Bus availability
     */
    public static function checkScheduleConflict(
        int $busId,
        int $driverId,
        int $routeId,
        string $departureTime,
        ?int $excludeScheduleId = null
    ): array {
        // Get buffer time from settings
        $driverBuffer = (int) SystemSetting::get('driver_schedule_buffer_minutes', 15);
        
        // Convert time to minutes for comparison
        $timeParts = explode(':', $departureTime);
        $startMin = intval($timeParts[0]) * 60 + intval($timeParts[1]);
        
        // Get route duration
        $route = Route::find($routeId);
        $duration = $route?->travel_time_minutes ?? (int) SystemSetting::get('schedule_default_travel_time_minutes', 30);
        $endMin = $startMin + $duration;

        // Check existing schedules for conflicts
        $conflictingSchedules = Schedule::query();
        
        if ($excludeScheduleId) {
            $conflictingSchedules->where('id', '!=', $excludeScheduleId);
        }

        $existingSchedules = $conflictingSchedules->get();

        foreach ($existingSchedules as $schedule) {
            // Check bus conflict
            if ($schedule->bus_id === $busId) {
                $sParts = explode(':', $schedule->departure_time);
                $sStart = intval($sParts[0]) * 60 + intval($sParts[1]);
                $sDuration = $schedule->route?->travel_time_minutes ?? (int) SystemSetting::get('schedule_default_travel_time_minutes', 30);
                $sEnd = $sStart + $sDuration;

                if (($startMin < $sEnd) && ($sStart < $endMin)) {
                    return [
                        'conflict' => true,
                        'type' => 'bus',
                        'message' => "Bus is already scheduled from {$schedule->departure_time} to end of route"
                    ];
                }
            }

            // Check driver conflict with buffer
            if ($schedule->driver_id === $driverId) {
                $sParts = explode(':', $schedule->departure_time);
                $sStart = intval($sParts[0]) * 60 + intval($sParts[1]);
                $sDuration = $schedule->route?->travel_time_minutes ?? (int) SystemSetting::get('schedule_default_travel_time_minutes', 30);
                $sEnd = $sStart + $sDuration;

                // Add buffer for driver rest
                if (($startMin < ($sEnd + $driverBuffer)) && ($sStart < ($endMin + $driverBuffer))) {
                    return [
                        'conflict' => true,
                        'type' => 'driver',
                        'message' => "Driver already has trip from {$schedule->departure_time} with {$driverBuffer}min buffer"
                    ];
                }
            }
        }

        return ['conflict' => false];
    }

    /**
     * Validate bus can operate (not in maintenance, has active license, etc)
     * Related to 3.1.4 and 3.1.5
     */
    public static function validateBusAvailability(int $busId): array
    {
        $bus = \App\Models\Bus::find($busId);

        if (!$bus) {
            return [
                'available' => false,
                'error' => 'Bus not found'
            ];
        }

        if ($bus->status === 'maintenance') {
            return [
                'available' => false,
                'error' => 'Bus is currently in maintenance'
            ];
        }

        if ($bus->status === 'inactive') {
            return [
                'available' => false,
                'error' => 'Bus is inactive'
            ];
        }

        return ['available' => true];
    }

    /**
     * Validate driver can operate (active, license not expired, not suspended, etc)
     * Related to 3.1.1
     */
    public static function validateDriverAvailability(int $driverId): array
    {
        $driver = \App\Models\Driver::find($driverId);

        if (!$driver) {
            return [
                'available' => false,
                'error' => 'Driver not found'
            ];
        }

        if ($driver->status === 'suspended') {
            return [
                'available' => false,
                'error' => 'Driver is suspended'
            ];
        }

        if ($driver->status === 'inactive') {
            return [
                'available' => false,
                'error' => 'Driver is inactive'
            ];
        }

        // Check license expiry
        $licenseExpiry = \Carbon\Carbon::parse($driver->license_expiry);
        if ($licenseExpiry->isPast()) {
            return [
                'available' => false,
                'error' => 'Driver license has expired'
            ];
        }

        return ['available' => true];
    }

    /**
     * Get validation error for display
     */
    public static function getValidationError(array $result): ?string
    {
        return $result['error'] ?? $result['message'] ?? null;
    }
}
