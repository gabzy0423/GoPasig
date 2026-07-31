<?php

namespace App\Listeners;

use App\Events\PositionUpdated;
use App\Events\RouteDeviationDetected;
use App\Models\Trip;
use App\Models\TripProgress;
use App\Models\RouteDeviation;
use App\Services\Routing\AuthoritativeRouteResolver;
use App\Services\Routing\RouteAdherenceService;
use App\Services\ValueObjects\Coordinate;

class AdherenceListener
{
    public function __construct(
        protected RouteAdherenceService $service,
        protected AuthoritativeRouteResolver $routeResolver
    ) {}

    public function handle(PositionUpdated $event): void
    {
        $position = $event->position;
        if (!$position->trip_id) {
            return;
        }

        $trip = Trip::find($position->trip_id);
        if (!$trip) {
            return;
        }

        $plan = $this->routeResolver->resolveForTrip($trip);
        if (empty($plan->polylineCoordinates)) {
            return;
        }

        $coord = new Coordinate($position->lat, $position->lng);
        $deviation = $this->service->checkAdherence($position->trip_id, $coord, $plan->polylineCoordinates);

        $progress = TripProgress::where('trip_id', $position->trip_id)->first();

        if ($deviation->isDeviated) {
            // Log RouteDeviation
            RouteDeviation::create([
                'trip_id' => $position->trip_id,
                'lat' => $position->lat,
                'lng' => $position->lng,
                'distance_meters' => $deviation->distanceMeters,
                'severity' => $deviation->severity,
                'detected_at' => now(),
            ]);

            if ($progress) {
                $progress->update(['route_adherence' => $deviation->severity . ' Deviation']);
            }

            event(new RouteDeviationDetected(
                $position->trip_id,
                $position->lat,
                $position->lng,
                $deviation->distanceMeters,
                $deviation->severity
            ));
        } else {
            // Resolve active deviation log
            RouteDeviation::where('trip_id', $position->trip_id)
                ->whereNull('resolved_at')
                ->update(['resolved_at' => now()]);

            if ($progress) {
                $progress->update(['route_adherence' => 'On Route']);
            }
        }
    }
}

