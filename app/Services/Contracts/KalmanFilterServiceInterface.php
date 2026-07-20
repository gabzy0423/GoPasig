<?php

namespace App\Services\Contracts;

use App\Services\ValueObjects\Coordinate;

interface KalmanFilterServiceInterface
{
    /**
     * Smooth raw coordinate telemetry using process and measurement variance state.
     */
    public function smooth(int|string $busId, Coordinate $coord, int|string|null $tripId = null): Coordinate;
}