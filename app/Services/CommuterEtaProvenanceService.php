<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\RouteVariant;
use App\Models\Trip;
use App\Models\TripProgress;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CommuterEtaProvenanceService
{
    public const AUTHORITATIVE = 'authoritative';
    public const LEGACY_BUS_ETA = 'legacy_bus_eta';
    public const MISSING_GEOMETRY = 'missing_geometry';
    public const MISSING_TRIP_PROGRESS = 'missing_trip_progress';
    public const UNAVAILABLE = 'unavailable';

    public function forBus(Bus $bus, ?int $targetStopId = null, ?int $targetRouteVariantStopId = null): object
    {
        $legacyEta = $bus->eta !== null ? (int) $bus->eta : null;
        $trip = $this->activeTrip($bus);

        if (! $trip) {
            return $this->legacyOrUnavailable($legacyEta, 'No active trip ETA');
        }

        if (! $trip->route_variant_id || ! $trip->routeVariant) {
            return $this->legacyOrUnavailable($legacyEta, 'ETA unavailable');
        }

        if (! $this->hasUsableAuthoritativeGeometry($trip->routeVariant)) {
            return $this->legacyOrUnavailable(
                $legacyEta,
                'ETA unavailable - official route data pending',
                self::MISSING_GEOMETRY
            );
        }

        $progress = TripProgress::where('trip_id', $trip->id)->first();
        if (! $progress) {
            return $this->legacyOrUnavailable($legacyEta, 'Awaiting trip progress', self::MISSING_TRIP_PROGRESS);
        }

        $entry = $this->matchingUpcomingEta($progress, $targetStopId, $targetRouteVariantStopId, $trip->routeVariant);
        if (! $entry) {
            return $this->legacyOrUnavailable($legacyEta, 'Awaiting trip progress', self::MISSING_TRIP_PROGRESS);
        }

        $minutes = $this->minutesUntil($entry['eta_timestamp'] ?? null);
        if ($minutes === null) {
            return $this->legacyOrUnavailable($legacyEta, 'Awaiting trip progress', self::MISSING_TRIP_PROGRESS);
        }

        return (object) [
            'state' => self::AUTHORITATIVE,
            'minutes' => $minutes,
            'label' => 'ETA: '.$minutes.' min',
            'short_label' => $minutes.' min',
            'description' => 'Authoritative ETA',
            'is_authoritative' => true,
            'target' => $targetStopId || $targetRouteVariantStopId ? 'stop' : 'next_stop',
        ];
    }

    public function forBuses($buses, ?int $targetStopId = null, ?int $targetRouteVariantStopId = null): Collection
    {
        return collect($buses)->mapWithKeys(fn (Bus $bus) => [
            $bus->id => $this->forBus($bus, $targetStopId, $targetRouteVariantStopId),
        ]);
    }

    private function activeTrip(Bus $bus): ?Trip
    {
        return Trip::with('routeVariant.stops')
            ->where('bus_id', $bus->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->first();
    }

    private function hasUsableAuthoritativeGeometry(RouteVariant $variant): bool
    {
        $polyline = $variant->polyline_coordinates ?: [];
        $status = strtolower((string) ($variant->geometry_status ?? ''));
        $allowedStatuses = array_merge(RouteVariantSelectionService::USABLE_GEOMETRY_STATUSES, ['schematic']);
        if (!in_array($status, $allowedStatuses, true) || count($polyline) < 2) {
            return false;
        }

        $stops = $variant->relationLoaded('stops') ? $variant->stops : $variant->stops()->get();

        return $stops->isNotEmpty()
            && $stops->every(fn ($stop) => $stop->lat !== null && $stop->lng !== null)
            && $stops->every(fn ($stop) => in_array((string) ($stop->coordinate_status ?? 'verified'), ['verified', 'approved'], true));
    }

    private function matchingUpcomingEta(TripProgress $progress, ?int $targetStopId, ?int $targetRouteVariantStopId, RouteVariant $variant): ?array
    {
        $etas = collect($progress->upcoming_etas ?? [])->filter(fn ($eta) => is_array($eta));
        if ($etas->isEmpty()) {
            return null;
        }

        if ($targetStopId) {
            $match = $etas->first(fn ($eta) => (int) ($eta['stop_id'] ?? 0) === $targetStopId);
            if ($match) {
                return $match;
            }
        }

        if ($targetRouteVariantStopId) {
            $variantStop = $variant->stops->firstWhere('id', $targetRouteVariantStopId);
            $canonicalStopId = $variantStop?->canonical_stop_id;
            if ($canonicalStopId) {
                $match = $etas->first(fn ($eta) => (int) ($eta['stop_id'] ?? 0) === (int) $canonicalStopId);
                if ($match) {
                    return $match;
                }
            }
        }

        return $targetStopId || $targetRouteVariantStopId ? null : $etas->first();
    }

    private function minutesUntil(?string $timestamp): ?int
    {
        if (! $timestamp) {
            return null;
        }

        try {
            return max(0, (int) ceil(now()->diffInSeconds(Carbon::parse($timestamp), false) / 60));
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyOrUnavailable(?int $legacyEta, string $unavailableLabel, string $unavailableState = self::UNAVAILABLE): object
    {
        if ($legacyEta !== null) {
            return (object) [
                'state' => self::LEGACY_BUS_ETA,
                'minutes' => $legacyEta,
                'label' => 'Next stop: ~'.$legacyEta.' min',
                'short_label' => '~'.$legacyEta.' min',
                'description' => 'Approximate next-stop ETA',
                'is_authoritative' => false,
                'target' => 'next_stop',
                'blocked_state' => $unavailableState,
                'blocked_label' => $unavailableLabel,
            ];
        }

        return (object) [
            'state' => $unavailableState,
            'minutes' => null,
            'label' => $unavailableLabel,
            'short_label' => 'ETA unavailable',
            'description' => $unavailableLabel,
            'is_authoritative' => false,
            'target' => null,
        ];
    }
}
