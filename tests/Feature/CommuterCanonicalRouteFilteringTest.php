<?php

namespace Tests\Feature;

use App\Livewire\Commuter\CommuterRoutes;
use App\Livewire\Commuter\CommuterStops;
use App\Livewire\Commuter\GeofenceDetector;
use App\Models\Bus;
use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\ServiceAlert;
use App\Models\Stop;
use App\Models\Trip;
use App\Services\CommuterDashboardCacheService;
use App\Services\RouteStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class CommuterCanonicalRouteFilteringTest extends TestCase
{
    use RefreshDatabase;

    private Route $route1;
    private Route $route2;
    private Route $route3;
    private Route $legacyRoute;
    private array $legacyRoutes = [];
    private Route $uatRoute;
    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->legacyRoutes = collect(['Route A', 'Route B', 'Route C', 'Route D'])
            ->map(fn (string $name) => $this->makeRoute($name))
            ->all();
        $this->legacyRoute = $this->legacyRoutes[0];
        $this->uatRoute = $this->makeRoute('PHASE3C-UAT Point-to-Point A-B');
        $this->route1 = $this->makeRoute('Route 1');
        $this->route2 = $this->makeRoute('Route 2');
        $this->route3 = $this->makeRoute('Route 3');
        $this->driver = Driver::factory()->create();

        foreach ([...$this->legacyRoutes, $this->uatRoute, $this->route1, $this->route2, $this->route3] as $route) {
            Stop::create([
                'route_id' => $route->id,
                'name' => $route->name . ' Origin',
                'lat' => 14.5,
                'lng' => 121.0,
                'sequence' => 1,
                'radius_meters' => 100,
            ]);

            Stop::create([
                'route_id' => $route->id,
                'name' => $route->name . ' Destination',
                'lat' => 14.5001,
                'lng' => 121.0001,
                'sequence' => 2,
                'radius_meters' => 100,
            ]);
        }
    }

    public function test_public_commuter_route_listing_serializes_as_alpine_array_with_only_canonical_routes(): void
    {
        $component = Livewire::test(CommuterRoutes::class)
            ->assertSee('Route 1')
            ->assertSee('Route 2')
            ->assertSee('Route 3')
            ->assertDontSee('Route A')
            ->assertDontSee('Route B')
            ->assertDontSee('Route C')
            ->assertDontSee('Route D')
            ->assertDontSee('PHASE3C-UAT');

        $routes = $component->get('routes');
        $routeCodes = collect($routes)->pluck('route_code')->all();
        $json = json_encode($routes);

        $this->assertTrue(array_is_list($routes));
        $this->assertSame([0, 1, 2], array_keys($routes));
        $this->assertSame(['Route 1', 'Route 2', 'Route 3'], $routeCodes);
        $this->assertStringStartsWith('[', $json);
        $this->assertStringContainsString('"route_code":"Route 1"', $json);
        $this->assertStringNotContainsString('Route A', $json);
        $this->assertStringNotContainsString('Route B', $json);
        $this->assertStringNotContainsString('Route C', $json);
        $this->assertStringNotContainsString('Route D', $json);
        $this->assertStringNotContainsString('PHASE3C-UAT', $json);
    }

    public function test_commuter_routes_page_renders_canonical_routes_for_alpine_filtering(): void
    {
        $response = $this->get('/commuter/routes');

        $response->assertOk();
        $response->assertSee('Route 1');
        $response->assertSee('Route 2');
        $response->assertSee('Route 3');
        $response->assertDontSee('Route A');
        $response->assertDontSee('Route B');
        $response->assertDontSee('Route C');
        $response->assertDontSee('Route D');
        $response->assertDontSee('PHASE3C-UAT');
        $response->assertSee('routes: [{"route_id":', false);
    }

    public function test_public_dashboard_route_summaries_and_counts_exclude_non_canonical_routes(): void
    {
        $canonicalBus = $this->makeActiveBus($this->route1, 'CAN-101', eta: 4);
        $legacyBus = $this->makeActiveBus($this->legacyRoute, 'LEG-101', eta: 99);

        Schedule::factory()->create(['route_id' => $this->route1->id, 'passengers' => 10, 'departure_time' => '08:00:00']);
        Schedule::factory()->create(['route_id' => $this->legacyRoute->id, 'passengers' => 999, 'departure_time' => '08:00:00']);

        $data = app(CommuterDashboardCacheService::class)->dashboardData();
        $routeNames = $data['activeRoutes']->pluck('route_name')->all();

        $this->assertSame(['Route 1', 'Route 2', 'Route 3'], $routeNames);
        $this->assertSame(1, $data['quickStats']['active_buses']);
        $this->assertSame(10, $data['quickStats']['passengers_today']);
        $this->assertTrue($data['nearestBuses']->pluck('plate')->contains($canonicalBus->plate_number));
        $this->assertFalse($data['nearestBuses']->pluck('plate')->contains($legacyBus->plate_number));
    }

    public function test_public_alerts_hide_non_canonical_route_specific_alerts_and_keep_global_alerts(): void
    {
        ServiceAlert::create([
            'route_id' => $this->route1->id,
            'title' => 'Route 1 delay',
            'message' => 'Canonical route alert',
            'severity' => 'warning',
            'status' => 'active',
            'type' => 'delay',
            'affected_routes' => 'Route 1',
        ]);

        ServiceAlert::create([
            'route_id' => $this->legacyRoute->id,
            'title' => 'Route A delay',
            'message' => 'Legacy route alert',
            'severity' => 'warning',
            'status' => 'active',
            'type' => 'delay',
            'affected_routes' => 'Route A',
        ]);

        ServiceAlert::create([
            'route_id' => null,
            'title' => 'System-wide notice',
            'message' => 'Global alert',
            'severity' => 'info',
            'status' => 'active',
            'type' => 'info',
            'affected_routes' => null,
        ]);

        $visibleTitles = ServiceAlert::activeAlerts()->publicCommuterVisible()->pluck('title')->all();

        $this->assertContains('Route 1 delay', $visibleTitles);
        $this->assertContains('System-wide notice', $visibleTitles);
        $this->assertNotContains('Route A delay', $visibleTitles);
    }

    public function test_suspended_canonical_routes_remain_publicly_visible_with_disrupted_health(): void
    {
        foreach ([$this->route1, $this->route2, $this->route3] as $route) {
            $route->update(['status' => 'Suspended']);
        }

        Cache::flush();

        $visibleRouteNames = Route::publicCommuterVisible()->orderBy('id')->pluck('name')->all();
        $cachedRouteNames = Route::getCanonicalProductionCached()->pluck('name')->values()->all();

        $this->assertSame(['Route 1', 'Route 2', 'Route 3'], $visibleRouteNames);
        $this->assertSame(['Route 1', 'Route 2', 'Route 3'], $cachedRouteNames);

        foreach ([$this->route1, $this->route2, $this->route3] as $route) {
            $this->assertSame('Disrupted', app(RouteStatusService::class)->getCommuterRouteHealth($route->fresh(), collect()));
        }

        $dashboardData = app(CommuterDashboardCacheService::class)->dashboardData();
        $routes = $dashboardData['activeRoutes']->keyBy('route_name');

        $this->assertSame(['Route 1', 'Route 2', 'Route 3'], $dashboardData['activeRoutes']->pluck('route_name')->all());
        $this->assertSame('Disrupted', $routes['Route 1']->health_status);
        $this->assertSame('Disrupted', $routes['Route 2']->health_status);
        $this->assertSame('Disrupted', $routes['Route 3']->health_status);

        Livewire::test(CommuterRoutes::class)
            ->assertSee('Route 1')
            ->assertSee('Route 2')
            ->assertSee('Route 3')
            ->assertDontSee('Route A')
            ->assertDontSee('PHASE3C-UAT');
    }

    public function test_route_specific_suspension_alert_remains_public_after_canonical_route_is_suspended(): void
    {
        ServiceAlert::create([
            'route_id' => $this->route1->id,
            'title' => 'Route 1 suspension remains visible',
            'message' => 'Route 1 service is temporarily suspended.',
            'severity' => 'critical',
            'status' => 'active',
            'type' => 'suspension',
            'affected_routes' => 'Route 1',
            'suspend_route' => true,
        ]);

        $this->route1->update(['status' => 'Suspended']);
        Cache::flush();

        $visibleTitles = ServiceAlert::activeAlerts()->publicCommuterVisible()->pluck('title')->all();

        $this->assertContains('Route 1 suspension remains visible', $visibleTitles);

        $this->get('/commuter/alerts')
            ->assertOk()
            ->assertSee('Route 1 suspension remains visible');
    }

    public function test_suspended_legacy_and_uat_routes_do_not_become_public(): void
    {
        foreach ([...$this->legacyRoutes, $this->uatRoute] as $route) {
            $route->update(['status' => 'Suspended']);
        }

        ServiceAlert::create([
            'route_id' => $this->legacyRoute->id,
            'title' => 'Route A suspension hidden',
            'message' => 'Legacy route suspension.',
            'severity' => 'critical',
            'status' => 'active',
            'type' => 'suspension',
            'affected_routes' => 'Route A',
            'suspend_route' => true,
        ]);

        ServiceAlert::create([
            'route_id' => $this->uatRoute->id,
            'title' => 'UAT suspension hidden',
            'message' => 'UAT route suspension.',
            'severity' => 'critical',
            'status' => 'active',
            'type' => 'suspension',
            'affected_routes' => $this->uatRoute->name,
            'suspend_route' => true,
        ]);

        Cache::flush();

        $visibleRouteNames = Route::publicCommuterVisible()->pluck('name')->all();
        $visibleAlertTitles = ServiceAlert::activeAlerts()->publicCommuterVisible()->pluck('title')->all();

        $this->assertSame(['Route 1', 'Route 2', 'Route 3'], $visibleRouteNames);
        $this->assertNotContains('Route A suspension hidden', $visibleAlertTitles);
        $this->assertNotContains('UAT suspension hidden', $visibleAlertTitles);
    }

    public function test_suspended_canonical_route_identity_does_not_create_active_service_visibility(): void
    {
        $bus = $this->makeActiveBus($this->route1, 'CAN-SUSP-401');
        $this->makeOngoingTrip($bus, $this->route1);
        $this->route1->update(['status' => 'Suspended']);

        Cache::flush();

        $dashboardData = app(CommuterDashboardCacheService::class)->dashboardData();
        $route1Summary = $dashboardData['activeRoutes']->firstWhere('route_name', 'Route 1');

        $this->assertNotNull($route1Summary);
        $this->assertSame('Disrupted', $route1Summary->health_status);
        $this->assertSame(0, $route1Summary->buses_on_route);
        $this->assertFalse($dashboardData['nearestBuses']->pluck('plate')->contains($bus->plate_number));

        $this->getJson('/api/commuter/buses')
            ->assertOk()
            ->assertJsonMissing(['plate_number' => 'CAN-SUSP-401']);
    }

    public function test_public_stop_browsing_exposes_only_canonical_route_stops(): void
    {
        Livewire::test(CommuterStops::class)
            ->assertViewHas('stops', function ($stops) {
                $routeNames = $stops->pluck('route.name')->unique()->values()->all();

                return $routeNames === ['Route 1', 'Route 2', 'Route 3'];
            });
    }

    public function test_public_bus_api_uses_canonical_active_trip_context(): void
    {
        $canonicalBus = $this->makeActiveBus($this->route1, 'CAN-201');
        $legacyBus = $this->makeActiveBus($this->legacyRoute, 'LEG-201');
        $uatBus = $this->makeActiveBus($this->uatRoute, 'UAT-201');

        $this->makeOngoingTrip($canonicalBus, $this->route1);
        $this->makeOngoingTrip($legacyBus, $this->legacyRoute);
        $this->makeOngoingTrip($uatBus, $this->uatRoute);

        $response = $this->getJson('/api/commuter/buses');

        $response->assertOk();
        $plates = collect($response->json())->pluck('plate_number')->all();

        $this->assertContains('CAN-201', $plates);
        $this->assertNotContains('LEG-201', $plates);
        $this->assertNotContains('UAT-201', $plates);
    }

    public function test_guest_session_access_and_commuter_geofence_foundation_does_not_auto_transition_trips(): void
    {
        $origin = Stop::where('route_id', $this->route1->id)->where('sequence', 1)->first();
        $destination = Stop::where('route_id', $this->route1->id)->where('sequence', 2)->first();
        $bus = $this->makeActiveBus($this->route1, 'CAN-301', lat: $origin->lat, lng: $origin->lng);

        $this->makeOngoingTrip($bus, $this->route1);

        $response = $this->get('/commuter/dashboard');
        $response->assertOk();
        $response->assertCookie('commuter_session_token');

        $token = 'phase1-test-session';
        CommuterSession::create([
            'session_token' => $token,
            'expires_at' => now()->addHour(),
        ]);

        CommuterTrip::create([
            'session_token' => $token,
            'route_id' => $this->route1->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'status' => 'WAITING',
        ]);

        request()->cookies->set('commuter_session_token', $token);

        $detector = new GeofenceDetector();
        $detector->updateLocation($origin->lat, $origin->lng, 5);

        $this->assertSame($origin->id, $detector->activeStop['id']);
        $this->assertDatabaseHas('commuter_trips', [
            'session_token' => $token,
            'status' => 'WAITING',
            'bus_id' => null,
            'boarded_at' => null,
        ]);

        $detector->updateLocation($destination->lat, $destination->lng, 5);

        $this->assertDatabaseHas('commuter_trips', [
            'session_token' => $token,
            'status' => 'WAITING',
            'bus_id' => null,
            'arrived_at' => null,
        ]);
    }

    private function makeRoute(string $name): Route
    {
        return Route::create([
            'name' => $name,
            'description' => $name . ' Description',
            'status' => 'Active',
            'color' => '#003F87',
        ]);
    }

    private function makeActiveBus(Route $route, string $plate, int $eta = 5, ?float $lat = null, ?float $lng = null): Bus
    {
        return Bus::factory()->create([
            'plate_number' => $plate,
            'route_id' => $route->id,
            'status' => 'active',
            'eta' => $eta,
            'lat' => $lat ?? 14.5,
            'lng' => $lng ?? 121.0,
            'speed' => 10,
            'passengers' => 5,
        ]);
    }

    private function makeOngoingTrip(Bus $bus, Route $route): Trip
    {
        return Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now(),
        ]);
    }
}

