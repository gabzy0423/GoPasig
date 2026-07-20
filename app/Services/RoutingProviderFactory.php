<?php

namespace App\Services;

use App\Services\Contracts\RoutingProviderInterface;
use App\Services\Providers\GoogleRoutingProvider;
use App\Services\Providers\OsrmRoutingProvider;
use App\Services\Providers\ManualRoutingProvider;
use InvalidArgumentException;

class RoutingProviderFactory
{
    public static function make(?string $driver = null): RoutingProviderInterface
    {
        $driver = $driver ?: config('routing.default', 'manual');

        switch (strtolower($driver)) {
            case 'manual':
                return app(ManualRoutingProvider::class);
            case 'google':
                return app(GoogleRoutingProvider::class);
            case 'osrm':
                return app(OsrmRoutingProvider::class);
            default:
                throw new InvalidArgumentException("Routing engine driver [{$driver}] is not supported.");
        }
    }
}
