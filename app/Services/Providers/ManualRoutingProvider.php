<?php

namespace App\Services\Providers;

use App\Services\Contracts\RoutingProviderInterface;
use App\Services\ValueObjects\Polyline;

class ManualRoutingProvider implements RoutingProviderInterface
{
    public function getRouteGeometry(array $waypoints): Polyline
    {
        return new Polyline($waypoints);
    }

    public function getIdentifier(): string
    {
        return 'manual';
    }
}
