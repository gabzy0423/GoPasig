<?php

namespace App\Services;

use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use Illuminate\Support\Collection;

class RouteMapGeometryService
{
    private const MAP_GEOMETRY_STATUSES = ['valid', 'authoritative', 'active', 'schematic'];

    public function forRoute(Route $route, Collection $activeTrips): array
    {
        if ($route->isCanonicalProduction()) {
            $variants = RouteVariant::query()
                ->where('route_id', $route->id)
                ->orderBy('id')
                ->get();

            return $this->variantMapPayload($variants);
        }

        $trips = $activeTrips->where('route_id', $route->id);
        $variantTrips = $trips->filter(fn ($trip) => $trip->route_variant_id !== null);

        if ($variantTrips->isEmpty()) {
            return [
                'source' => 'legacy_route',
                'geometry_status' => 'legacy',
                'route_variant_ids' => [],
                'variant_geometries' => [],
                'polyline_coordinates' => $route->polyline_coordinates ?: [],
            ];
        }

        $variants = $variantTrips->map(fn ($trip) => $trip->routeVariant)
            ->filter()
            ->unique('id')
            ->values();

        return $this->variantMapPayload($variants);
    }

    /**
     * Geometry allowed for operational consumers such as dispatch and ETA.
     */
    public function operationalPolyline(RouteVariant $variant): array
    {
        $status = strtolower((string) $variant->geometry_status);
        $coordinates = $variant->polyline_coordinates ?: [];

        return in_array($status, RouteVariantSelectionService::USABLE_GEOMETRY_STATUSES, true)
            && is_array($coordinates)
            && count($coordinates) >= 2
            ? $coordinates
            : [];
    }

    /**
     * Geometry allowed only for drawing static map lines.
     */
    public function mapPolyline(RouteVariant $variant): array
    {
        $status = strtolower((string) $variant->geometry_status);
        $coordinates = $variant->polyline_coordinates ?: [];

        return in_array($status, self::MAP_GEOMETRY_STATUSES, true)
            && is_array($coordinates)
            && count($coordinates) >= 2
            ? $coordinates
            : [];
    }

    /**
     * Backward-compatible operational name used by existing callers.
     */
    public function authoritativePolyline(RouteVariant $variant): array
    {
        return $this->operationalPolyline($variant);
    }

    private function variantMapPayload(Collection $variants): array
    {
        $stopsByVariant = RouteVariantStop::query()
            ->whereIn('route_variant_id', $variants->pluck('id')->values())
            ->orderBy('sequence')
            ->get()
            ->groupBy('route_variant_id');

        $geometries = $variants->map(function (RouteVariant $variant) use ($stopsByVariant) {
            return [
                'route_variant_id' => $variant->id,
                'direction' => $variant->direction,
                'geometry_status' => $variant->geometry_status,
                'polyline_coordinates' => $this->mapPolyline($variant),
                'stops' => $stopsByVariant->get($variant->id, collect())
                    ->map(fn (RouteVariantStop $stop) => [
                        'id' => $stop->id,
                        'name' => $stop->name,
                        'lat' => $stop->lat,
                        'lng' => $stop->lng,
                        'sequence' => $stop->sequence,
                        'stop_type' => $stop->stop_type,
                        'radius_meters' => $stop->radius_meters,
                    ])
                    ->values()
                    ->all(),
            ];
        })->values();

        $hasPending = $geometries->contains(fn (array $geometry) => empty($geometry['polyline_coordinates']));

        return [
            'source' => 'route_variant',
            'geometry_status' => $hasPending ? 'pending' : 'available',
            'route_variant_ids' => $geometries->pluck('route_variant_id')->values()->all(),
            'variant_geometries' => $geometries->all(),
            'polyline_coordinates' => [],
        ];
    }
}
