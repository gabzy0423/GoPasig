<?php

namespace App\Data;

use App\Enums\SpatialPresenceState;
use App\Enums\GeofenceType;

class SpatialStateResult
{
    public function __construct(
        public SpatialPresenceState $state,
        public float $distance,
        public int $geofenceId,
        public GeofenceType $geofenceType
    ) {}
}
