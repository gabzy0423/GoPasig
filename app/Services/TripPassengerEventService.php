<?php

namespace App\Services;

use App\Models\RouteVariantStop;
use App\Models\Trip;
use App\Models\TripPassengerEvent;

class TripPassengerEventService
{
    public function record(
        Trip $trip,
        string $eventType,
        int $passengerDelta,
        int $onboardAfter,
        ?RouteVariantStop $stop = null,
        ?string $requestId = null
    ): TripPassengerEvent {
        if (! in_array($eventType, [
            TripPassengerEvent::TYPE_BOARDED,
            TripPassengerEvent::TYPE_ALIGHTED,
        ], true)) {
            throw new \InvalidArgumentException('Unsupported passenger event type.');
        }

        if ($passengerDelta < 1) {
            throw new \InvalidArgumentException('Passenger event delta must be positive.');
        }

        $routeVariantStopId = $stop
            && $trip->route_variant_id
            && (int) $stop->route_variant_id === (int) $trip->route_variant_id
                ? $stop->id
                : null;

        return TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'bus_id' => $trip->bus_id,
            'route_id' => $trip->route_id,
            'route_variant_stop_id' => $routeVariantStopId,
            'request_id' => $requestId,
            'event_type' => $eventType,
            'passenger_delta' => $passengerDelta,
            'onboard_after' => max(0, $onboardAfter),
            'recorded_at' => now(),
        ]);
    }
}
