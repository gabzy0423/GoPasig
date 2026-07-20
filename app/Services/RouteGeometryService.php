<?php

namespace App\Services;

use App\Services\Contracts\RouteGeometryServiceInterface;
use App\Services\Contracts\GeospatialServiceInterface;
use App\Services\ValueObjects\Coordinate;
use App\Services\ValueObjects\Polyline;

class RouteGeometryService implements RouteGeometryServiceInterface
{
    private GeospatialServiceInterface $geospatial;

    public function __construct(GeospatialServiceInterface $geospatial)
    {
        $this->geospatial = $geospatial;
    }

    public function measureLength(Polyline $polyline): float
    {
        $coords = $polyline->getCoordinates();
        if (count($coords) < 2) {
            return 0.0;
        }

        $totalLength = 0.0;
        for ($i = 0; $i < count($coords) - 1; $i++) {
            $totalLength += $this->geospatial->calculateDistanceKm($coords[$i], $coords[$i + 1]);
        }

        return $totalLength;
    }

    public function snapCoordinateToPolyline(Coordinate $coord, Polyline $polyline): Coordinate
    {
        $coords = $polyline->getCoordinates();
        if (empty($coords)) {
            return $coord;
        }

        $minDist = null;
        $closest = null;

        foreach ($coords as $pt) {
            $dist = $this->geospatial->calculateDistance($coord, $pt);
            if ($minDist === null || $dist < $minDist) {
                $minDist = $dist;
                $closest = $pt;
            }
        }

        return new Coordinate(
            $closest->getLatitude(),
            $closest->getLongitude(),
            $coord->getBearing(),
            $coord->getAccuracy(),
            $coord->getSpeed(),
            $coord->getTimestamp()
        );
    }
}
