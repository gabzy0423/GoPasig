<?php

namespace App\Data;

use App\Models\RouteVariantStop;
use App\Models\Stop;

final class StopGeofenceEvaluation
{
    public function __construct(
        public readonly Stop|RouteVariantStop|null $nearestStop,
        public readonly ?float $distanceToNearestMeters,
        public readonly Stop|RouteVariantStop|null $activeStop,
        public readonly array $stopDistances = [],
    ) {}

    public function isInsideStop(): bool
    {
        return $this->activeStop !== null;
    }
}
