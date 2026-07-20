<?php

namespace App\Services;

use App\Services\Contracts\KalmanFilterServiceInterface;
use App\Services\Contracts\GeospatialServiceInterface;
use App\Services\ValueObjects\Coordinate;

class GPSKalmanFilter
{
    /**
     * Smooth raw coordinates using a 1D Kalman Filter.
     *
     * @param int|string $busId
     * @param float $lat
     * @param float $lng
     * @return array ['lat' => float, 'lng' => float]
     */
    public static function smooth($busId, $lat, $lng)
    {
        $coord = new Coordinate((float)$lat, (float)$lng);
        $smoothed = app(KalmanFilterServiceInterface::class)->smooth($busId, $coord);

        return [
            'lat' => $smoothed->getLatitude(),
            'lng' => $smoothed->getLongitude(),
        ];
    }

    /**
     * Compute geographical distance in meters between two lat/lng pairs using the Haversine formula.
     *
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float Distance in meters
     */
    public static function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $c1 = new Coordinate((float)$lat1, (float)$lng1);
        $c2 = new Coordinate((float)$lat2, (float)$lng2);

        return app(GeospatialServiceInterface::class)->calculateDistance($c1, $c2);
    }
}

