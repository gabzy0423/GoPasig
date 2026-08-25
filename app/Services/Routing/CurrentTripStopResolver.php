<?php

namespace App\Services\Routing;

use App\Enums\GpsSessionStatus;
use App\Enums\TripStatus;
use App\Models\RouteVariantStop;
use App\Models\StopArrival;
use App\Models\Trip;
use App\Models\TripProgress;
use App\Models\VehiclePosition;
use App\Services\Contracts\GeospatialServiceInterface;
use App\Services\GpsQualityService;
use App\Services\ValueObjects\Coordinate;
use Carbon\CarbonInterface;

class CurrentTripStopResolver
{
    public function __construct(
        protected GeospatialServiceInterface $geospatial
    ) {}

    public function resolve(Trip|int $trip): ?RouteVariantStop
    {
        $trip = Trip::with('bus')->find($trip instanceof Trip ? $trip->id : $trip);

        if (! $trip
            || $trip->status !== TripStatus::ONGOING->value
            || $trip->gps_session !== GpsSessionStatus::ACTIVE->value
            || ! $trip->started_at
            || ! $trip->route_variant_id
            || ! $trip->bus_id
            || $trip->bus?->status !== 'operating') {
            return null;
        }

        $position = VehiclePosition::query()
            ->where('bus_id', $trip->bus_id)
            ->where('trip_id', $trip->id)
            ->first();

        if (! $position
            || ! $this->isFresh($position->last_updated_at)
            || ! $this->isFresh($position->last_gps_fix_at)
            || ! $this->hasTrustedQuality($position->gps_quality_state)) {
            return null;
        }

        $progress = TripProgress::where('trip_id', $trip->id)->first();
        if (! $progress?->current_route_variant_stop_id) {
            return null;
        }

        $stop = RouteVariantStop::query()
            ->whereKey($progress->current_route_variant_stop_id)
            ->where('route_variant_id', $trip->route_variant_id)
            ->first();

        if (! $stop || $stop->lat === null || $stop->lng === null) {
            return null;
        }

        $hasOpenArrival = StopArrival::query()
            ->where('trip_id', $trip->id)
            ->where('route_variant_stop_id', $stop->id)
            ->whereIn('arrival_source', ['GPS', 'DEVELOPER'])
            ->whereNull('departure_time')
            ->exists();

        if (! $hasOpenArrival) {
            return null;
        }

        $distanceMeters = $this->geospatial->calculateDistance(
            new Coordinate((float) $position->lat, (float) $position->lng),
            new Coordinate((float) $stop->lat, (float) $stop->lng)
        );

        $exitRadius = (float) config('fleet.stops.exit_radius_meters', 45.0);

        return $distanceMeters <= $exitRadius ? $stop : null;
    }

    private function isFresh(?CarbonInterface $observedAt): bool
    {
        if (! $observedAt) {
            return false;
        }

        $maxAgeSeconds = max(1, min(
            (int) config('fleet.gps.offline_timeout_seconds', 300),
            (int) config('fleet.gps_quality.stale_fix_age_seconds', 300)
        ));
        $now = now();

        return $observedAt->gte($now->copy()->subSeconds($maxAgeSeconds))
            && $observedAt->lte($now->copy()->addSeconds(30));
    }

    private function hasTrustedQuality(?string $state): bool
    {
        $state = strtoupper((string) $state);

        if ($state === 'DEVELOPER') {
            return config('app.env') === 'local';
        }

        return in_array($state, [
            GpsQualityService::STATE_GOOD,
            GpsQualityService::STATE_DEGRADED,
        ], true);
    }
}
