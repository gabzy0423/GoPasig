<?php

namespace App\Listeners;

use App\Events\PositionUpdated;
use App\Events\ETAUpdated;
use App\Models\Trip;
use App\Models\TripProgress;
use App\Services\Routing\AuthoritativeRouteResolver;
use App\Services\Routing\ETAEngine;
use App\Services\ValueObjects\Coordinate;
use Illuminate\Support\Facades\Log;

class ETAListener
{
    public function __construct(
        protected ETAEngine $engine,
        protected AuthoritativeRouteResolver $routeResolver
    ) {}

    /**
     * [GPS_TRACE] TEMPORARY INSTRUMENTATION — REMOVE AFTER INVESTIGATION
     */
    public function handle(PositionUpdated $event): void
    {
        Log::info('[GPS_TRACE] L-ETA - ETAListener::handle started', [
            'position_id' => $event->position->id,
            'trip_id'     => $event->position->trip_id,
        ]);

        try {
            $position = $event->position;
            if (!$position->trip_id) {
                Log::info('[GPS_TRACE] L-ETA SKIP - No trip_id on position');
                return;
            }

            $trip = Trip::find($position->trip_id);
            if (!$trip) {
                Log::warning('[GPS_TRACE] L-ETA SKIP - Missing trip', [
                    'trip_id' => $position->trip_id,
                ]);
                return;
            }

            $plan = $this->routeResolver->resolveForTrip($trip);
            if (empty($plan->polylineCoordinates)) {
                Log::warning('[GPS_TRACE] L-ETA SKIP - Missing authoritative polyline', [
                    'trip_id' => $position->trip_id,
                    'route_id' => $trip->route_id,
                    'route_variant_id' => $trip->route_variant_id,
                    'source' => $plan->source,
                ]);
                return;
            }

            $progress = TripProgress::where('trip_id', $position->trip_id)->first();
            if (!$progress) {
                Log::warning('[GPS_TRACE] L-ETA SKIP - No TripProgress record', [
                    'trip_id' => $position->trip_id,
                ]);
                return;
            }

            $completedCount = $progress->completed_stops_count;
            $stops = $plan->orderedStops->values();
            $upcomingStops = [];

            for ($i = $completedCount; $i < count($stops); $i++) {
                $stop = $stops[$i];
                $upcomingStops[] = [
                    'id' => $stop->id,
                    'lat' => $stop->lat,
                    'lng' => $stop->lng,
                    'name' => $stop->name,
                    'sequence' => $stop->sequence,
                    'legacy_stop_id' => $plan->usesVariant() ? $stop->canonical_stop_id : $stop->id,
                    'route_variant_stop_id' => $plan->usesVariant() ? $stop->id : null,
                ];
            }

            if (empty($upcomingStops)) {
                $progress->update(['upcoming_etas' => []]);
                Log::info('[GPS_TRACE] L-ETA SKIP - No upcoming stops');
                return;
            }

            $coord = new Coordinate($position->lat, $position->lng);
            $etas = $this->engine->calculateETAs(
                $position->trip_id,
                $coord,
                $plan->polylineCoordinates,
                $upcomingStops,
                $position->speed
            );

            $serialized = array_map(fn($item) => $item->toArray(), $etas);
            $progress->update(['upcoming_etas' => $serialized]);
            event(new ETAUpdated($position->trip_id, $serialized));

            Log::info('[GPS_TRACE] L-ETA2 - ETAListener complete', [
                'position_id' => $position->id,
                'etas_computed' => count($serialized),
                'route_plan_source' => $plan->source,
            ]);

        } catch (\Throwable $e) {
            Log::error('[GPS_TRACE] L-ETA-EXCEPTION - ETAListener failed', [
                'position_id' => $event->position->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}
