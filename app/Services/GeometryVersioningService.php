<?php

namespace App\Services;

use App\Models\RouteGeometryVersion;
use App\Services\ValueObjects\Polyline;
use App\Services\Contracts\RouteGeometryEngineInterface;

class GeometryVersioningService
{
    /**
     * Snapshot a route geometry.
     */
    public function snapshot(
        int      $routeId,
        Polyline $geometry,
        string   $label = '',
        ?int     $restoredFromVersion = null
    ): RouteGeometryVersion {
        return RouteGeometryVersion::create([
            'route_id' => $routeId,
            'polyline_coordinates' => $geometry->toLatLngs(),
            'vertex_count' => $geometry->count(),
            'length_km' => $geometry->getLengthKm(),
            'label' => $label,
            'created_by_user_id' => auth()->check() ? auth()->id() : null,
            'restored_from_version' => $restoredFromVersion,
        ]);
    }

    /**
     * Restore a geometry version.
     */
    public function restore(int $routeId, int $versionId): Polyline
    {
        $version = RouteGeometryVersion::where('route_id', $routeId)->findOrFail($versionId);
        $restored = Polyline::fromArray($version->polyline_coordinates);

        // Resolve engine dynamically to prevent circular dependency
        $engine = app(RouteGeometryEngineInterface::class);
        
        // Find current version of route
        $route = \App\Models\Route::findOrFail($routeId);

        $engine->updateGeometry($routeId, $restored, $route->geometry_version, $versionId);

        return $restored;
    }

    /**
     * Prune old versions keeping a specified limit.
     */
    public function prune(int $routeId, int $keep = 50): int
    {
        $ids = RouteGeometryVersion::where('route_id', $routeId)
            ->orderByDesc('id')
            ->pluck('id');

        if ($ids->count() > $keep) {
            $idsToDelete = $ids->slice($keep)->all();
            return RouteGeometryVersion::whereIn('id', $idsToDelete)->delete();
        }

        return 0;
    }
}
