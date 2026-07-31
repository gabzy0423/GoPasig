<?php

namespace App\Data;

use App\Models\Stop;

final class StopGeofenceEvaluation
{
    public function __construct(
        public readonly ?Stop $nearestStop,
        public readonly ?float $distanceToNearestMeters,
        public readonly ?Stop $activeStop,
        public readonly array $stopDistances = [],
    ) {}

    public function isInsideStop(): bool
    {
        return $this->activeStop !== null;
    }
}
