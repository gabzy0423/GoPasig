<?php

namespace App\Services\Contracts;

use App\Services\ValueObjects\Coordinate;
use App\Services\ValueObjects\Polyline;

interface RouteGeometryServiceInterface
{
    /**
     * Measure the total length of a polyline in kilometers.
     */
    public function measureLength(Polyline $polyline): float;

    /**
     * Snap a stop or coordinate to the closest point on the polyline.
     */
    public function snapCoordinateToPolyline(Coordinate $coord, Polyline $polyline): Coordinate;
}
