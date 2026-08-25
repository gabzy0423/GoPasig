<?php

namespace App\Services\Spatial;

use App\Models\VehiclePosition;
use App\Data\SpatialContext;
use App\Services\Spatial\Handlers\GeofenceHandlerRegistry;
use App\Services\ValueObjects\Coordinate;
use App\Events\VehicleOnline;

class SpatialMonitoringEngine
{
    public function __construct(
        protected GeofenceEngine $geofenceEngine,
        protected GeofenceHandlerRegistry $registry
    ) {}

    /**
     * Coordinate the active geofence handler strategies.
     */
    public function process(VehiclePosition $position, SpatialContext $context): void
    {
        // 1. Online Transition
        if ($position->status === 'Offline') {
            event(new VehicleOnline($position->bus_id));
        }

        $coord = new Coordinate($position->lat, $position->lng);

        // 2. Evaluate Geofences
        foreach ($context->nearbyGeofences as $geofence) {
            $result = $this->geofenceEngine->check($coord, $geofence, $position->bus_id);
            $handler = $this->registry->get($geofence->type);
            $handler->handle($position, $geofence, $result, $context->trip);
        }
    }
}
