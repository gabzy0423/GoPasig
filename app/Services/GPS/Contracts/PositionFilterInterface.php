<?php

namespace App\Services\GPS\Contracts;

use App\Services\ValueObjects\Coordinate;

interface PositionFilterInterface
{
    /**
     * Filter raw coordinates to smooth noise.
     */
    public function filter(int|string $busId, Coordinate $coord, int|string|null $tripId = null): Coordinate;
}