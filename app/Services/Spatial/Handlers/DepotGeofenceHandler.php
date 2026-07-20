<?php

namespace App\Services\Spatial\Handlers;

use App\Models\VehiclePosition;
use App\Models\Geofence;
use App\Models\Trip;
use App\Models\GeofenceTransition;
use App\Data\SpatialStateResult;
use App\Enums\SpatialPresenceState;
use App\Events\BusEnteredGeofence;
use App\Events\BusExitedGeofence;
use App\Events\BusEnteredDepot;
use App\Events\BusExitedDepot;

class DepotGeofenceHandler implements GeofenceHandlerInterface
{
    public function handle(VehiclePosition $position, Geofence $geofence, SpatialStateResult $result, ?Trip $trip): void
    {
        $bus = $position->bus;
        if (!$bus) {
            return;
        }

        $now = now();
        $tripId = $trip?->id;

        if ($result->state === SpatialPresenceState::ENTERING) {
            // 1. Log transition entry
            GeofenceTransition::create([
                'bus_id' => $bus->id,
                'trip_id' => $tripId,
                'geofence_id' => $geofence->id,
                'entered_at' => $now,
            ]);

            // 2. Dispatch events
            event(new BusEnteredGeofence($bus->id, $tripId, $geofence->id, 'DEPOT'));
            event(new BusEnteredDepot($bus, $geofence, $result->distance, $now, $position));
        } elseif ($result->state === SpatialPresenceState::OUTSIDE) {
            // Resolve active transition history
            $activeTransition = GeofenceTransition::where('bus_id', $bus->id)
                ->where('geofence_id', $geofence->id)
                ->whereNull('exited_at')
                ->orderBy('entered_at', 'desc')
                ->first();

            if ($activeTransition) {
                $duration = $now->timestamp - $activeTransition->entered_at->timestamp;
                $activeTransition->update([
                    'exited_at' => $now,
                    'duration_seconds' => max(0, $duration),
                ]);

                // Dispatch events
                event(new BusExitedGeofence($bus->id, $tripId, $geofence->id, 'DEPOT'));
                event(new BusExitedDepot($bus, $geofence, $result->distance, $now, $position));
            }
        }
    }
}
