<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\RoutingProviderFactory;
use App\Services\Routing\ProviderHealthService;
use App\Services\Routing\ProviderQuotaService;
use App\Services\Contracts\ProviderCircuitBreakerInterface;

class ProviderHealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $providers = ['google', 'osrm'];
        $waypoints = [
            ['latitude' => 14.55, 'longitude' => 121.07],
            ['latitude' => 14.56, 'longitude' => 121.08]
        ];

        $healthSvc = app(ProviderHealthService::class);
        $quotaSvc = app(ProviderQuotaService::class);
        $circuitBreaker = app(ProviderCircuitBreakerInterface::class);

        foreach ($providers as $name) {
            $startTime = microtime(true);
            try {
                $provider = RoutingProviderFactory::make($name);
                $provider->getRouteGeometry($waypoints);
                $latencyMs = (microtime(true) - $startTime) * 1000.0;

                $healthSvc->recordRequest($name, $latencyMs, true);
                $quotaSvc->recordRequest($name);

                $snapshot = $healthSvc->getSnapshot($name);
                $circuitBreaker->evaluate($snapshot);
            } catch (\Exception $e) {
                $latencyMs = (microtime(true) - $startTime) * 1000.0;

                $healthSvc->recordRequest($name, $latencyMs, false);
                $quotaSvc->recordRequest($name);

                $snapshot = $healthSvc->getSnapshot($name);
                $circuitBreaker->evaluate($snapshot);
            }
        }
    }
}
