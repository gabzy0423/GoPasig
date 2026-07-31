<?php

namespace App\Services\Commuter;

use App\Data\StopGeofenceEvaluation;
use App\Models\Stop;
use App\Services\Contracts\GeospatialServiceInterface;
use App\Services\Spatial\GeofenceEngine;
use App\Services\ValueObjects\Coordinate;
use Illuminate\Support\Collection;

class StopGeofenceEvaluator
{
    public function __construct(
        private readonly GeospatialServiceInterface $geospatial,
        private readonly GeofenceEngine $geofenceEngine,
    ) {}

    public function evaluate(Coordinate $location, Collection $stops): StopGeofenceEvaluation
    {
        $nearestStop = null;
        $nearestDistance = null;
        $activeStop = null;
        $distances = [];

        foreach ($stops as $stop) {
            if (! $stop instanceof Stop || $stop->lat === null || $stop->lng === null) {
                continue;
            }

            $stopCoordinate = new Coordinate((float) $stop->lat, (float) $stop->lng);
            $distance = $this->geospatial->calculateDistance($location, $stopCoordinate);
            $distances[$stop->id] = $distance;

            if ($nearestDistance === null || $distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestStop = $stop;
            }

            $radius = (float) ($stop->radius_meters ?? 100);
            if ($activeStop === null && $this->geofenceEngine->isInsideCircle($location, $stopCoordinate, $radius)) {
                $activeStop = $stop;
            }
        }

        return new StopGeofenceEvaluation($nearestStop, $nearestDistance, $activeStop, $distances);
    }
}
