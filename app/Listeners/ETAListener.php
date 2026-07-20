<?php

namespace App\Listeners;

use App\Events\PositionUpdated;
use App\Events\ETAUpdated;
use App\Models\Trip;
use App\Models\TripProgress;
use App\Services\Routing\ETAEngine;
use App\Services\ValueObjects\Coordinate;
use Illuminate\Support\Facades\Log;

class ETAListener
{
    public function __construct(protected ETAEngine $engine) {}

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

            $trip = Trip::with(['route.stops' => function ($q) {
                $q->orderBy('sequence');
            }])->find($position->trip_id);

            if (!$trip || !$trip->route || empty($trip->route->polyline_coordinates)) {
                Log::warning('[GPS_TRACE] L-ETA SKIP - Missing trip/route/polyline', [
                    'trip_found'       => (bool) $trip,
                    'route_found'      => (bool) ($trip?->route),
                    'has_polyline'     => !empty($trip?->route?->polyline_coordinates),
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

            $completedCount  = $progress->completed_stops_count;
            $stops           = $trip->route->stops;
            $upcomingStops   = [];

            for ($i = $completedCount; $i < count($stops); $i++) {
                $upcomingStops[] = [
                    'id'       => $stops[$i]->id,
                    'lat'      => $stops[$i]->lat,
                    'lng'      => $stops[$i]->lng,
                    'name'     => $stops[$i]->name,
                    'sequence' => $stops[$i]->sequence,
                ];
            }

            if (empty($upcomingStops)) {
                $progress->update(['upcoming_etas' => []]);
                Log::info('[GPS_TRACE] L-ETA SKIP - No upcoming stops');
                return;
            }

            $coord = new Coordinate($position->lat, $position->lng);
            $etas  = $this->engine->calculateETAs(
                $position->trip_id,
                $coord,
                $trip->route->polyline_coordinates,
                $upcomingStops,
                $position->speed
            );

            $serialized = array_map(fn($item) => $item->toArray(), $etas);
            $progress->update(['upcoming_etas' => $serialized]);
            event(new ETAUpdated($position->trip_id, $serialized));

            Log::info('[GPS_TRACE] L-ETA2 - ETAListener complete', [
                'position_id'   => $position->id,
                'etas_computed' => count($serialized),
            ]);

        } catch (\Throwable $e) {
            Log::error('[GPS_TRACE] L-ETA-EXCEPTION - ETAListener failed', [
                'position_id' => $event->position->id,
                'exception'   => get_class($e),
                'message'     => $e->getMessage(),
                'file'        => $e->getFile(),
                'line'        => $e->getLine(),
            ]);
            // Do not re-throw — ETA failure must not kill the telemetry pipeline
        }
    }
}
