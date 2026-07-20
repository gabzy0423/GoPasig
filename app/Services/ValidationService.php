<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Models\SystemSetting;

class ValidationService
{
    /**
     * Philippines geographic bounds (main island group)
     * Latitude: 4.6° to 20.9°N
     * Longitude: 116.4° to 127.0°E
     */
    private const PHILIPPINES_LAT_MIN = 4.6;
    private const PHILIPPINES_LAT_MAX = 20.9;
    private const PHILIPPINES_LNG_MIN = 116.4;
    private const PHILIPPINES_LNG_MAX = 127.0;

    /**
     * Validate GPS coordinates are within Philippines bounds
     * @param float $latitude
     * @param float $longitude
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validateGPSCoordinates(float $latitude, float $longitude): array
    {
        // Check for NaN or Infinity
        if (!is_finite($latitude) || !is_finite($longitude)) {
            return [
                'valid' => false,
                'message' => 'GPS coordinates contain invalid values (NaN or Infinity)'
            ];
        }

        $latMin = (float) SystemSetting::get('coordinates_bounds_south_latitude', self::PHILIPPINES_LAT_MIN);
        $latMax = (float) SystemSetting::get('coordinates_bounds_north_latitude', self::PHILIPPINES_LAT_MAX);
        $lngMin = (float) SystemSetting::get('coordinates_bounds_west_longitude', self::PHILIPPINES_LNG_MIN);
        $lngMax = (float) SystemSetting::get('coordinates_bounds_east_longitude', self::PHILIPPINES_LNG_MAX);

        // Check latitude bounds
        if ($latitude < $latMin || $latitude > $latMax) {
            return [
                'valid' => false,
                'message' => "Latitude {$latitude} out of bounds. Range: {$latMin}° to {$latMax}°N"
            ];
        }

        // Check longitude bounds
        if ($longitude < $lngMin || $longitude > $lngMax) {
            return [
                'valid' => false,
                'message' => "Longitude {$longitude} out of bounds. Range: {$lngMin}° to {$lngMax}°E"
            ];
        }

        return [
            'valid' => true,
            'message' => 'GPS coordinates are valid'
        ];
    }

    /**
     * Validate polyline coordinates array
     * Each coordinate should be [lat, lng] within Philippines bounds
     * @param array $coordinates Array of [latitude, longitude] pairs
     * @return array ['valid' => bool, 'message' => string, 'invalid_coords' => array]
     */
    public static function validatePolylineGeometry(array $coordinates): array
    {
        if (empty($coordinates)) {
            return [
                'valid' => false,
                'message' => 'Polyline coordinates cannot be empty',
                'invalid_coords' => []
            ];
        }

        if (count($coordinates) < 2) {
            return [
                'valid' => false,
                'message' => 'Polyline must have at least 2 coordinates',
                'invalid_coords' => []
            ];
        }

        $invalidCoords = [];
        
        foreach ($coordinates as $index => $coord) {
            // Check coordinate format
            if (!is_array($coord) || count($coord) < 2) {
                $invalidCoords[] = [
                    'index' => $index,
                    'reason' => 'Invalid coordinate format (expected [lat, lng])'
                ];
                continue;
            }

            $lat = $coord[0];
            $lng = $coord[1];

            // Check if numeric
            if (!is_numeric($lat) || !is_numeric($lng)) {
                $invalidCoords[] = [
                    'index' => $index,
                    'reason' => 'Coordinates must be numeric'
                ];
                continue;
            }

            // Validate bounds
            if ($lat < self::PHILIPPINES_LAT_MIN || $lat > self::PHILIPPINES_LAT_MAX ||
                $lng < self::PHILIPPINES_LNG_MIN || $lng > self::PHILIPPINES_LNG_MAX) {
                $invalidCoords[] = [
                    'index' => $index,
                    'lat' => $lat,
                    'lng' => $lng,
                    'reason' => 'Coordinates outside Philippines bounds'
                ];
            }

            // Check for NaN/Infinity
            if (!is_finite($lat) || !is_finite($lng)) {
                $invalidCoords[] = [
                    'index' => $index,
                    'reason' => 'Coordinates contain NaN or Infinity'
                ];
            }
        }

        if (!empty($invalidCoords)) {
            return [
                'valid' => false,
                'message' => 'Polyline contains ' . count($invalidCoords) . ' invalid coordinate(s)',
                'invalid_coords' => $invalidCoords
            ];
        }

        // Check for excessive distance jumps between consecutive points (basic geometry validation)
        $maxJumpKm = (float) SystemSetting::get('max_polyline_jump_km', 50.0);
        $invalidJumps = [];

        for ($i = 0; $i < count($coordinates) - 1; $i++) {
            $lat1 = $coordinates[$i][0];
            $lng1 = $coordinates[$i][1];
            $lat2 = $coordinates[$i + 1][0];
            $lng2 = $coordinates[$i + 1][1];

            $distance = self::haversineDistance($lat1, $lng1, $lat2, $lng2);
            
            if ($distance > $maxJumpKm) {
                $invalidJumps[] = [
                    'from_index' => $i,
                    'to_index' => $i + 1,
                    'distance_km' => round($distance, 2),
                    'reason' => "Excessive distance between consecutive points"
                ];
            }
        }

        if (!empty($invalidJumps)) {
            return [
                'valid' => false,
                'message' => 'Polyline has ' . count($invalidJumps) . ' unrealistic geometry jump(s)',
                'invalid_coords' => $invalidJumps
            ];
        }

        return [
            'valid' => true,
            'message' => 'Polyline geometry is valid',
            'invalid_coords' => []
        ];
    }

    /**
     * Calculate distance between two GPS points using Haversine formula
     * Returns distance in kilometers
     */
    private static function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $c1 = new \App\Services\ValueObjects\Coordinate($lat1, $lng1);
        $c2 = new \App\Services\ValueObjects\Coordinate($lat2, $lng2);
        return app(\App\Services\Contracts\GeospatialServiceInterface::class)->calculateDistanceKm($c1, $c2);
    }

    /**
     * Validate schedule time format (HH:MM in 24-hour format)
     * @param string $time
     * @return array ['valid' => bool, 'message' => string, 'time' => string]
     */
    public static function validateScheduleTime(string $time): array
    {
        // Check format HH:MM
        if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            return [
                'valid' => false,
                'message' => 'Time must be in HH:MM format (24-hour)',
                'time' => $time
            ];
        }

        return [
            'valid' => true,
            'message' => 'Time format is valid',
            'time' => $time
        ];
    }

    /**
     * Validate arrival time is after departure time
     * @param string $departureTime HH:MM format
     * @param string $arrivalTime HH:MM format
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validateScheduleTimeRange(string $departureTime, string $arrivalTime): array
    {
        $depCheck = self::validateScheduleTime($departureTime);
        if (!$depCheck['valid']) {
            return [
                'valid' => false,
                'message' => "Invalid departure time: {$depCheck['message']}"
            ];
        }

        $arrCheck = self::validateScheduleTime($arrivalTime);
        if (!$arrCheck['valid']) {
            return [
                'valid' => false,
                'message' => "Invalid arrival time: {$arrCheck['message']}"
            ];
        }

        // Parse times
        $depParts = explode(':', $departureTime);
        $arrParts = explode(':', $arrivalTime);

        $depMinutes = (int)$depParts[0] * 60 + (int)$depParts[1];
        $arrMinutes = (int)$arrParts[0] * 60 + (int)$arrParts[1];

        // Allow overnight trips (arrival time can be less than departure time)
        if ($arrMinutes <= $depMinutes && $arrMinutes !== 0) {
            // If arrival is earlier, assume next day
            $arrMinutes += 24 * 60;
        }

        $duration = $arrMinutes - $depMinutes;

        // Duration must be between 5 minutes and 12 hours
        $minDuration = (int) SystemSetting::get('min_trip_duration_minutes', 5);
        $maxDuration = (int) SystemSetting::get('max_trip_duration_minutes', 720);

        if ($duration < $minDuration) {
            return [
                'valid' => false,
                'message' => "Trip duration too short (minimum {$minDuration} minutes)"
            ];
        }

        if ($duration > $maxDuration) {
            $maxHours = round($maxDuration / 60, 1);
            return [
                'valid' => false,
                'message' => "Trip duration too long (maximum {$maxHours} hours)"
            ];
        }

        return [
            'valid' => true,
            'message' => 'Schedule time range is valid',
            'duration_minutes' => $duration
        ];
    }

    /**
     * Sanitize and validate service alert message
     * Prevents XSS by stripping/escaping dangerous content
     * @param string $message
     * @param int $maxLength Default 500 characters
     * @return array ['valid' => bool, 'message' => string, 'sanitized' => string]
     */
    public static function validateServiceAlertMessage(string $message, int $maxLength = 500): array
    {
        if (empty(trim($message))) {
            return [
                'valid' => false,
                'message' => 'Alert message cannot be empty',
                'sanitized' => ''
            ];
        }

        if (strlen($message) > $maxLength) {
            return [
                'valid' => false,
                'message' => "Alert message exceeds {$maxLength} character limit",
                'sanitized' => ''
            ];
        }

        // Strip dangerous HTML/JavaScript tags and attributes
        $sanitized = self::sanitizeHtml($message);

        // Check if sanitization removed too much (possible attack detected)
        $removedLength = strlen($message) - strlen($sanitized);
        if ($removedLength > strlen($message) * 0.3) { // 30% removed = suspicious
            Log::warning('Potential XSS attack in service alert', [
                'original_length' => strlen($message),
                'sanitized_length' => strlen($sanitized),
                'removed_length' => $removedLength,
                'message' => substr($message, 0, 100)
            ]);
        }

        return [
            'valid' => true,
            'message' => 'Alert message is valid',
            'sanitized' => trim($sanitized)
        ];
    }

    /**
     * Sanitize HTML by removing dangerous tags and attributes
     * Keeps safe tags like <b>, <i>, <br>, but removes script, iframe, onclick, etc.
     */
    private static function sanitizeHtml(string $input): string
    {
        // Remove script tags completely
        $output = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $input);
        
        // Remove iframe tags completely
        $output = preg_replace('/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/i', '', $output);
        
        // Remove on* event attributes (onclick, onload, etc.)
        $output = preg_replace('/\s*on\w+\s*=\s*[\'"][^\'"]*[\'"]/i', '', $output);
        
        // Remove style tags and style attributes with javascript: protocol
        $output = preg_replace('/javascript:/i', '', $output);
        
        // Escape remaining HTML entities for safe display
        $output = htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
        
        return $output;
    }

    /**
     * Validate passenger count
     * @param int $passengers
     * @param int $busCapacity
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validatePassengerCount(int $passengers, int $busCapacity): array
    {
        if ($passengers < 0) {
            return [
                'valid' => false,
                'message' => 'Passenger count cannot be negative'
            ];
        }

        if ($passengers > $busCapacity * 1.5) { // Allow 50% overflow (standing room)
            return [
                'valid' => false,
                'message' => "Passenger count {$passengers} exceeds bus capacity {$busCapacity} significantly"
            ];
        }

        return [
            'valid' => true,
            'message' => 'Passenger count is valid'
        ];
    }

    /**
     * Validate plate number format
     * Philippines format: Example "PAS-825", "MAN-123"
     * @param string $plateNumber
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validatePlateNumber(string $plateNumber): array
    {
        // Format: 3 letters - 3 digits
        if (!preg_match('/^[A-Z]{3}-\d{3,4}$/', strtoupper($plateNumber))) {
            return [
                'valid' => false,
                'message' => 'Plate number must match format: XXX-###'
            ];
        }

        return [
            'valid' => true,
            'message' => 'Plate number is valid'
        ];
    }

    /**
     * Validate stop sequence order (should be 1, 2, 3... or monotonically increasing)
     * @param array $stops Array of stop objects with 'sequence' property
     * @return array ['valid' => bool, 'message' => string, 'gaps' => array]
     */
    public static function validateStopSequence(array $stops): array
    {
        if (empty($stops)) {
            return [
                'valid' => false,
                'message' => 'Route must have at least one stop'
            ];
        }

        $sequences = array_column($stops, 'sequence');
        sort($sequences);

        $gaps = [];
        $expected = 1;

        foreach ($sequences as $seq) {
            if ($seq != $expected) {
                $gaps[] = "Missing sequence {$expected}";
                $expected = $seq + 1;
            } else {
                $expected++;
            }
        }

        if (!empty($gaps)) {
            return [
                'valid' => false,
                'message' => 'Stop sequences are not consecutive',
                'gaps' => $gaps
            ];
        }

        return [
            'valid' => true,
            'message' => 'Stop sequences are valid'
        ];
    }
}
