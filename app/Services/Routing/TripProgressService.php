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
    public function __construct(protected AuthoritativeRouteResolver $routeResolver) {}

    /**
     * Update progression metrics for a trip using the latest vehicle position.
     * Implements entry/exit hysteresis stop detection.
     */
    public function updateProgress(int $tripId, Coordinate $position): TripProgressResult
    {
        $trip = Trip::findOrFail($tripId);
        $plan = $this->routeResolver->resolveForTrip($trip);
        $stops = $plan->orderedStops->values();
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

        $entryRadius = (float) config('fleet.stops.entry_radius_meters', 30.0);
        $exitRadius = (float) config('fleet.stops.exit_radius_meters', 45.0);

        $nextStopIndex = $progress->completed_stops_count;
        $nextStop = ($nextStopIndex < $totalStops) ? $stops[$nextStopIndex] : null;

        if ($nextStop) {
            $stopCoord = new Coordinate((float)$nextStop->lat, (float)$nextStop->lng);
            $distToNext = $geospatial->calculateDistance($position, $stopCoord);

            if ($distToNext <= $entryRadius) {
                $legacyStopId = $this->legacyStopId($nextStop);
                $variantStopId = $this->variantStopId($nextStop, $plan->usesVariant());

                $progress->last_completed_stop_id = $legacyStopId;
                $progress->last_completed_route_variant_stop_id = $variantStopId;
                $progress->current_stop_id = $legacyStopId;
                $progress->current_route_variant_stop_id = $variantStopId;
                $progress->completed_stops_count += 1;
                $progress->remaining_stops_count = max(0, $totalStops - $progress->completed_stops_count);
                $progress->trip_percentage = $totalStops > 0
                    ? round(($progress->completed_stops_count / $totalStops) * 100, 1)
                    : 0.0;

                $this->recordArrival($tripId, $nextStop, $plan->usesVariant());

                event(new StopReached($tripId, $legacyStopId ?? $variantStopId, 'GPS'));

                if ($progress->completed_stops_count === $totalStops) {
                    $trip->update(['status' => 'completed', 'ended_at' => now()]);
                    event(new TripCompleted($tripId));
                }

                $nextStopIndex = $progress->completed_stops_count;
                $nextStop = ($nextStopIndex < $totalStops) ? $stops[$nextStopIndex] : null;
            }
        }

        $lastCompletedStop = $this->findLastCompletedStop($stops, $progress, $plan->usesVariant());
        if ($lastCompletedStop && ($progress->current_stop_id || $progress->current_route_variant_stop_id)) {
            $lastCoord = new Coordinate((float)$lastCompletedStop->lat, (float)$lastCompletedStop->lng);
            $distToLast = $geospatial->calculateDistance($position, $lastCoord);

            if ($distToLast > $exitRadius) {
                $progress->current_stop_id = null;
                $progress->current_route_variant_stop_id = null;

                $this->recordDeparture($tripId, $lastCompletedStop, $plan->usesVariant());

                event(new StopDeparted($tripId, $this->legacyStopId($lastCompletedStop) ?? $this->variantStopId($lastCompletedStop, $plan->usesVariant())));
            }
        }

        $progress->next_stop_id = $nextStop ? $this->legacyStopId($nextStop) : null;
        $progress->next_route_variant_stop_id = $nextStop ? $this->variantStopId($nextStop, $plan->usesVariant()) : null;
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

    private function legacyStopId(object $stop): ?int
    {
        if (method_exists($stop, 'getAttributes') && array_key_exists('canonical_stop_id', $stop->getAttributes())) {
            $canonicalStopId = $stop->getAttribute('canonical_stop_id');

            return $canonicalStopId !== null ? (int) $canonicalStopId : null;
        }

        return isset($stop->id) ? (int) $stop->id : null;
    }

    private function variantStopId(object $stop, bool $usesVariant): ?int
    {
        return $usesVariant ? (int) $stop->id : null;
    }

    private function recordArrival(int $tripId, object $stop, bool $usesVariant): void
    {
        if ($usesVariant) {
            StopArrival::updateOrCreate(
                ['trip_id' => $tripId, 'route_variant_stop_id' => $stop->id],
                [
                    'stop_id' => $this->legacyStopId($stop),
                    'arrival_time' => now(),
                    'arrival_source' => 'GPS',
                ]
            );

            return;
        }

        StopArrival::updateOrCreate(
            ['trip_id' => $tripId, 'stop_id' => $stop->id],
            ['arrival_time' => now(), 'arrival_source' => 'GPS']
        );
    }

    private function recordDeparture(int $tripId, object $stop, bool $usesVariant): void
    {
        $arrival = $usesVariant
            ? StopArrival::where('trip_id', $tripId)->where('route_variant_stop_id', $stop->id)->first()
            : StopArrival::where('trip_id', $tripId)->where('stop_id', $stop->id)->first();

        if ($arrival && !$arrival->departure_time) {
            $arrival->update(['departure_time' => now()]);
        }
    }

    private function findLastCompletedStop($stops, TripProgress $progress, bool $usesVariant): ?object
    {
        if ($usesVariant) {
            return $stops->firstWhere('id', $progress->last_completed_route_variant_stop_id);
        }

        return $stops->firstWhere('id', $progress->last_completed_stop_id);
    }
}
