<?php

namespace App\Services;

use App\Services\Contracts\GeospatialServiceInterface;
use App\Services\ValueObjects\Coordinate;

class GeospatialService implements GeospatialServiceInterface
{
    public function calculateDistance(Coordinate $c1, Coordinate $c2): float
    {
        $earthRadius = 6371000; // in meters

        $lat1 = $c1->getLatitude();
        $lng1 = $c1->getLongitude();
        $lat2 = $c2->getLatitude();
        $lng2 = $c2->getLongitude();

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function calculateDistanceKm(Coordinate $c1, Coordinate $c2): float
    {
        return $this->calculateDistance($c1, $c2) / 1000.0;
    }

    public function calculateBearing(Coordinate $c1, Coordinate $c2): float
    {
        $lat1 = deg2rad($c1->getLatitude());
        $lng1 = deg2rad($c1->getLongitude());
        $lat2 = deg2rad($c2->getLatitude());
        $lng2 = deg2rad($c2->getLongitude());

        $dLon = $lng2 - $lng1;

        $y = sin($dLon) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLon);

        $brng = rad2deg(atan2($y, $x));

        return fmod(($brng + 360), 360);
    }
}
