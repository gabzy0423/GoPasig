<?php

namespace App\Services\Spatial;

use App\Models\VehiclePosition;
use App\Models\RouteCorridor;
use Illuminate\Database\Eloquent\Model;
use App\Models\Trip;
use App\Models\TripProgress;
use App\Models\RouteDeviation;
use App\Services\Routing\AuthoritativeRouteResolver;
use App\Services\ValueObjects\Coordinate;
use App\Services\Contracts\GeospatialServiceInterface;
use App\Enums\RouteDeviationSeverity;
use App\Events\RouteDeviationDetected;
use App\Events\RouteRecovered;

class RouteCorridorEngine
{
    public function __construct(
        protected GeospatialServiceInterface $geospatial,
        protected AuthoritativeRouteResolver $routeResolver
    ) {}

    /**
     * Snap coordinate to authoritative trip geometry and evaluate corridor deviation severity.
     */
    public function check(VehiclePosition $position, Coordinate $coord, ?Model $corridor, Trip $trip): void
    {
        $plan = $this->routeResolver->resolveForTrip($trip);
        $polylineCoordinates = $plan->polylineCoordinates;

        if (empty($polylineCoordinates) || !is_array($polylineCoordinates)) {
            return;
        }
        $n = count($polylineCoordinates);
        if ($n < 2) {
            return;
        }

        $minDistance = null;

        for ($i = 0; $i < $n - 1; $i++) {
            $a = new Coordinate((float)$polylineCoordinates[$i][0], (float)$polylineCoordinates[$i][1]);
            $b = new Coordinate((float)$polylineCoordinates[$i + 1][0], (float)$polylineCoordinates[$i + 1][1]);

            $dist = $this->distanceToSegment($coord, $a, $b);
            if ($minDistance === null || $dist < $minDistance) {
                $minDistance = $dist;
            }
        }

        $buffer = $corridor instanceof RouteCorridor
            ? $corridor->buffer_width
            : (float) config('fleet.spatial.corridor_default');

        $position->update(['corridor_distance' => round($minDistance, 1)]);

        $progress = TripProgress::firstOrCreate(['trip_id' => $trip->id]);
        $isDeviated = $minDistance > $buffer;

        if ($isDeviated) {
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

            RouteDeviation::create([
                'trip_id' => $trip->id,
                'lat' => $position->lat,
                'lng' => $position->lng,
                'distance_meters' => $minDistance,
                'severity' => $severityName,
                'detected_at' => now(),
            ]);

            $progress->update([
                'route_adherence' => $severityName . ' Deviation',
            ]);

            event(new RouteDeviationDetected(
                $trip->id,
                $position->lat,
                $position->lng,
                $minDistance,
                $severityName
            ));
        } else {
            $affectedRows = RouteDeviation::where('trip_id', $trip->id)
                ->whereNull('resolved_at')
                ->update(['resolved_at' => now()]);

            $progress->update([
                'route_adherence' => 'On Route',
            ]);

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

        $t = (($latP - $latA) * $dx + ($lngP - $lngA) * $dy) / ($dx * $dx + $dy * $dy);
        $t = max(0.0, min(1.0, $t));

        $closestLat = $latA + $t * $dx;
        $closestLng = $lngA + $t * $dy;

        return $this->geospatial->calculateDistance($p, new Coordinate($closestLat, $closestLng));
    }
}