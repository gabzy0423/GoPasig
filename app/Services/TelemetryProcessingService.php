<?php

namespace App\Services;

use App\Events\GPSRejected;
use App\Events\GPSValidated;
use App\Events\PositionUpdated;
use App\Models\Bus;
use App\Models\GPSLog;
use App\Models\Trip;
use App\Models\VehiclePosition;
use App\Services\GPS\GPSSmoothingService;
use App\Services\GpsQualityService;
use App\Services\MovementClassificationService;
use App\Services\Routing\FleetStatusService;
use App\Services\Routing\GPSValidationService;
use App\Services\Routing\AuthoritativeRouteResolver;
use App\Services\ValueObjects\Coordinate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelemetryProcessingService
{
    public function __construct(
        protected GPSValidationService $validator,
        protected GPSSmoothingService $smoothing,
        protected FleetStatusService $fleetStatus,
        protected MovementClassificationService $movementClassifier,
        protected GpsQualityService $gpsQuality,
        protected HeadingService $headingService,
        protected AuthoritativeRouteResolver $routeResolver
    ) {}

    /**
     * Process one GPS log into live vehicle state.
     *
     * The core database writes are kept together so GPSLog, VehiclePosition,
     * and Bus state do not drift apart during synchronous live telemetry.
     */
    public function processGpsLog(int $gpsLogId): array
    {
        $startedAt = microtime(true);

        Log::info('[GPS_TRACE] F - TelemetryProcessingService::processGpsLog started', [
            'gps_log_id' => $gpsLogId,
        ]);

        $log = GPSLog::find($gpsLogId);
        if (!$log) {
            Log::warning('[GPS_TRACE] F-ABORT - GPSLog not found', ['gps_log_id' => $gpsLogId]);
            return [
                'log' => null,
                'position' => null,
                'processing_ms' => $this->elapsedMs($startedAt),
                'status' => 'missing_log',
            ];
        }

        $trip = Trip::with(['bus', 'route'])->find($log->trip_id);
        if (!$trip) {
            Log::warning('[GPS_TRACE] F-ABORT - Trip not found', [
                'gps_log_id' => $gpsLogId,
                'trip_id'    => $log->trip_id,
            ]);
            $log->update([
                'processing_status' => 'invalid',
                'processed_at'      => now('UTC'),
            ]);

            return [
                'log' => $log->fresh(),
                'position' => null,
                'processing_ms' => $this->elapsedMs($startedAt),
                'status' => 'invalid',
            ];
        }

        if ($trip->status !== 'ongoing' || $trip->gps_session !== 'ACTIVE') {
            Log::warning('[GPS_TRACE] F-REJECTED - Trip guard failed', [
                'gps_log_id'  => $gpsLogId,
                'trip_id'     => $trip->id,
                'trip_status' => $trip->status,
                'gps_session' => $trip->gps_session,
            ]);
            $log->update([
                'processing_status' => 'rejected',
                'processed_at'      => now('UTC'),
            ]);

            return [
                'log' => $log->fresh(),
                'position' => null,
                'processing_ms' => $this->elapsedMs($startedAt),
                'status' => 'rejected',
            ];
        }

        Log::info('[GPS_TRACE] F2 - Guards passed', [
            'gps_log_id' => $gpsLogId,
            'trip_id'    => $trip->id,
            'bus_id'     => $trip->bus_id,
            'driver_id'  => $trip->driver_id,
        ]);

        $lastLog = GPSLog::where('trip_id', $log->trip_id)
            ->where('processing_status', 'processed')
            ->where('id', '<', $log->id)
            ->orderBy('id', 'desc')
            ->first();

        try {
            $payload = array_merge($log->toArray(), [
                'gps_log_id' => $log->id,
                'trip_id'    => $trip->id,
                'bus_id'     => $trip->bus_id,
                'driver_id'  => $trip->driver_id,
                'timestamp'  => $log->timestamp,
            ]);

            Log::info('[GPS_TRACE] G - Before GPSValidationService::validate', [
                'gps_log_id'   => $gpsLogId,
                'has_last_log' => (bool) $lastLog,
                'last_log_id'  => $lastLog?->id,
                'accuracy'     => $log->accuracy,
                'timestamp'    => $log->timestamp?->toIso8601String(),
            ]);

            $this->validator->validate($payload, $lastLog);

            $coordValidation = BusinessLogicService::validateCoordinates($log->lat, $log->lng);
            if (!$coordValidation['valid']) {
                throw new \InvalidArgumentException('Invalid GPS coordinates - outside service area or physically impossible.');
            }

            Log::info('[GPS_TRACE] H - Before GPSSmoothingService::smooth', [
                'gps_log_id' => $gpsLogId,
                'bus_id'     => $trip->bus_id,
                'trip_id'    => $trip->id,
                'raw_lat'    => $log->lat,
                'raw_lng'    => $log->lng,
            ]);

            $coord = new Coordinate(
                $log->lat,
                $log->lng,
                $log->heading,
                $log->accuracy,
                $log->speed,
                $log->timestamp->toIso8601String()
            );

            $smoothed = $this->smoothing->smooth($trip->bus_id, $coord, $trip->id);

            $position = DB::transaction(function () use ($log, $trip, $smoothed) {
                $log->update([
                    'filtered_lat'      => $smoothed->getLatitude(),
                    'filtered_lng'      => $smoothed->getLongitude(),
                    'processing_status' => 'processed',
                    'processed_at'      => now('UTC'),
                ]);

                Log::info('[GPS_TRACE] I - Before VehiclePosition::updateOrCreate', [
                    'gps_log_id' => $log->id,
                    'bus_id'     => $trip->bus_id,
                    'trip_id'    => $trip->id,
                ]);

                $position = VehiclePosition::updateOrCreate(
                    ['bus_id' => $trip->bus_id],
                    [
                        'trip_id'         => $trip->id,
                        'lat'             => $smoothed->getLatitude(),
                        'lng'             => $smoothed->getLongitude(),
                        'speed'           => $log->speed,
                        'heading'         => $log->heading,
                        'last_updated_at' => $log->timestamp,
                    ]
                );

                $gpsQuality = $this->gpsQuality->classify($log, $position);
                $position->update([
                    'gps_quality_state' => $gpsQuality['gps_quality_state'],
                    'gps_quality_reason' => $gpsQuality['gps_quality_reason'],
                    'gps_quality_updated_at' => $gpsQuality['gps_quality_updated_at'],
                    'gps_fix_age_seconds' => $gpsQuality['gps_fix_age_seconds'],
                    'last_gps_fix_at' => $gpsQuality['last_gps_fix_at'],
                ]);
                $position->refresh();

                $movement = $this->movementClassifier->classify($log, $position);
                $movementEvidence = $movement['evidence'] ?? [];
                Log::debug('[MOVEMENT_CLASSIFIER] classified GPS log', [
                    'gps_log_id' => $log->id,
                    'trip_id' => $trip->id,
                    'bus_id' => $trip->bus_id,
                    'is_cached_fix' => (bool) $log->is_cached_fix,
                    'speed_source' => $log->speed_source,
                    'speed_mps' => $log->speed,
                    'accuracy' => $log->accuracy,
                    'displacement_meters' => $movementEvidence['distance_meters'] ?? null,
                    'dynamic_displacement_threshold' => $movementEvidence['displacement_threshold_meters'] ?? null,
                    'implied_speed_mps' => $movementEvidence['implied_speed_mps'] ?? null,
                    'evidence_type' => $movementEvidence['evidence_type'] ?? null,
                    'movement_positive_samples' => $movement['movement_positive_samples'],
                    'movement_negative_samples' => $movement['movement_negative_samples'],
                    'movement_state' => $movement['movement_state'],
                    'movement_reason' => $movement['movement_reason'],
                ]);
                $position->update([
                    'movement_state' => $movement['movement_state'],
                    'movement_confidence' => $movement['movement_confidence'],
                    'movement_reason' => $movement['movement_reason'],
                    'movement_state_updated_at' => $movement['movement_state_updated_at'],
                    'movement_positive_samples' => $movement['movement_positive_samples'],
                    'movement_negative_samples' => $movement['movement_negative_samples'],
                ]);
                $position->refresh();

                $runtimeSpeedMps = $this->canonicalRuntimeSpeedMps($log, $position);
                $position->update(['speed' => $runtimeSpeedMps]);
                $position->refresh();

                $heading = $this->headingService->resolve($log, $position);
                $position->update([
                    'display_heading' => $heading['display_heading'],
                    'heading_source' => $heading['heading_source'],
                    'heading_updated_at' => $heading['heading_updated_at'],
                ]);
                $position->refresh();

                $status = $this->fleetStatus->determineStatus($position);
                $position->update(['status' => $status]);
                $position->refresh();

                $bus      = $trip->bus ?: Bus::find($trip->bus_id);
                $nextStop = $bus?->next_stop;
                $eta      = $bus?->eta ?: 5;
                $route    = $trip->route;
                $routePlan = $this->routeResolver->resolveForTrip($trip);

                if ($route && $routePlan->orderedStops->isNotEmpty()) {
                    $stops = $routePlan->orderedStops->values();

                    $currentStop = $stops->first(function ($s) use ($nextStop) {
                        return stripos($s->name, (string) $nextStop) !== false
                            || stripos((string) $nextStop, $s->name) !== false;
                    });

                    if (!$currentStop) {
                        $currentStop = $stops->first();
                    }

                    $distanceToStop = \App\Services\GPSKalmanFilter::calculateDistance(
                        $smoothed->getLatitude(),
                        $smoothed->getLongitude(),
                        $currentStop->lat,
                        $currentStop->lng
                    );

                    $autoAdvanceThreshold = (float) \App\Models\SystemSetting::get('stop_auto_advance_distance', 100);
                    if ($distanceToStop <= $autoAdvanceThreshold) {
                        $currentIndex = $stops->search(fn ($stop) => (int) $stop->id === (int) $currentStop->id);
                        if ($currentIndex === false) {
                            $currentIndex = 0;
                        }
                        $nextIndex   = ($currentIndex + 1) % $stops->count();
                        $currentStop = $stops->get($nextIndex);

                        $distanceToStop = \App\Services\GPSKalmanFilter::calculateDistance(
                            $smoothed->getLatitude(),
                            $smoothed->getLongitude(),
                            $currentStop->lat,
                            $currentStop->lng
                        );
                    }

                    $nextStop = $currentStop->name;
                    $speedMps = (float) $log->speed;

                    if ($speedMps >= 2.0) {
                        $eta = (int) round(($distanceToStop / $speedMps) / 60);
                    } else {
                        $averageFleetSpeed = Bus::where('route_id', $route->id)
                            ->where('status', 'active')
                            ->where('speed', '>', 2.0)
                            ->avg('speed');

                        if (!$averageFleetSpeed) {
                            $averageFleetSpeed = Bus::where('status', 'active')
                                ->where('speed', '>', 2.0)
                                ->avg('speed');
                        }

                        if (!$averageFleetSpeed) {
                            $averageFleetSpeed = DB::table('gps_logs')
                                ->where('speed', '>', 2.0)
                                ->where('created_at', '>=', now()->subHour())
                                ->avg('speed');
                        }

                        $fallbackSpeedMps = $averageFleetSpeed ? (float) $averageFleetSpeed : 5.55;
                        $fallbackSpeedMps = max(2.0, $fallbackSpeedMps);
                        $eta              = (int) round(($distanceToStop / $fallbackSpeedMps) / 60);
                    }
                    $eta = max(1, $eta);
                }

                if ($bus) {
                    $bus->update([
                        'lat'       => $smoothed->getLatitude(),
                        'lng'       => $smoothed->getLongitude(),
                        'speed'     => $runtimeSpeedMps,
                        'next_stop' => $nextStop,
                        'eta'       => $eta,
                    ]);
                }

                return $position;
            });

            event(new GPSValidated($log->fresh()));
            event(new PositionUpdated($position));

            $processingMs = $this->elapsedMs($startedAt);

            Log::info('[GPS_TRACE] TELEMETRY COMPLETE', [
                'gps_log_id'    => $gpsLogId,
                'position_id'   => $position->id,
                'bus_id'        => $position->bus_id,
                'trip_id'       => $position->trip_id,
                'lat'           => $position->lat,
                'lng'           => $position->lng,
                'processing_ms' => $processingMs,
            ]);

            return [
                'log' => $log->fresh(),
                'position' => $position->fresh(),
                'processing_ms' => $processingMs,
                'status' => 'processed',
            ];
        } catch (\Throwable $e) {
            $previousTimestamp = $lastLog?->timestamp;
            $elapsedSeconds = ($previousTimestamp && $log->timestamp)
                ? abs($log->timestamp->diffInSeconds($previousTimestamp, false))
                : null;
            $distanceMeters = null;
            $impliedSpeedMps = null;
            if ($lastLog) {
                $distanceMeters = app(\App\Services\Contracts\GeospatialServiceInterface::class)
                    ->calculateDistance(
                        new Coordinate($lastLog->lat, $lastLog->lng),
                        new Coordinate($log->lat, $log->lng)
                    );
                if ($elapsedSeconds > 0) {
                    $impliedSpeedMps = $distanceMeters / $elapsedSeconds;
                }
            }
            $message = $e->getMessage();
            $validationRule = match (true) {
                str_contains($message, 'cannot be zero') => 'ZERO_COORDINATES',
                str_contains($message, 'accuracy') => 'ACCURACY_TOO_HIGH',
                str_contains($message, 'Timestamp is required') => 'MISSING_TIMESTAMP',
                str_contains($message, 'future') => 'FUTURE_TIMESTAMP',
                str_contains($message, 'Out-of-order') => 'OUT_OF_ORDER_TIMESTAMP',
                str_contains($message, 'Duplicate coordinates') => 'DUPLICATE_COORDINATES',
                str_contains($message, 'Impossible GPS position jump') => 'IMPOSSIBLE_JUMP',
                default => 'OTHER_PROCESSING_EXCEPTION',
            };

            Log::warning('[GPS_DIAGNOSTIC] TelemetryProcessingService rejected packet', [
                'gps_log_id' => $gpsLogId,
                'trip_id' => $trip->id,
                'bus_id' => $trip->bus_id,
                'current_lat' => $log->lat,
                'current_lng' => $log->lng,
                'previous_reference_log_id' => $lastLog?->id,
                'previous_lat' => $lastLog?->lat,
                'previous_lng' => $lastLog?->lng,
                'current_timestamp' => $log->timestamp?->toIso8601String(),
                'previous_timestamp' => $previousTimestamp?->toIso8601String(),
                'elapsed_seconds' => $elapsedSeconds,
                'calculated_distance_meters' => $distanceMeters,
                'implied_speed_mps' => $impliedSpeedMps,
                'reported_speed' => $log->speed,
                'accuracy' => $log->accuracy,
                'validation_rule_failed' => $validationRule,
                'rejection_reason' => $message,
            ]);

            Log::error('[GPS_TRACE] TELEMETRY EXCEPTION', [
                'gps_log_id' => $gpsLogId,
                'exception'  => get_class($e),
                'message'    => $message,
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
            ]);

            $log->update([
                'processing_status' => 'invalid',
                'processed_at'      => now('UTC'),
            ]);

            event(new GPSRejected($log->trip_id, $log->toArray(), $e->getMessage()));

            return [
                'log' => $log->fresh(),
                'position' => null,
                'processing_ms' => $this->elapsedMs($startedAt),
                'status' => 'invalid',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function canonicalRuntimeSpeedMps(GPSLog $log, VehiclePosition $position): float
    {
        if ($position->movement_state === MovementClassificationService::STATE_STATIONARY) {
            return 0.0;
        }

        return max(0.0, (float) ($log->speed ?? 0.0));
    }
}














