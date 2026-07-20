<?php

namespace App\Services\Contracts;

use App\Services\ValueObjects\Coordinate;

interface GeospatialServiceInterface
{
    /**
     * Calculate great-circle distance in meters between two coordinates.
     */
    public function calculateDistance(Coordinate $c1, Coordinate $c2): float;

    /**
     * Calculate great-circle distance in kilometers between two coordinates.
     */
    public function calculateDistanceKm(Coordinate $c1, Coordinate $c2): float;

    /**
     * Calculate initial bearing (heading angle) between two coordinates in degrees.
     */
    public function calculateBearing(Coordinate $c1, Coordinate $c2): float;
}
