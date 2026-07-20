<?php

namespace App\Repositories\Contracts;

use App\Services\ValueObjects\Polyline;

interface RouteGeometryRepositoryInterface
{
    /**
     * Get route polyline coordinates from Cache or DB.
     */
    public function getRoutePolyline(int|string $routeId): Polyline;

    /**
     * Persist polyline to the routes table and increment geometry_version.
     */
    public function persistPolyline(int $routeId, Polyline $polyline): void;

    /**
     * Clear route geometry cache.
     */
    public function clearCache(int|string $routeId): void;

    /**
     * Clear route geometry metrics cache.
     */
    public function clearMetrics(int|string $routeId): void;

    /**
     * Clear both geometry and metrics caches.
     */
    public function clearAll(int|string $routeId): void;

    /**
     * Cache the pre-computed metrics array.
     */
    public function storeMetrics(int|string $routeId, array $metrics): void;

    /**
     * Retrieve cached metrics or null if not found.
     */
    public function getMetrics(int|string $routeId): ?array;
}
