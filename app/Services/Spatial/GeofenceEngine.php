<?php

namespace App\Services\Spatial;

use App\Models\Geofence;
use App\Services\ValueObjects\Coordinate;
use App\Services\Contracts\GeospatialServiceInterface;
use App\Enums\SpatialPresenceState;
use App\Data\SpatialStateResult;
use Illuminate\Support\Facades\Cache;

class GeofenceEngine
{
    public function __construct(protected GeospatialServiceInterface $geospatial) {}

    /**
     * Check if point is inside circular geofence.
     */
    public function isInsideCircle(Coordinate $point, Coordinate $center, float $radius): bool
    {
        return $this->geospatial->calculateDistance($point, $center) <= $radius;
    }

    /**
     * Check if point is inside polygon geofence using Ray-Casting PIP algorithm.
     */
    public function isInsidePolygon(Coordinate $point, array $polygon): bool
    {
        $lat = $point->getLatitude();
        $lng = $point->getLongitude();
        $inside = false;
        $n = count($polygon);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $latI = (float) $polygon[$i][0];
            $lngI = (float) $polygon[$i][1];
            $latJ = (float) $polygon[$j][0];
            $lngJ = (float) $polygon[$j][1];

            if ((($latI > $lat) != ($latJ > $lat)) &&
                ($lng < ($lngJ - $lngI) * ($lat - $latI) / ($latJ - $latI) + $lngI)) {
                $inside = !$inside;
            }
        }
        return $inside;
    }

    /**
     * Evaluates a vehicle's coordinate against a geofence and returns the state machine result.
     */
    public function check(Coordinate $point, Geofence $geofence, int $busId): SpatialStateResult
    {
        $geometry = $geofence->geometry;
        $isInside = false;

        $center = new Coordinate((float)$geofence->lat, (float)$geofence->lng);
        $distance = $this->geospatial->calculateDistance($point, $center);

        if (isset($geometry['type']) && $geometry['type'] === 'Polygon') {
            $isInside = $this->isInsidePolygon($point, $geometry['coordinates']);
        } else {
            $radius = $geofence->radius ?? (float) config('fleet.spatial.stop_radius');
            $isInside = $distance <= $radius;
        }

        $stateKey = "bus:{$busId}:geofence:{$geofence->id}:state";
        $enteredAtKey = "bus:{$busId}:geofence:{$geofence->id}:entered_at";
        $exitPendingAtKey = "bus:{$busId}:geofence:{$geofence->id}:exit_pending_at";

        $currentStateName = Cache::get($stateKey, SpatialPresenceState::OUTSIDE->value);
        $currentState = SpatialPresenceState::tryFrom($currentStateName) ?? SpatialPresenceState::OUTSIDE;

        $targetState = $currentState;
        $now = now();

        if ($isInside) {
            if ($currentState === SpatialPresenceState::OUTSIDE) {
                $targetState = SpatialPresenceState::ENTERING;
                Cache::put($enteredAtKey, $now->timestamp, 86400);
                Cache::forget($exitPendingAtKey);
            } elseif ($currentState === SpatialPresenceState::EXIT_PENDING || $currentState === SpatialPresenceState::ENTERING) {
                $targetState = SpatialPresenceState::INSIDE;
                Cache::forget($exitPendingAtKey);
            }
        } else {
            if ($currentState === SpatialPresenceState::INSIDE || $currentState === SpatialPresenceState::ENTERING) {
                $targetState = SpatialPresenceState::EXIT_PENDING;
                Cache::put($exitPendingAtKey, $now->timestamp, 86400);
            } elseif ($currentState === SpatialPresenceState::EXIT_PENDING) {
                $exitPendingAt = Cache::get($exitPendingAtKey, $now->timestamp);
                $gracePeriod = (int) config('fleet.spatial.hysteresis_time_threshold_seconds');
                
                if (($now->timestamp - $exitPendingAt) >= $gracePeriod) {
                    $targetState = SpatialPresenceState::OUTSIDE;
                    Cache::forget($enteredAtKey);
                    Cache::forget($exitPendingAtKey);
                }
            }
        }

        if ($targetState !== $currentState) {
            Cache::put($stateKey, $targetState->value, 86400);
        }

        return new SpatialStateResult($targetState, $distance, $geofence->id, $geofence->type);
    }
}
