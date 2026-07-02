<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;

class ValidationService
{
    /**
     * Return the configured geographic bounds for coordinate validation.
     * Values are read from SystemSetting so they can be adjusted without
     * touching source code (e.g. tightening to Pasig City only).
     * Falls back to the Pasig-area defaults seeded by the migration.
     *
     * @return array{lat_min: float, lat_max: float, lng_min: float, lng_max: float}
     */
    private static function getBounds(): array
    {
        return [
            'lat_min' => (float) SystemSetting::get('coordinates_bounds_south_latitude', 14.30),
            'lat_max' => (float) SystemSetting::get('coordinates_bounds_north_latitude', 14.85),
            'lng_min' => (float) SystemSetting::get('coordinates_bounds_west_longitude', 120.95),
            'lng_max' => (float) SystemSetting::get('coordinates_bounds_east_longitude', 121.20),
        ];
    }

    /**
     * Validate GPS coordinates are within Philippines bounds
     * @param float $latitude
     * @param float $longitude
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validateGPSCoordinates(float $latitude, float $longitude): array
    {
        $bounds = self::getBounds();

        // Check latitude bounds
        if ($latitude < $bounds['lat_min'] || $latitude > $bounds['lat_max']) {
            return [
                'valid' => false,
                'message' => "Latitude {$latitude} out of bounds. Configured range: " .
                    $bounds['lat_min'] . "° to " . $bounds['lat_max'] . "°N"
            ];
        }

        // Check longitude bounds
        if ($longitude < $bounds['lng_min'] || $longitude > $bounds['lng_max']) {
            return [
                'valid' => false,
                'message' => "Longitude {$longitude} out of bounds. Configured range: " .
                    $bounds['lng_min'] . "° to " . $bounds['lng_max'] . "°E"
            ];
        }

        // Check for NaN or Infinity
        if (!is_finite($latitude) || !is_finite($longitude)) {
            return [
                'valid' => false,
                'message' => 'GPS coordinates contain invalid values (NaN or Infinity)'
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
        $bounds = self::getBounds();

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
            if ($lat < $bounds['lat_min'] || $lat > $bounds['lat_max'] ||
                $lng < $bounds['lng_min'] || $lng > $bounds['lng_max']) {
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
        $maxJumpKm = (float) SystemSetting::get('polyline_max_jump_km', 10); // Configurable; default 10km suits Pasig City scale
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
        $R = 6371; // Earth radius in kilometers
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $R * $c;
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

        // Duration must be within configured bounds
        $minDuration = (int) SystemSetting::get('schedule_min_duration_minutes', 5);
        $maxDuration = (int) SystemSetting::get('schedule_max_duration_minutes', 720);

        if ($duration < $minDuration) {
            return [
                'valid' => false,
                'message' => "Trip duration too short (minimum {$minDuration} minutes)"
            ];
        }

        if ($duration > $maxDuration) {
            return [
                'valid' => false,
                'message' => "Trip duration too long (maximum {$maxDuration} minutes)"
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
