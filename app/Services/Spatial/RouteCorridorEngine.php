<?php

namespace App\Services\Spatial;

use App\Models\VehiclePosition;
use App\Models\RouteCorridor;
use App\Models\Trip;
use App\Models\TripProgress;
use App\Models\RouteDeviation;
use App\Services\ValueObjects\Coordinate;
use App\Services\Contracts\GeospatialServiceInterface;
use App\Enums\RouteDeviationSeverity;
use App\Events\RouteDeviationDetected;
use App\Events\RouteRecovered;

class RouteCorridorEngine
{
    public function __construct(protected GeospatialServiceInterface $geospatial) {}

    /**
     * Snap coordinate to route polyline and evaluate corridor deviation severity.
     */
    public function check(VehiclePosition $position, Coordinate $coord, ?RouteCorridor $corridor, Trip $trip): void
    {
        $polylineCoordinates = $trip->route->polyline_coordinates;
        if (is_null($polylineCoordinates) || !is_array($polylineCoordinates)) {
            return;
        }
        $n = count($polylineCoordinates);
        if ($n < 2) {
            return;
        }

        $minDistance = null;

        // Iterate over each segment to find the minimum distance
        for ($i = 0; $i < $n - 1; $i++) {
            $a = new Coordinate((float)$polylineCoordinates[$i][0], (float)$polylineCoordinates[$i][1]);
            $b = new Coordinate((float)$polylineCoordinates[$i + 1][0], (float)$polylineCoordinates[$i + 1][1]);

            $dist = $this->distanceToSegment($coord, $a, $b);
            if ($minDistance === null || $dist < $minDistance) {
                $minDistance = $dist;
            }
        }

        // Get corridor buffer size (meters)
        $buffer = $corridor ? $corridor->buffer_width : (float) config('fleet.spatial.corridor_default');

        // Update VehiclePosition latest corridor distance
        $position->update(['corridor_distance' => round($minDistance, 1)]);

        $progress = TripProgress::firstOrCreate(['trip_id' => $trip->id]);
        $isDeviated = $minDistance > $buffer;

        if ($isDeviated) {
            // Determine severity
            $severity = RouteDeviationSeverity::MINOR;
            if ($minDistance > $buffer * 5.0) {
                $severity = RouteDeviationSeverity::CRITICAL;
            } elseif ($minDistance > $buffer * 2.5) {
                $severity = RouteDeviationSeverity::MAJOR;
            }

            $severityName = match($severity) {
                RouteDeviationSeverity::CRITICAL => 'Critical',
                RouteDeviationSeverity::MAJOR => 'Major',
                RouteDeviationSeverity::MINOR => 'Minor',
                default => 'Minor',
            };

            // Log RouteDeviation
            RouteDeviation::create([
                'trip_id' => $trip->id,
                'lat' => $position->lat,
                'lng' => $position->lng,
                'distance_meters' => $minDistance,
                'severity' => $severityName,
                'detected_at' => now(),
            ]);

            // Update TripProgress status
            $progress->update([
                'route_adherence' => $severityName . ' Deviation',
            ]);

            // Dispatch event
            event(new RouteDeviationDetected(
                $trip->id,
                $position->lat,
                $position->lng,
                $minDistance,
                $severityName
            ));
        } else {
            // Resolve active deviation log
            $affectedRows = RouteDeviation::where('trip_id', $trip->id)
                ->whereNull('resolved_at')
                ->update(['resolved_at' => now()]);

            // Reset progress status to on route
            $progress->update([
                'route_adherence' => 'On Route',
            ]);

            // Dispatch RouteRecovered if it was previously deviated
            if ($affectedRows > 0) {
                event(new RouteRecovered($trip->id, $position->lat, $position->lng));
            }
        }
    }

    private function distanceToSegment(Coordinate $p, Coordinate $a, Coordinate $b): float
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
            return $this->geospatial->calculateDistance($p, $a);
        }

        // Project point P onto segment AB
        $t = (($latP - $latA) * $dx + ($lngP - $lngA) * $dy) / ($dx * $dx + $dy * $dy);
        $t = max(0.0, min(1.0, $t));

        $closestLat = $latA + $t * $dx;
        $closestLng = $lngA + $t * $dy;

        return $this->geospatial->calculateDistance($p, new Coordinate($closestLat, $closestLng));
    }
}
