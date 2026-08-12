<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Route;
use App\Models\ServiceAlert;
use App\Models\Trip;
use App\Services\CommuterDashboardCacheService;
use App\Services\RouteStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CommuterPublicRouteHealthCentralizationTest extends TestCase
{
    use RefreshDatabase;

    private Route $route1;
    private Route $route2;
    private Route $route3;
    private Route $legacyRoute;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->route1 = $this->makeRoute('Route 2');
        $this->route2 = $this->makeRoute('Route 3');
        $this->route3 = $this->makeRoute('Route 4');
        $this->legacyRoute = $this->makeRoute('Route A');
    }

    public function test_dashboard_route_health_uses_centralized_route_status_service(): void
    {
        $this->mock(RouteStatusService::class, function ($mock) {
            $mock->shouldReceive('getCommuterRouteHealth')
                ->times(3)
                ->andReturn('Disrupted');
        });

        $data = app(CommuterDashboardCacheService::class)->dashboardData();

        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], $data['activeRoutes']->pluck('route_name')->all());
        $this->assertSame(['Disrupted', 'Disrupted', 'Disrupted'], $data['activeRoutes']->pluck('health_status')->all());
        $this->assertFalse(method_exists(CommuterDashboardCacheService::class, 'routeHealth'));
    }

    public function test_centralized_commuter_health_preserves_service_alert_and_bus_delay_semantics(): void
    {
        $service = app(RouteStatusService::class);

        $this->assertSame('On Track', $service->getCommuterRouteHealth($this->route1, collect()));

        ServiceAlert::create([
            'route_id' => $this->route1->id,
            'title' => 'Route 2 delay',
            'message' => 'Expect minor delay.',
            'severity' => 'warning',
            'status' => 'active',
            'type' => 'delay',
            'affected_routes' => 'Route 2',
        ]);

        $this->assertSame('Minor Delay', $service->getCommuterRouteHealth($this->route1, collect()));

        ServiceAlert::query()->delete();
        ServiceAlert::create([
            'route_id' => $this->route1->id,
            'title' => 'Route 2 suspension',
            'message' => 'Service suspended.',
            'severity' => 'critical',
            'status' => 'active',
            'type' => 'suspension',
            'affected_routes' => 'Route 2',
        ]);

        $this->assertSame('Disrupted', $service->getCommuterRouteHealth($this->route1, collect()));

        ServiceAlert::query()->delete();
        $delayedBus = $this->makeActiveBus($this->route1, eta: 15);

        $this->assertSame('Minor Delay', $service->getCommuterRouteHealth($this->route1, collect([$delayedBus]), collect(), 10));
    }

    public function test_centralized_commuter_health_preserves_route_suspension_semantics(): void
    {
        $this->route1->update(['status' => 'Suspended']);

        $this->assertSame('Disrupted', app(RouteStatusService::class)->getCommuterRouteHealth($this->route1->fresh(), collect()));
    }

    public function test_dashboard_route_health_remains_canonical_only(): void
    {
        $canonicalBus = $this->makeActiveBus($this->route1, eta: 4);
        $legacyBus = $this->makeActiveBus($this->legacyRoute, eta: 99, plate: 'LEG-P5A');

        $data = app(CommuterDashboardCacheService::class)->dashboardData();

        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], $data['activeRoutes']->pluck('route_name')->all());
        $this->assertTrue($data['nearestBuses']->pluck('plate')->contains($canonicalBus->plate_number));
        $this->assertFalse($data['nearestBuses']->pluck('plate')->contains($legacyBus->plate_number));
    }

    private function makeRoute(string $name): Route
    {
        return Route::create([
            'name' => $name,
            'description' => $name.' description',
            'color' => '#003F87',
            'status' => 'Active',
            'delay_threshold_minutes' => 10,
            'travel_time_minutes' => 30,
        ]);
    }

    private function makeActiveBus(Route $route, int $eta, string $plate = 'P5A-CAN'): Bus
    {
        $bus = Bus::create([
            'plate_number' => $plate.'-'.$route->id,
            'fleet_number' => 'FLEET-'.$plate.'-'.$route->id,
            'vin' => 'P5ATESTVIN'.str_pad((string) $route->id, 7, '0', STR_PAD_LEFT),
            'manufacturer' => 'UAT',
            'model' => 'Phase 5A Test',
            'year_model' => 2026,
            'route_id' => $route->id,
            'driver_name' => 'Phase 5A Driver',
            'capacity' => 45,
            'speed' => 10,
            'passengers' => 0,
            'next_stop' => 'Terminal',
            'eta' => $eta,
            'lat' => 14.569,
            'lng' => 121.085,
            'status' => Bus::STATUS_ACTIVE,
            'is_simulated' => false,
        ]);

        Trip::factory()->create([
            'bus_id' => $bus->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'started_at' => now(),
        ]);

        return $bus;
    }
}
