<?php

namespace App\Services\Spatial\Handlers;

use App\Models\VehiclePosition;
use App\Models\Geofence;
use App\Models\Trip;
use App\Data\SpatialStateResult;

interface GeofenceHandlerInterface
{
    public function handle(VehiclePosition $position, Geofence $geofence, SpatialStateResult $result, ?Trip $trip): void;
}
