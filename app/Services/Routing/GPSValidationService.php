<?php

namespace App\Services\Routing;

use App\Models\GPSLog;
use App\Services\Contracts\GeospatialServiceInterface;
use App\Services\ValueObjects\Coordinate;
use Illuminate\Support\Facades\Log;

class GPSValidationService
{
    private GeospatialServiceInterface $geospatial;

    public function __construct(GeospatialServiceInterface $geospatial)
    {
        $this->geospatial = $geospatial;
    }

    /**
     * Validate GPS data payload against previous successful telemetry.
     * Diagnostic logging is intentionally side-effect free; validation behavior is unchanged.
     */
    public function validate(array $data, ?GPSLog $lastLog = null): void
    {
        $lat = (float) ($data['lat'] ?? 0.0);
        $lng = (float) ($data['lng'] ?? 0.0);
        $accuracy = isset($data['accuracy']) ? (float) $data['accuracy'] : null;
        $timestamp = isset($data['timestamp']) ? \Carbon\Carbon::parse($data['timestamp']) : null;

        if ($lat === 0.0 || $lng === 0.0) {
            $this->reject('ZERO_COORDINATES', 'Invalid coordinate values (lat/lng cannot be zero).', $data, $lastLog);
        }

        $maxAccuracy = (float) config('fleet.gps.max_accuracy_meters', 50.0);
        if ($accuracy !== null && $accuracy > $maxAccuracy) {
            $this->reject('ACCURACY_TOO_HIGH', "GPS signal accuracy ({$accuracy}m) exceeds limit of {$maxAccuracy}m.", $data, $lastLog);
        }

        if (!$timestamp) {
            $this->reject('MISSING_TIMESTAMP', 'Timestamp is required.', $data, $lastLog);
        }

        if ($timestamp->isAfter(now()->addMinutes(1))) {
            $this->reject('FUTURE_TIMESTAMP', 'GPS timestamp cannot be in the future.', $data, $lastLog);
        }

        if ($lastLog) {
            $lastTime = \Carbon\Carbon::parse($lastLog->timestamp);
            $elapsedSeconds = abs($timestamp->diffInSeconds($lastTime, false));

            $sameCoordinates = abs($lat - $lastLog->lat) < 0.0000001
                && abs($lng - $lastLog->lng) < 0.0000001;

            if ($timestamp->eq($lastTime) && $sameCoordinates) {
                $this->reject('DUPLICATE_COORDINATES', 'Duplicate GPS packet (coordinates and timestamp match).', $data, $lastLog, 0.0, 0.0, 0.0);
            }

            if ($timestamp->lessThanOrEqualTo($lastTime)) {
                $this->reject('OUT_OF_ORDER_TIMESTAMP', 'Out-of-order or duplicate GPS timestamp.', $data, $lastLog, null, $elapsedSeconds);
            }

            $c1 = new Coordinate($lastLog->lat, $lastLog->lng);
            $c2 = new Coordinate($lat, $lng);
            $distanceMeters = $this->geospatial->calculateDistance($c1, $c2);

            if ($elapsedSeconds > 0) {
                $impliedSpeedMps = $distanceMeters / $elapsedSeconds;
                $maxSpeedMps = 33.33;

                if ($impliedSpeedMps > $maxSpeedMps && $distanceMeters > 50.0) {
                    $this->reject(
                        'IMPOSSIBLE_JUMP',
                        'Impossible GPS position jump (implied speed: ' . round($impliedSpeedMps * 3.6, 1) . ' km/h).',
                        $data,
                        $lastLog,
                        $distanceMeters,
                        $elapsedSeconds,
                        $impliedSpeedMps
                    );
                }
            }
        }
    }

    private function reject(string $rule, string $reason, array $data, ?GPSLog $lastLog = null, ?float $distance = null, ?float $elapsed = null, ?float $impliedSpeed = null): never
    {
        Log::warning('[GPS_DIAGNOSTIC] GPS validation rejected', [
            'gps_log_id' => $data['gps_log_id'] ?? null,
            'trip_id' => $data['trip_id'] ?? null,
            'bus_id' => $data['bus_id'] ?? null,
            'current_lat' => $data['lat'] ?? null,
            'current_lng' => $data['lng'] ?? null,
            'previous_reference_log_id' => $lastLog?->id,
            'previous_lat' => $lastLog?->lat,
            'previous_lng' => $lastLog?->lng,
            'current_timestamp' => isset($data['timestamp']) ? (string) $data['timestamp'] : null,
            'previous_timestamp' => $lastLog?->timestamp?->toIso8601String(),
            'elapsed_seconds' => $elapsed,
            'calculated_distance_meters' => $distance,
            'implied_speed_mps' => $impliedSpeed,
            'reported_speed' => $data['speed'] ?? null,
            'accuracy' => $data['accuracy'] ?? null,
            'validation_rule_failed' => $rule,
            'rejection_reason' => $reason,
        ]);

        throw new \InvalidArgumentException($reason);
    }
}



