<?php

namespace App\Services\GPS;

use App\Services\GPS\Contracts\PositionFilterInterface;
use App\Services\ValueObjects\Coordinate;

class GPSSmoothingService
{
    public function __construct(protected PositionFilterInterface $filter) {}

    /**
     * Smooth raw coordinate telemetry.
     */
    public function smooth(int|string $busId, Coordinate $coord, int|string|null $tripId = null): Coordinate
    {
        return $this->filter->filter($busId, $coord, $tripId);
    }
}