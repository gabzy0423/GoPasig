<?php

namespace App\Repositories;

use App\Repositories\Contracts\RouteGeometryRepositoryInterface;
use App\Services\ValueObjects\Polyline;
use App\Models\Route;
use Illuminate\Support\Facades\Cache;

class RouteGeometryRepository implements RouteGeometryRepositoryInterface
{
    private const GEOMETRY_CACHE_PREFIX = 'route_geometry_';
    private const METRICS_CACHE_PREFIX = 'route_geometry_metrics_';

    public function getRoutePolyline(int|string $routeId): Polyline
    {
        $cacheKey = self::GEOMETRY_CACHE_PREFIX . $routeId;
        $ttl = (int) config('routing.geometry_cache_ttl', 86400);

        $coords = Cache::remember($cacheKey, $ttl, function () use ($routeId) {
            $route = Route::find($routeId);
            return $route ? ($route->polyline_coordinates ?: []) : [];
        });

        return Polyline::fromArray($coords);
    }

    public function persistPolyline(int $routeId, Polyline $polyline): void
    {
        $route = Route::findOrFail($routeId);
        $route->polyline_coordinates = $polyline->toLatLngs();
        $route->geometry_version = ($route->geometry_version ?? 0) + 1;
        $route->save();
    }

    public function clearCache(int|string $routeId): void
    {
        Cache::forget(self::GEOMETRY_CACHE_PREFIX . $routeId);
    }

    public function clearMetrics(int|string $routeId): void
    {
        Cache::forget(self::METRICS_CACHE_PREFIX . $routeId);
    }

    public function clearAll(int|string $routeId): void
    {
        $this->clearCache($routeId);
        $this->clearMetrics($routeId);
    }

    public function storeMetrics(int|string $routeId, array $metrics): void
    {
        $cacheKey = self::METRICS_CACHE_PREFIX . $routeId;
        $ttl = (int) config('routing.geometry_metrics_cache_ttl', 86400);
        Cache::put($cacheKey, $metrics, $ttl);
    }

    public function getMetrics(int|string $routeId): ?array
    {
        $cacheKey = self::METRICS_CACHE_PREFIX . $routeId;
        return Cache::get($cacheKey);
    }
}
