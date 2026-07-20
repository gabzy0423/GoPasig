<?php

namespace App\Services;

use App\Models\GPSLog;
use App\Models\VehiclePosition;
use App\Services\Contracts\GeospatialServiceInterface;
use App\Services\ValueObjects\Coordinate;

class MovementClassificationService
{
    public const STATE_MOVING = 'MOVING';
    public const STATE_STATIONARY = 'STATIONARY';
    public const STATE_UNKNOWN = 'UNKNOWN';

    public function __construct(private GeospatialServiceInterface $geospatial) {}

    public function classify(GPSLog $log, VehiclePosition $position): array
    {
        $currentState = $position->movement_state ?: self::STATE_UNKNOWN;
        $positiveSamples = (int) ($position->movement_positive_samples ?? 0);
        $negativeSamples = (int) ($position->movement_negative_samples ?? 0);

        if ($log->is_cached_fix) {
            return [
                'movement_state' => $currentState,
                'movement_confidence' => $position->movement_confidence,
                'movement_reason' => 'cached_heartbeat_no_new_evidence',
                'movement_state_updated_at' => $position->movement_state_updated_at,
                'movement_positive_samples' => $positiveSamples,
                'movement_negative_samples' => $negativeSamples,
                'changed' => false,
            ];
        }

        $gpsQualityState = strtoupper((string) ($position->gps_quality_state ?: GpsQualityService::STATE_UNKNOWN));
        if (in_array($gpsQualityState, [GpsQualityService::STATE_STALE, GpsQualityService::STATE_BLOCKED], true)) {
            return [
                'movement_state' => $currentState,
                'movement_confidence' => $position->movement_confidence,
                'movement_reason' => strtolower($gpsQualityState) . '_gps_quality_no_new_movement_evidence',
                'movement_state_updated_at' => $position->movement_state_updated_at,
                'movement_positive_samples' => $positiveSamples,
                'movement_negative_samples' => $negativeSamples,
                'changed' => false,
                'evidence' => [
                    'evidence_type' => 'INSUFFICIENT',
                    'gps_quality_state' => $gpsQualityState,
                ],
            ];
        }

        $previousFreshLog = GPSLog::where('trip_id', $log->trip_id)
            ->where('processing_status', 'processed')
            ->where('is_cached_fix', false)
            ->where('id', '<', $log->id)
            ->orderByDesc('id')
            ->first();

        $evidence = $this->evaluateFreshEvidence($log, $previousFreshLog);
        $evidence['gps_quality_state'] = $gpsQualityState;
        if ($gpsQualityState === GpsQualityService::STATE_DEGRADED) {
            $evidence['confidence'] = min($evidence['confidence'], 0.6);
        }

        if ($evidence['movement_positive']) {
            $positiveSamples++;
            $negativeSamples = 0;
        } elseif ($evidence['movement_negative']) {
            $negativeSamples++;
            $positiveSamples = 0;
        }

        $movingConfirmSamples = max(1, (int) config('fleet.movement.moving_confirm_samples', 2));
        $stationaryConfirmSamples = max(1, (int) config('fleet.movement.stationary_confirm_samples', 3));

        $newState = $currentState;
        $reason = $evidence['reason'];
        $confidence = $evidence['confidence'];

        if ($evidence['movement_positive'] && $positiveSamples >= $movingConfirmSamples) {
            $newState = self::STATE_MOVING;
            $reason = $evidence['speed_positive']
                ? 'speed_and_displacement_confirmed'
                : 'displacement_confirmed';
            $confidence = min(1.0, max($confidence, $positiveSamples / $movingConfirmSamples));
        } elseif ($evidence['movement_negative'] && $negativeSamples >= $stationaryConfirmSamples) {
            $newState = self::STATE_STATIONARY;
            $reason = 'repeated_low_displacement';
            $confidence = min(1.0, max($confidence, $negativeSamples / $stationaryConfirmSamples));
        } elseif (!$currentState || $currentState === self::STATE_UNKNOWN) {
            $newState = self::STATE_UNKNOWN;
        }

        if ($gpsQualityState === GpsQualityService::STATE_DEGRADED) {
            $confidence = min($confidence, 0.6);
        }

        $changed = $newState !== $currentState;

        return [
            'movement_state' => $newState,
            'movement_confidence' => round($confidence, 3),
            'movement_reason' => $reason,
            'movement_state_updated_at' => $changed ? now('UTC') : $position->movement_state_updated_at,
            'movement_positive_samples' => $positiveSamples,
            'movement_negative_samples' => $negativeSamples,
            'changed' => $changed,
            'evidence' => $evidence,
        ];
    }

    private function evaluateFreshEvidence(GPSLog $log, ?GPSLog $previousFreshLog): array
    {
        $speedMps = max(0.0, (float) ($log->speed ?? 0.0));
        $accuracy = $log->accuracy !== null ? max(0.0, (float) $log->accuracy) : null;
        $movingSpeedThreshold = (float) config('fleet.movement.moving_speed_threshold_mps', 0.5);
        $sustainedSpeedThreshold = (float) config('fleet.movement.sustained_speed_threshold_mps', 1.0);
        $speedEvidenceMinDisplacement = (float) config('fleet.movement.speed_evidence_min_displacement_meters', 2.0);
        $stationarySpeedThreshold = (float) config('fleet.movement.stationary_speed_threshold_mps', 0.3);
        $maxReliableAccuracy = (float) config('fleet.movement.max_reliable_accuracy_meters', 50.0);

        $speedPositive = $speedMps >= $movingSpeedThreshold;
        $speedLow = $speedMps <= $stationarySpeedThreshold;
        $distanceMeters = null;
        $elapsedSeconds = null;
        $impliedSpeedMps = null;
        $meaningfulDisplacement = false;
        $displacementThreshold = $this->displacementThreshold($accuracy, $previousFreshLog?->accuracy);

        if ($previousFreshLog) {
            $distanceMeters = $this->geospatial->calculateDistance(
                new Coordinate((float) $previousFreshLog->lat, (float) $previousFreshLog->lng),
                new Coordinate((float) $log->lat, (float) $log->lng)
            );

            if ($log->timestamp && $previousFreshLog->timestamp) {
                $elapsedSeconds = abs($log->timestamp->diffInSeconds($previousFreshLog->timestamp, false));
                if ($elapsedSeconds > 0) {
                    $impliedSpeedMps = $distanceMeters / $elapsedSeconds;
                }
            }

            $meaningfulDisplacement = $distanceMeters >= $displacementThreshold;
        }

        $accuracyDegraded = $accuracy !== null && $accuracy > $maxReliableAccuracy;
        $impliedSpeedPositive = $impliedSpeedMps !== null && $impliedSpeedMps >= $movingSpeedThreshold;
        $strongDisplacementEvidence = $meaningfulDisplacement && ($speedPositive || $impliedSpeedPositive) && !$accuracyDegraded;
        $sustainedSpeedEvidence = $previousFreshLog
            && !$accuracyDegraded
            && $speedMps >= $sustainedSpeedThreshold
            && $distanceMeters !== null
            && $distanceMeters >= $speedEvidenceMinDisplacement;
        $movementPositive = $strongDisplacementEvidence || $sustainedSpeedEvidence;
        $movementNegative = !$movementPositive && (!$previousFreshLog || !$meaningfulDisplacement) && ($speedLow || !$speedPositive);
        $evidenceType = $movementPositive ? 'POSITIVE' : ($movementNegative ? 'NEGATIVE' : 'INSUFFICIENT');

        $reason = match (true) {
            $accuracyDegraded && !$meaningfulDisplacement => 'poor_accuracy_low_displacement',
            !$previousFreshLog => 'first_fresh_fix_insufficient_history',
            $sustainedSpeedEvidence && !$strongDisplacementEvidence => 'fresh_sustained_speed_positive',
            $movementPositive && $speedPositive => 'fresh_speed_and_displacement_positive',
            $movementPositive => 'fresh_displacement_positive',
            $speedPositive && !$meaningfulDisplacement => 'speed_without_meaningful_displacement',
            $movementNegative => 'fresh_low_speed_low_displacement',
            default => 'fresh_evidence_inconclusive',
        };

        $confidence = match (true) {
            $movementPositive => 0.75,
            $movementNegative => 0.65,
            $accuracyDegraded => 0.25,
            default => 0.4,
        };

        return [
            'movement_positive' => $movementPositive,
            'movement_negative' => $movementNegative,
            'speed_positive' => $speedPositive,
            'speed_low' => $speedLow,
            'meaningful_displacement' => $meaningfulDisplacement,
            'strong_displacement_evidence' => $strongDisplacementEvidence,
            'sustained_speed_evidence' => $sustainedSpeedEvidence,
            'accuracy_degraded' => $accuracyDegraded,
            'distance_meters' => $distanceMeters,
            'elapsed_seconds' => $elapsedSeconds,
            'implied_speed_mps' => $impliedSpeedMps,
            'displacement_threshold_meters' => $displacementThreshold,
            'sustained_speed_threshold_mps' => $sustainedSpeedThreshold,
            'speed_evidence_min_displacement_meters' => $speedEvidenceMinDisplacement,
            'evidence_type' => $evidenceType,
            'reason' => $reason,
            'confidence' => $confidence,
        ];
    }

    private function displacementThreshold(?float $currentAccuracy, ?float $previousAccuracy): float
    {
        $minDisplacement = (float) config('fleet.movement.min_displacement_meters', 8.0);
        $accuracyMultiplier = (float) config('fleet.movement.accuracy_noise_multiplier', 0.5);
        $accuracyRadius = max((float) ($currentAccuracy ?? 0.0), (float) ($previousAccuracy ?? 0.0));

        return max($minDisplacement, $accuracyRadius * $accuracyMultiplier);
    }
}


