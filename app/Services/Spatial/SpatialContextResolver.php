<?php

namespace App\Services\Spatial;

use App\Models\VehiclePosition;
use App\Models\Trip;
use App\Models\Geofence;
use App\Models\Stop;
use App\Models\Terminal;
use App\Data\SpatialContext;
use App\Services\ValueObjects\Coordinate;

class SpatialContextResolver
{
    public function resolve(VehiclePosition $position): SpatialContext
    {
        // 1. Fetch active trip - first try trip_id on position, then resolve via bus_id
        $trip = null;
        if ($position->trip_id) {
            $trip = Trip::with(['route', 'routeVariant'])->find($position->trip_id);
        }
        if (!$trip) {
            $trip = Trip::with(['route', 'routeVariant'])
                ->where('bus_id', $position->bus_id)
                ->where('status', 'ongoing')
                ->latest('started_at')
                ->first();
        }

        // 2. Resolve nearby geofences via Bounding Box spatial index using config.
        $margin = (float) config('fleet.spatial.indexing_margin');
        $nearbyGeofences = Geofence::where('status', 'active')
            ->whereBetween('lat', [$position->lat - $margin, $position->lat + $margin])
            ->whereBetween('lng', [$position->lng - $margin, $position->lng + $margin])
            ->get();

        // 3. Extract nearest items.
        $nearestStop = null;
        $minStopDist = null;

        $nearestTerminal = null;
        $minTerminalDist = null;

        $nearestDepot = null;
        $minDepotDist = null;

        $geospatial = app(\App\Services\Contracts\GeospatialServiceInterface::class);
        $coord = new Coordinate($position->lat, $position->lng);

        foreach ($nearbyGeofences as $geofence) {
            $fenceCoord = new Coordinate($geofence->lat, $geofence->lng);
            $dist = $geospatial->calculateDistance($coord, $fenceCoord);

            if ($geofence->type->value === 'STOP') {
                if ($minStopDist === null || $dist < $minStopDist) {
                    $minStopDist = $dist;
                    $nearestStop = Stop::where('name', $geofence->name)->first();
                }
            } elseif ($geofence->type->value === 'TERMINAL') {
                if ($minTerminalDist === null || $dist < $minTerminalDist) {
                    $minTerminalDist = $dist;
                    $nearestTerminal = Terminal::where('name', $geofence->name)->first();
                }
            } elseif ($geofence->type->value === 'DEPOT') {
                if ($minDepotDist === null || $dist < $minDepotDist) {
                    $minDepotDist = $dist;
                    $nearestDepot = $geofence;
                }
            }
        }

        return new SpatialContext($trip, $nearbyGeofences, $nearestStop, $nearestTerminal, $nearestDepot);
    }
}
