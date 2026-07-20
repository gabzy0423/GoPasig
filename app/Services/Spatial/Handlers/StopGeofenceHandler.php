<?php

namespace App\Services\Spatial\Handlers;

use App\Models\VehiclePosition;
use App\Models\Geofence;
use App\Models\Trip;
use App\Models\Stop;
use App\Models\StopArrival;
use App\Models\TripProgress;
use App\Models\GeofenceTransition;
use App\Data\SpatialStateResult;
use App\Enums\SpatialPresenceState;
use App\Events\BusEnteredGeofence;
use App\Events\BusExitedGeofence;
use App\Events\BusEnteredStop;
use App\Events\BusExitedStop;
use App\Events\StopReached;
use App\Events\StopDeparted;

class StopGeofenceHandler implements GeofenceHandlerInterface
{
    public function handle(VehiclePosition $position, Geofence $geofence, SpatialStateResult $result, ?Trip $trip): void
    {
        $bus = $position->bus;
        if (!$bus) {
            return;
        }

        $now = now();
        $tripId = $trip?->id;

        // Find matching Stop for this geofence and route
        $stop = null;
        if ($trip) {
            $stop = Stop::where('name', $geofence->name)
                ->where('route_id', $trip->route_id)
                ->first();
        }

        if ($result->state === SpatialPresenceState::ENTERING) {
            // 1. Transition History Entry
            GeofenceTransition::create([
                'bus_id' => $bus->id,
                'trip_id' => $tripId,
                'geofence_id' => $geofence->id,
                'entered_at' => $now,
            ]);

            // 2. Dispatch generic geofence entered event
            event(new BusEnteredGeofence($bus->id, $tripId, $geofence->id, 'STOP'));

            if ($trip && $stop) {
                // 3. Update StopArrival entry
                StopArrival::updateOrCreate(
                    ['trip_id' => $tripId, 'stop_id' => $stop->id],
                    ['arrival_time' => $now, 'arrival_source' => 'GPS']
                );

                // 4. Update TripProgress
                $progress = TripProgress::firstOrCreate(['trip_id' => $tripId]);
                $stops = $trip->route->stops()->orderBy('sequence')->get();
                $totalStops = count($stops);

                // Find index of current stop in route sequence
                $sequenceIndex = $stops->pluck('id')->search($stop->id);
                $completedCount = ($sequenceIndex !== false) ? ($sequenceIndex + 1) : ($progress->completed_stops_count + 1);

                $progress->update([
                    'last_completed_stop_id' => $stop->id,
                    'current_stop_id' => $stop->id,
                    'completed_stops_count' => $completedCount,
                    'remaining_stops_count' => max(0, $totalStops - $completedCount),
                    'trip_percentage' => $totalStops > 0 ? round(($completedCount / $totalStops) * 100, 1) : 0.0,
                ]);

                // 5. Dispatch specific events
                event(new BusEnteredStop($bus, $trip, $stop, $result->distance, $now, $position));
                event(new StopReached($tripId, $stop->id, 'GPS'));
            }
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

                // Dispatch generic geofence exited event
                event(new BusExitedGeofence($bus->id, $tripId, $geofence->id, 'STOP'));

                if ($trip && $stop) {
                    // Update StopArrival exit
                    $arrival = StopArrival::where('trip_id', $tripId)
                        ->where('stop_id', $stop->id)
                        ->first();
                    if ($arrival && !$arrival->departure_time) {
                        $arrival->update(['departure_time' => $now]);
                    }

                    // Update TripProgress
                    $progress = TripProgress::where('trip_id', $tripId)->first();
                    if ($progress && $progress->current_stop_id === $stop->id) {
                        $progress->update(['current_stop_id' => null]);
                    }

                    // Dispatch specific events
                    event(new BusExitedStop($bus, $trip, $stop, $result->distance, $now, $position));
                    event(new StopDeparted($tripId, $stop->id));
                }
            }
        }
    }
}
