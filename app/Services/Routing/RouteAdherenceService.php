<?php

namespace App\Services\Routing;

use App\Data\RouteDeviationResult;
use App\Services\Contracts\GeospatialServiceInterface;
use App\Services\ValueObjects\Coordinate;

class RouteAdherenceService
{
    /**
     * Compare a vehicle position against the route polyline coordinates.
     * Evaluates deviation distance and categorizes severity (Minor, Major, Critical).
     */
    public function checkAdherence(int $tripId, Coordinate $position, array $polylineCoordinates): RouteDeviationResult
    {
        $n = count($polylineCoordinates);
        if ($n < 2) {
            return new RouteDeviationResult($tripId, $position->getLatitude(), $position->getLongitude(), 0.0, 'Minor', false);
        }

        $geospatial = app(GeospatialServiceInterface::class);
        $minDistance = null;

        // Iterate over each segment to find the minimum distance
        for ($i = 0; $i < $n - 1; $i++) {
            $a = new Coordinate((float)$polylineCoordinates[$i][0], (float)$polylineCoordinates[$i][1]);
            $b = new Coordinate((float)$polylineCoordinates[$i + 1][0], (float)$polylineCoordinates[$i + 1][1]);

            $dist = $this->distanceToSegment($position, $a, $b, $geospatial);
            if ($minDistance === null || $dist < $minDistance) {
                $minDistance = $dist;
            }
        }

        $minorLimit = (float) config('fleet.deviation.minor_meters', 50.0);
        $majorLimit = (float) config('fleet.deviation.major_meters', 150.0);
        $criticalLimit = (float) config('fleet.deviation.critical_meters', 300.0);

        $isDeviated = $minDistance > $minorLimit;
        $severity = 'Minor';

        if ($minDistance > $criticalLimit) {
            $severity = 'Critical';
        } elseif ($minDistance > $majorLimit) {
            $severity = 'Major';
        }

        return new RouteDeviationResult(
            tripId: $tripId,
            latitude: $position->getLatitude(),
            longitude: $position->getLongitude(),
            distanceMeters: $minDistance,
            severity: $severity,
            isDeviated: $isDeviated
        );
    }

    private function distanceToSegment(Coordinate $p, Coordinate $a, Coordinate $b, GeospatialServiceInterface $geospatial): float
    {
        $latP = $p->getLatitude();
        $lngP = $p->getLongitude();
        $latA = $a->getLatitude();
        $lngA = $a->getLongitude();
        $latB = $b->getLatitude();
        $lngB = $b->getLongitude();

        $dx = $latB - $latA;
        $dy = $lngB - $lngA;

        if ($dx === 0.0 && $dy === 0.0) {
            return $geospatial->calculateDistance($p, $a);
        }

        // Project point P onto segment AB
        $t = (($latP - $latA) * $dx + ($lngP - $lngA) * $dy) / ($dx * $dx + $dy * $dy);
        $t = max(0.0, min(1.0, $t));

        $closestLat = $latA + $t * $dx;
        $closestLng = $lngA + $t * $dy;

        return $geospatial->calculateDistance($p, new Coordinate($closestLat, $closestLng));
    }
}
