<?php

namespace App\Services\Contracts;

use App\Services\ValueObjects\Polyline;

interface RouteGeometryEngineInterface
{
    /**
     * Perform transactional geometry update with validation, version snapshot, caching, and events.
     */
    public function updateGeometry(int $routeId, Polyline $polyline, int $clientVersion, ?int $restoredFromVersion = null): Polyline;

    /**
     * Retrieve the current route geometry polyline.
     */
    public function getGeometry(int $routeId): Polyline;

    /**
     * Compute full metrics and GIS diagnostics for the polyline.
     */
    public function computeMetrics(int $routeId, Polyline $polyline): array;
}
