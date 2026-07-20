<?php

namespace App\Services;

use App\Models\GPSLog;
use App\Models\VehiclePosition;
use Carbon\CarbonInterface;

class GpsQualityService
{
    public const STATE_GOOD = 'GOOD';
    public const STATE_DEGRADED = 'DEGRADED';
    public const STATE_STALE = 'STALE';
    public const STATE_BLOCKED = 'BLOCKED';
    public const STATE_UNKNOWN = 'UNKNOWN';

    public function classify(GPSLog $log, VehiclePosition $position): array
    {
        $currentState = strtoupper((string) ($position->gps_quality_state ?: self::STATE_UNKNOWN));

        if ($currentState === self::STATE_BLOCKED && !$log->gps_fix_timestamp) {
            return $this->result(self::STATE_BLOCKED, 'gps_permission_blocked', null, null);
        }

        if (!$log->gps_fix_timestamp) {
            return $this->result(self::STATE_UNKNOWN, 'missing_gps_fix_timestamp', null, null);
        }

        $fixAgeSeconds = $this->fixAgeSeconds($log);
        $accuracy = $log->accuracy !== null ? max(0.0, (float) $log->accuracy) : null;

        $goodAccuracy = (float) config('fleet.gps_quality.good_accuracy_meters', 20.0);
        $degradedAccuracy = (float) config(
            'fleet.gps_quality.degraded_accuracy_meters',
            config('fleet.gps.max_accuracy_meters', 50.0)
        );
        $degradedFixAge = (int) config('fleet.gps_quality.degraded_fix_age_seconds', 30);
        $staleFixAge = (int) config('fleet.gps_quality.stale_fix_age_seconds', 300);

        if ($fixAgeSeconds !== null && $fixAgeSeconds > $staleFixAge) {
            return $this->result(self::STATE_STALE, 'gps_fix_age_stale', $fixAgeSeconds, $log->gps_fix_timestamp);
        }

        if ($accuracy !== null && $accuracy > $degradedAccuracy) {
            return $this->result(self::STATE_DEGRADED, 'gps_accuracy_above_degraded_threshold', $fixAgeSeconds, $log->gps_fix_timestamp);
        }

        if ($accuracy !== null && $accuracy > $goodAccuracy) {
            return $this->result(self::STATE_DEGRADED, 'gps_accuracy_degraded', $fixAgeSeconds, $log->gps_fix_timestamp);
        }

        if ($fixAgeSeconds !== null && $fixAgeSeconds > $degradedFixAge) {
            return $this->result(self::STATE_DEGRADED, 'gps_fix_age_degraded', $fixAgeSeconds, $log->gps_fix_timestamp);
        }

        return $this->result(self::STATE_GOOD, 'gps_fix_recent_and_accurate', $fixAgeSeconds, $log->gps_fix_timestamp);
    }

    private function fixAgeSeconds(GPSLog $log): ?int
    {
        if ($log->gps_fix_age_ms !== null) {
            return max(0, (int) round($log->gps_fix_age_ms / 1000));
        }

        if (!$log->gps_fix_timestamp || !$log->timestamp) {
            return null;
        }

        return max(0, $log->gps_fix_timestamp->diffInSeconds($log->timestamp));
    }

    private function result(string $state, string $reason, ?int $fixAgeSeconds, ?CarbonInterface $lastGpsFixAt): array
    {
        return [
            'gps_quality_state' => $state,
            'gps_quality_reason' => $reason,
            'gps_quality_updated_at' => now('UTC'),
            'gps_fix_age_seconds' => $fixAgeSeconds,
            'last_gps_fix_at' => $lastGpsFixAt,
        ];
    }
}
