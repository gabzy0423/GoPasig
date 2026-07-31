<?php

namespace App\Services;

use App\Models\Route;
use App\Models\RouteVariant;
use Illuminate\Support\Collection;

class RouteMapGeometryService
{
    private const OFFICIAL_ROUTE_NAMES = ['Route 1', 'Route 2', 'Route 3'];
    public function forRoute(Route $route, Collection $activeTrips): array
    {
        $trips = $activeTrips->where('route_id', $route->id);
        $variantTrips = $trips->filter(fn ($trip) => $trip->route_variant_id !== null);

        if ($variantTrips->isEmpty()) {
            if (in_array($route->name, self::OFFICIAL_ROUTE_NAMES, true)) {
                return [
                    'source' => 'route_variant',
                    'geometry_status' => 'pending',
                    'route_variant_ids' => [],
                    'variant_geometries' => [],
                    'polyline_coordinates' => [],
                ];
            }

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
        $geometries = $variants->map(function (RouteVariant $variant) {
            return [
                'route_variant_id' => $variant->id,
                'direction' => $variant->direction,
                'geometry_status' => $variant->geometry_status,
                'polyline_coordinates' => $this->authoritativePolyline($variant),
            ];
        })->values()->all();

        $hasPending = $variants->contains(fn (RouteVariant $variant) => empty($this->authoritativePolyline($variant)));

        return [
            'source' => 'route_variant',
            'geometry_status' => $hasPending ? 'pending' : 'available',
            'route_variant_ids' => $variants->pluck('id')->values()->all(),
            'variant_geometries' => $geometries,
            // Explicit variants never fall back to the operational route geometry.
            'polyline_coordinates' => [],
        ];
    }

    public function authoritativePolyline(RouteVariant $variant): array
    {
        $status = strtolower((string) $variant->geometry_status);
        $coordinates = $variant->polyline_coordinates ?: [];

        return in_array($status, RouteVariantSelectionService::USABLE_GEOMETRY_STATUSES, true)
            && is_array($coordinates)
            && count($coordinates) >= 2
            ? $coordinates
            : [];
    }
}
