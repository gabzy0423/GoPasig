<?php

namespace App\Services;

use App\Models\GPSLog;
use App\Models\VehiclePosition;
use App\Services\Contracts\GeospatialServiceInterface;
use App\Services\ValueObjects\Coordinate;
use Carbon\CarbonInterface;

class HeadingService
{
    public const SOURCE_NATIVE = 'native';
    public const SOURCE_DERIVED = 'derived';
    public const SOURCE_PRESERVED = 'preserved';
    public const SOURCE_UNAVAILABLE = 'unavailable';

    public function __construct(private GeospatialServiceInterface $geospatial) {}

    public function resolve(GPSLog $log, VehiclePosition $position): array
    {
        $existingHeading = $this->normalizeHeading($position->display_heading);
        $existingUpdatedAt = $position->heading_updated_at;
        $movementState = strtoupper((string) ($position->movement_state ?: MovementClassificationService::STATE_UNKNOWN));
        $gpsQualityState = strtoupper((string) ($position->gps_quality_state ?: GpsQualityService::STATE_UNKNOWN));

        if ($log->is_cached_fix) {
            return $this->preserve($existingHeading, $existingUpdatedAt, 'cached_heartbeat_preserved_heading');
        }

        if ($movementState !== MovementClassificationService::STATE_MOVING) {
            return $this->preserve($existingHeading, $existingUpdatedAt, 'not_moving_preserved_heading');
        }

        if (!in_array($gpsQualityState, [GpsQualityService::STATE_GOOD, GpsQualityService::STATE_DEGRADED], true)) {
            return $this->preserve($existingHeading, $existingUpdatedAt, 'gps_quality_preserved_heading');
        }

        $rawHeading = $this->normalizeHeading($log->heading);
        if ($rawHeading !== null) {
            return $this->result($rawHeading, self::SOURCE_NATIVE, now('UTC'), 'native_heading_accepted');
        }

        $derivedHeading = $this->deriveHeading($log);
        if ($derivedHeading !== null) {
            return $this->result($derivedHeading, self::SOURCE_DERIVED, now('UTC'), 'derived_from_fresh_displacement');
        }

        return $this->preserve($existingHeading, $existingUpdatedAt, 'heading_unavailable_or_displacement_insufficient');
    }

    public function angularDifference(float $from, float $to): float
    {
        return abs(fmod(($to - $from + 540.0), 360.0) - 180.0);
    }

    private function deriveHeading(GPSLog $log): ?float
    {
        $previousFreshLog = GPSLog::where('trip_id', $log->trip_id)
            ->where('processing_status', 'processed')
            ->where('is_cached_fix', false)
            ->where('id', '<', $log->id)
            ->orderByDesc('id')
            ->first();

        if (!$previousFreshLog) {
            return null;
        }

        $current = new Coordinate(
            (float) ($log->filtered_lat ?? $log->lat),
            (float) ($log->filtered_lng ?? $log->lng)
        );
        $previous = new Coordinate(
            (float) ($previousFreshLog->filtered_lat ?? $previousFreshLog->lat),
            (float) ($previousFreshLog->filtered_lng ?? $previousFreshLog->lng)
        );

        $distanceMeters = $this->geospatial->calculateDistance($previous, $current);
        if ($distanceMeters < $this->deriveDisplacementThreshold($log, $previousFreshLog)) {
            return null;
        }

        return $this->normalizeHeading($this->geospatial->calculateBearing($previous, $current));
    }

    private function deriveDisplacementThreshold(GPSLog $current, GPSLog $previous): float
    {
        $minDisplacement = (float) config('fleet.heading.derive_min_displacement_meters', 5.0);
        $accuracyMultiplier = (float) config('fleet.heading.derive_accuracy_noise_multiplier', 0.35);
        $maxReliableAccuracy = (float) config('fleet.heading.max_reliable_accuracy_meters', config('fleet.gps.max_accuracy_meters', 50.0));
        $accuracyRadius = max((float) ($current->accuracy ?? 0.0), (float) ($previous->accuracy ?? 0.0));

        if ($accuracyRadius > $maxReliableAccuracy) {
            return INF;
        }

        return max($minDisplacement, $accuracyRadius * $accuracyMultiplier);
    }

    private function normalizeHeading(mixed $heading): ?float
    {
        if ($heading === null || $heading === '') {
            return null;
        }

        $heading = (float) $heading;
        if ($heading < 0.0 || $heading > 360.0) {
            return null;
        }

        return fmod($heading, 360.0);
    }

    private function preserve(?float $existingHeading, ?CarbonInterface $existingUpdatedAt, string $reason): array
    {
        if ($existingHeading === null) {
            return $this->result(null, self::SOURCE_UNAVAILABLE, null, $reason);
        }

        return $this->result($existingHeading, self::SOURCE_PRESERVED, $existingUpdatedAt, $reason);
    }

    private function result(?float $heading, string $source, ?CarbonInterface $updatedAt, string $reason): array
    {
        return [
            'display_heading' => $heading,
            'heading_source' => $source,
            'heading_updated_at' => $updatedAt,
            'heading_reason' => $reason,
        ];
    }
}
