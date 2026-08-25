<?php

namespace App\Data;

use App\Models\Trip;
use App\Models\Stop;
use App\Models\Terminal;
use App\Models\Geofence;
use Illuminate\Support\Collection;

class SpatialContext
{
    public function __construct(
        public ?Trip $trip,
        public Collection $nearbyGeofences,
        public ?Stop $nearestStop,
        public ?Terminal $nearestTerminal,
        public ?Geofence $nearestDepot
    ) {}
}
