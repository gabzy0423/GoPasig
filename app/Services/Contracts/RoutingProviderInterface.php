<?php

namespace App\Services\Contracts;

use App\Services\ValueObjects\Polyline;

interface RoutingProviderInterface
{
    /**
     * Compute geometry polyline from an array of Coordinates.
     */
    public function getRouteGeometry(array $waypoints): Polyline;

    /**
     * Get the provider's unique identifier string.
     */
    public function getIdentifier(): string;
}
