<?php

namespace App\Services\Spatial;

use App\Models\VehiclePosition;
use App\Models\Trip;
use App\Models\Geofence;
use App\Models\RouteCorridor;
use App\Models\RouteVariantCorridor;
use App\Models\Stop;
use App\Models\Terminal;
use App\Data\SpatialContext;
use App\Services\ValueObjects\Coordinate;
use Illuminate\Support\Facades\Log;

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

        // 2. Resolve corridor ownership. Official variant trips require an exact variant-owned corridor.
        [$corridor, $corridorSource] = $this->resolveCorridor($trip, $position);

        // 3. Resolve nearby geofences via Bounding Box spatial index using config
        $margin = (float) config('fleet.spatial.indexing_margin');
        $nearbyGeofences = Geofence::where('status', 'active')
            ->whereBetween('lat', [$position->lat - $margin, $position->lat + $margin])
            ->whereBetween('lng', [$position->lng - $margin, $position->lng + $margin])
            ->get();

        // 4. Extract nearest items
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

        return new SpatialContext($trip, $corridor, $nearbyGeofences, $nearestStop, $nearestTerminal, $nearestDepot, $corridorSource);
    }

    private function resolveCorridor(?Trip $trip, VehiclePosition $position): array
    {
        if (!$trip) {
            return [null, 'none'];
        }

        if ($trip->route_variant_id) {
            $corridor = RouteVariantCorridor::with('routeVariant:id,route_id,direction')
                ->where('route_variant_id', $trip->route_variant_id)
                ->first();

            if ($corridor && $corridor->routeVariant?->route_id === $trip->route_id) {
                return [$corridor, 'route_variant_corridor'];
            }

            Log::warning('[GPS_TRACE] Missing RouteVariant corridor for trip spatial context', [
                'position_id' => $position->id,
                'trip_id' => $trip->id,
                'route_id' => $trip->route_id,
                'route_variant_id' => $trip->route_variant_id,
                'reason' => $corridor ? 'route_variant_mismatch' : 'missing_variant_corridor',
            ]);

            return [null, 'missing_variant_corridor'];
        }

        $corridor = RouteCorridor::where('route_id', $trip->route_id)->first();

        return [$corridor, $corridor ? 'legacy_route_corridor' : 'none'];
    }
}
