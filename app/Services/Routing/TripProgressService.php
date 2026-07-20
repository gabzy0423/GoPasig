<?php

namespace App\Services\Routing;

use App\Models\Trip;
use App\Models\TripProgress;
use App\Models\StopArrival;
use App\Services\ValueObjects\Coordinate;
use App\Data\TripProgressResult;
use App\Events\StopReached;
use App\Events\StopDeparted;
use App\Events\TripCompleted;

class TripProgressService
{
    /**
     * Update progression metrics for a trip using the latest vehicle position.
     * Implements entry/exit hysteresis stop detection.
     */
    public function updateProgress(int $tripId, Coordinate $position): TripProgressResult
    {
        $trip = Trip::with(['route.stops' => function ($q) {
            $q->orderBy('sequence');
        }])->findOrFail($tripId);

        $stops = $trip->route->stops;
        $totalStops = count($stops);

        $progress = TripProgress::firstOrCreate(
            ['trip_id' => $tripId],
            [
                'completed_stops_count' => 0,
                'remaining_stops_count' => $totalStops,
                'trip_percentage' => 0.0,
                'route_adherence' => 'On Route',
                'current_delay_minutes' => 0,
                'upcoming_etas' => [],
            ]
        );

        $geospatial = app(\App\Services\Contracts\GeospatialServiceInterface::class);

        // Hysteresis configuration limits
        $entryRadius = (float) config('fleet.stops.entry_radius_meters', 30.0);
        $exitRadius = (float) config('fleet.stops.exit_radius_meters', 45.0);

        // Determine target next stop index
        $nextStopIndex = $progress->completed_stops_count;
        $nextStop = ($nextStopIndex < $totalStops) ? $stops[$nextStopIndex] : null;

        // 1. Entry detection
        if ($nextStop) {
            $stopCoord = new Coordinate((float)$nextStop->lat, (float)$nextStop->lng);
            $distToNext = $geospatial->calculateDistance($position, $stopCoord);

            if ($distToNext <= $entryRadius) {
                // Arrived at stop
                $progress->last_completed_stop_id = $nextStop->id;
                $progress->current_stop_id = $nextStop->id;
                $progress->completed_stops_count += 1;
                $progress->remaining_stops_count = max(0, $totalStops - $progress->completed_stops_count);
                $progress->trip_percentage = round(($progress->completed_stops_count / $totalStops) * 100, 1);

                // Record StopArrival entry
                StopArrival::updateOrCreate(
                    ['trip_id' => $tripId, 'stop_id' => $nextStop->id],
                    ['arrival_time' => now(), 'arrival_source' => 'GPS']
                );

                event(new StopReached($tripId, $nextStop->id, 'GPS'));

                // If final stop, complete trip
                if ($progress->completed_stops_count === $totalStops) {
                    $trip->update(['status' => 'completed', 'ended_at' => now()]);
                    event(new TripCompleted($tripId));
                }

                // Advance next stop sequence
                $nextStopIndex = $progress->completed_stops_count;
                $nextStop = ($nextStopIndex < $totalStops) ? $stops[$nextStopIndex] : null;
            }
        }

        // 2. Exit detection
        if ($progress->last_completed_stop_id && $progress->current_stop_id) {
            $lastCompletedStop = $stops->firstWhere('id', $progress->last_completed_stop_id);
            if ($lastCompletedStop) {
                $lastCoord = new Coordinate((float)$lastCompletedStop->lat, (float)$lastCompletedStop->lng);
                $distToLast = $geospatial->calculateDistance($position, $lastCoord);

                if ($distToLast > $exitRadius) {
                    // Departed stop
                    $progress->current_stop_id = null; // moving in between stops

                    // Update StopArrival exit
                    $arrival = StopArrival::where('trip_id', $tripId)
                        ->where('stop_id', $lastCompletedStop->id)
                        ->first();
                    if ($arrival && !$arrival->departure_time) {
                        $arrival->update(['departure_time' => now()]);
                    }

                    event(new StopDeparted($tripId, $lastCompletedStop->id));
                }
            }
        }

        // Set next stop pointer
        $progress->next_stop_id = $nextStop ? $nextStop->id : null;
        $progress->save();

        return new TripProgressResult(
            tripId: $tripId,
            currentStopId: $progress->current_stop_id,
            nextStopId: $progress->next_stop_id,
            lastCompletedStopId: $progress->last_completed_stop_id,
            completedStopsCount: $progress->completed_stops_count,
            remainingStopsCount: $progress->remaining_stops_count,
            tripPercentage: $progress->trip_percentage,
            routeAdherence: $progress->route_adherence,
            currentDelayMinutes: $progress->current_delay_minutes,
            upcomingEtas: $progress->upcoming_etas ?? []
        );
    }
}
