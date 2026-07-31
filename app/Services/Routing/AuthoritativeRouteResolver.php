<?php

namespace App\Services\Routing;

use App\Data\AuthoritativeRoutePlan;
use App\Models\RouteVariant;
use App\Models\Trip;

class AuthoritativeRouteResolver
{
    public function resolveForTrip(Trip $trip): AuthoritativeRoutePlan
    {
        $trip->loadMissing('route');

        if ($trip->route_variant_id) {
            $variant = RouteVariant::with(['route', 'stops'])
                ->find($trip->route_variant_id);

            if ($variant) {
                return new AuthoritativeRoutePlan(
                    route: $variant->route,
                    variant: $variant,
                    polylineCoordinates: $variant->polyline_coordinates ?: [],
                    orderedStops: $variant->stops,
                    source: 'route_variant'
                );
            }
        }

        $route = $trip->route;
        if (!$route) {
            $route = \App\Models\Route::findOrFail($trip->route_id);
        }

        $route->loadMissing(['stops' => fn ($query) => $query->orderBy('sequence')]);

        return new AuthoritativeRoutePlan(
            route: $route,
            variant: null,
            polylineCoordinates: $route->polyline_coordinates ?: [],
            orderedStops: $route->stops,
            source: 'legacy_route'
        );
    }
}
