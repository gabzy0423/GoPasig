<?php

namespace App\Listeners;

use App\Events\PositionUpdated;
use App\Services\Spatial\SpatialContextResolver;
use App\Services\Spatial\SpatialMonitoringEngine;
use Illuminate\Support\Facades\Log;

class SpatialMonitoringListener
{
    public function __construct(
        protected SpatialContextResolver $resolver,
        protected SpatialMonitoringEngine $engine
    ) {}

    /**
     * Handle PositionUpdated telemetry events.
     * [GPS_TRACE] TEMPORARY INSTRUMENTATION — REMOVE AFTER INVESTIGATION
     */
    public function handle(PositionUpdated $event): void
    {
        Log::info('[GPS_TRACE] L - SpatialMonitoringListener::handle started', [
            'position_id' => $event->position->id,
            'bus_id'      => $event->position->bus_id,
            'trip_id'     => $event->position->trip_id,
        ]);

        try {
            $context = $this->resolver->resolve($event->position);

            Log::info('[GPS_TRACE] L2 - SpatialContextResolver resolved', [
                'position_id'      => $event->position->id,
                'nearby_geofences' => count($context->nearbyGeofences ?? []),
                'has_trip'         => (bool) $context->trip,
                'has_corridor'     => (bool) $context->corridor,
            ]);

            $this->engine->process($event->position, $context);

            Log::info('[GPS_TRACE] L3 - SpatialMonitoringEngine::process complete', [
                'position_id' => $event->position->id,
            ]);

        } catch (\Throwable $e) {
            Log::error('[GPS_TRACE] L-EXCEPTION - SpatialMonitoringListener failed', [
                'position_id' => $event->position->id,
                'exception'   => get_class($e),
                'message'     => $e->getMessage(),
                'file'        => $e->getFile(),
                'line'        => $e->getLine(),
            ]);
            // Do not re-throw — spatial monitoring failure must not kill the telemetry pipeline
        }
    }
}
