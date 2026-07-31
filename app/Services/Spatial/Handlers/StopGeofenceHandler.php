<?php

namespace App\Services\Spatial\Handlers;

use App\Models\VehiclePosition;
use App\Models\Geofence;
use App\Models\Trip;
use App\Models\StopArrival;
use App\Models\TripProgress;
use App\Models\GeofenceTransition;
use App\Services\Routing\AuthoritativeRouteResolver;
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
    public function __construct(protected AuthoritativeRouteResolver $routeResolver) {}

    public function handle(VehiclePosition $position, Geofence $geofence, SpatialStateResult $result, ?Trip $trip): void
    {
        $bus = $position->bus;
        if (!$bus) {
            return;
        }

        $now = now();
        $tripId = $trip?->id;
        $plan = $trip ? $this->routeResolver->resolveForTrip($trip) : null;
        $stop = $plan ? $plan->orderedStops->firstWhere('name', $geofence->name) : null;

        if ($result->state === SpatialPresenceState::ENTERING) {
            GeofenceTransition::create([
                'bus_id' => $bus->id,
                'trip_id' => $tripId,
                'geofence_id' => $geofence->id,
                'entered_at' => $now,
            ]);

            event(new BusEnteredGeofence($bus->id, $tripId, $geofence->id, 'STOP'));

            if ($trip && $plan && $stop) {
                $this->recordArrival($tripId, $stop, $plan->usesVariant(), $now);

                $progress = TripProgress::firstOrCreate(['trip_id' => $tripId]);
                $stops = $plan->orderedStops->values();
                $totalStops = count($stops);
                $sequenceIndex = $stops->pluck('id')->search($stop->id);
                $completedCount = ($sequenceIndex !== false) ? ($sequenceIndex + 1) : ($progress->completed_stops_count + 1);

                $progress->update([
                    'last_completed_stop_id' => $this->legacyStopId($stop),
                    'last_completed_route_variant_stop_id' => $this->variantStopId($stop, $plan->usesVariant()),
                    'current_stop_id' => $this->legacyStopId($stop),
                    'current_route_variant_stop_id' => $this->variantStopId($stop, $plan->usesVariant()),
                    'completed_stops_count' => $completedCount,
                    'remaining_stops_count' => max(0, $totalStops - $completedCount),
                    'trip_percentage' => $totalStops > 0 ? round(($completedCount / $totalStops) * 100, 1) : 0.0,
                ]);

                event(new BusEnteredStop($bus, $trip, $stop, $result->distance, $now, $position));
                event(new StopReached($tripId, $this->legacyStopId($stop) ?? $this->variantStopId($stop, $plan->usesVariant()), 'GPS'));
            }
        } elseif ($result->state === SpatialPresenceState::OUTSIDE) {
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

                event(new BusExitedGeofence($bus->id, $tripId, $geofence->id, 'STOP'));

                if ($trip && $plan && $stop) {
                    $this->recordDeparture($tripId, $stop, $plan->usesVariant(), $now);

                    $progress = TripProgress::where('trip_id', $tripId)->first();
                    if ($progress && $this->isCurrentStop($progress, $stop, $plan->usesVariant())) {
                        $progress->update([
                            'current_stop_id' => null,
                            'current_route_variant_stop_id' => null,
                        ]);
                    }

                    event(new BusExitedStop($bus, $trip, $stop, $result->distance, $now, $position));
                    event(new StopDeparted($tripId, $this->legacyStopId($stop) ?? $this->variantStopId($stop, $plan->usesVariant())));
                }
            }
        }
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

    private function recordArrival(int $tripId, object $stop, bool $usesVariant, $now): void
    {
        if ($usesVariant) {
            StopArrival::updateOrCreate(
                ['trip_id' => $tripId, 'route_variant_stop_id' => $stop->id],
                [
                    'stop_id' => $this->legacyStopId($stop),
                    'arrival_time' => $now,
                    'arrival_source' => 'GPS',
                ]
            );

            return;
        }

        StopArrival::updateOrCreate(
            ['trip_id' => $tripId, 'stop_id' => $stop->id],
            ['arrival_time' => $now, 'arrival_source' => 'GPS']
        );
    }

    private function recordDeparture(int $tripId, object $stop, bool $usesVariant, $now): void
    {
        $arrival = $usesVariant
            ? StopArrival::where('trip_id', $tripId)->where('route_variant_stop_id', $stop->id)->first()
            : StopArrival::where('trip_id', $tripId)->where('stop_id', $stop->id)->first();

        if ($arrival && !$arrival->departure_time) {
            $arrival->update(['departure_time' => $now]);
        }
    }

    private function isCurrentStop(TripProgress $progress, object $stop, bool $usesVariant): bool
    {
        if ($usesVariant) {
            return (int) $progress->current_route_variant_stop_id === (int) $stop->id;
        }

        return (int) $progress->current_stop_id === (int) $stop->id;
    }
}
