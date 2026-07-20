<?php

namespace App\Services\Routing;

use App\Services\Contracts\GeospatialServiceInterface;
use App\Services\ValueObjects\Coordinate;
use App\Data\ETAResult;

class ETAEngine
{
    /**
     * Recalculate predictions (ETA timestamp, remaining path distance) for upcoming stops.
     */
    public function calculateETAs(
        int $tripId,
        Coordinate $position,
        array $polylineCoordinates,
        array $upcomingStops,
        float $currentSpeedMps = 0.0
    ): array {
        $n = count($polylineCoordinates);
        if ($n < 2 || empty($upcomingStops)) {
            return [];
        }

        $geospatial = app(GeospatialServiceInterface::class);

        // Find the bus's projection and segment index
        $busProjection = $this->findClosestSegment($position, $polylineCoordinates, $geospatial);
        $busSeg = $busProjection['segment'];
        $busProjCoord = $busProjection['projection'];

        // Determine effective speed (prevent division by zero, default to ~20 km/h = 5.5 m/s)
        $speed = $currentSpeedMps;
        if ($speed < 2.0) {
            $speed = 5.55;
        }

        $results = [];
        $accumulatedSeconds = 0;

        foreach ($upcomingStops as $stop) {
            $stopCoord = new Coordinate((float) $stop['lat'], (float) $stop['lng']);

            // Find the stop's projection and segment index on the same polyline
            $stopProjection = $this->findClosestSegment($stopCoord, $polylineCoordinates, $geospatial);
            $stopSeg = $stopProjection['segment'];
            $stopProjCoord = $stopProjection['projection'];

            // Calculate path distance along route geometry
            if ($busSeg > $stopSeg) {
                // Stop is already passed
                $distanceMeters = 0.0;
            } else {
                $distanceMeters = $this->calculatePathDistance(
                    $busProjCoord,
                    $busSeg,
                    $stopProjCoord,
                    $stopSeg,
                    $polylineCoordinates,
                    $geospatial
                );
            }

            // Estimate travel duration
            $travelDurationSeconds = $distanceMeters / $speed;
            $accumulatedSeconds = $travelDurationSeconds; // non-cumulative relative to current position

            // Add standard stops dwell buffer (e.g. 30 seconds per intermediate stop)
            // Let's keep it simple: ETA is current time + travel duration
            $etaTime = now()->addSeconds((int) round($accumulatedSeconds));

            // Estimate delay compared to scheduled time if applicable
            $delayMinutes = 0;

            $results[] = new ETAResult(
                stopId: (int) $stop['id'],
                etaTimestamp: $etaTime->toIso8601String(),
                distanceRemainingMeters: round($distanceMeters, 1),
                delayMinutes: $delayMinutes
            );
        }

        return $results;
    }

    private function findClosestSegment(Coordinate $p, array $polyline, GeospatialServiceInterface $geospatial): array
    {
        $minDist = null;
        $segIndex = 0;
        $projCoord = null;
        $n = count($polyline);

        for ($i = 0; $i < $n - 1; $i++) {
            $a = new Coordinate((float) $polyline[$i][0], (float) $polyline[$i][1]);
            $b = new Coordinate((float) $polyline[$i + 1][0], (float) $polyline[$i + 1][1]);

            $dx = $b->getLatitude() - $a->getLatitude();
            $dy = $b->getLongitude() - $a->getLongitude();

            if ($dx === 0.0 && $dy === 0.0) {
                $dist = $geospatial->calculateDistance($p, $a);
                $proj = $a;
            } else {
                $t = (($p->getLatitude() - $a->getLatitude()) * $dx + ($p->getLongitude() - $a->getLongitude()) * $dy) / ($dx * $dx + $dy * $dy);
                $t = max(0.0, min(1.0, $t));
                $proj = new Coordinate($a->getLatitude() + $t * $dx, $a->getLongitude() + $t * $dy);
                $dist = $geospatial->calculateDistance($p, $proj);
            }

            if ($minDist === null || $dist < $minDist) {
                $minDist = $dist;
                $segIndex = $i;
                $projCoord = $proj;
            }
        }

        return ['segment' => $segIndex, 'projection' => $projCoord];
    }

    private function calculatePathDistance(
        Coordinate $from,
        int $fromSeg,
        Coordinate $to,
        int $toSeg,
        array $polyline,
        GeospatialServiceInterface $geospatial
    ): float {
        if ($fromSeg > $toSeg) {
            return 0.0;
        }

        if ($fromSeg === $toSeg) {
            return $geospatial->calculateDistance($from, $to);
        }

        // 1. Distance from vehicle projection to next node
        $nextNode = new Coordinate((float) $polyline[$fromSeg + 1][0], (float) $polyline[$fromSeg + 1][1]);
        $distance = $geospatial->calculateDistance($from, $nextNode);

        // 2. Add full segments between fromSeg+1 and toSeg
        for ($k = $fromSeg + 1; $k < $toSeg; $k++) {
            $n1 = new Coordinate((float) $polyline[$k][0], (float) $polyline[$k][1]);
            $n2 = new Coordinate((float) $polyline[$k + 1][0], (float) $polyline[$k + 1][1]);
            $distance += $geospatial->calculateDistance($n1, $n2);
        }

        // 3. Add distance from start of target segment to target point projection
        $prevNode = new Coordinate((float) $polyline[$toSeg][0], (float) $polyline[$toSeg][1]);
        $distance += $geospatial->calculateDistance($prevNode, $to);

        return $distance;
    }
}
