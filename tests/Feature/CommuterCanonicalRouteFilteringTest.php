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
        $this->route1 = $this->makeRoute('Route 2');
        $this->route2 = $this->makeRoute('Route 3');
        $this->route3 = $this->makeRoute('Route 4');
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
            ->assertSee('Route 2')
            ->assertSee('Route 3')
            ->assertSee('Route 4')
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
        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], $routeCodes);
        $this->assertStringStartsWith('[', $json);
        $this->assertStringContainsString('"route_code":"Route 2"', $json);
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
        $response->assertSee('Route 2');
        $response->assertSee('Route 3');
        $response->assertSee('Route 4');
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
        $this->makeOngoingTrip($canonicalBus, $this->route1);
        $this->makeOngoingTrip($legacyBus, $this->legacyRoute);

        Schedule::factory()->create(['route_id' => $this->route1->id, 'passengers' => 10, 'departure_time' => '08:00:00']);
        Schedule::factory()->create(['route_id' => $this->legacyRoute->id, 'passengers' => 999, 'departure_time' => '08:00:00']);

        $data = app(CommuterDashboardCacheService::class)->dashboardData();
        $routeNames = $data['activeRoutes']->pluck('route_name')->all();

        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], $routeNames);
        $this->assertSame(1, $data['quickStats']['active_buses']);
        $this->assertSame(10, $data['quickStats']['passengers_today']);
        $this->assertTrue($data['nearestBuses']->pluck('plate')->contains($canonicalBus->plate_number));
        $this->assertFalse($data['nearestBuses']->pluck('plate')->contains($legacyBus->plate_number));
    }

    public function test_public_alerts_hide_non_canonical_route_specific_alerts_and_keep_global_alerts(): void
    {
        ServiceAlert::create([
            'route_id' => $this->route1->id,
            'title' => 'Route 2 delay',
            'message' => 'Canonical route alert',
            'severity' => 'warning',
            'status' => 'active',
            'type' => 'delay',
            'affected_routes' => 'Route 2',
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

        $this->assertContains('Route 2 delay', $visibleTitles);
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

        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], $visibleRouteNames);
        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], $cachedRouteNames);

        foreach ([$this->route1, $this->route2, $this->route3] as $route) {
            $this->assertSame('Disrupted', app(RouteStatusService::class)->getCommuterRouteHealth($route->fresh(), collect()));
        }

        $dashboardData = app(CommuterDashboardCacheService::class)->dashboardData();
        $routes = $dashboardData['activeRoutes']->keyBy('route_name');

        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], $dashboardData['activeRoutes']->pluck('route_name')->all());
        $this->assertSame('Disrupted', $routes['Route 2']->health_status);
        $this->assertSame('Disrupted', $routes['Route 3']->health_status);
        $this->assertSame('Disrupted', $routes['Route 4']->health_status);

        Livewire::test(CommuterRoutes::class)
            ->assertSee('Route 2')
            ->assertSee('Route 3')
            ->assertSee('Route 4')
            ->assertDontSee('Route A')
            ->assertDontSee('PHASE3C-UAT');
    }

    public function test_route_specific_suspension_alert_remains_public_after_canonical_route_is_suspended(): void
    {
        ServiceAlert::create([
            'route_id' => $this->route1->id,
            'title' => 'Route 2 suspension remains visible',
            'message' => 'Route 2 service is temporarily suspended.',
            'severity' => 'critical',
            'status' => 'active',
            'type' => 'suspension',
            'affected_routes' => 'Route 2',
            'suspend_route' => true,
        ]);

        $this->route1->update(['status' => 'Suspended']);
        Cache::flush();

        $visibleTitles = ServiceAlert::activeAlerts()->publicCommuterVisible()->pluck('title')->all();

        $this->assertContains('Route 2 suspension remains visible', $visibleTitles);

        $this->get('/commuter/alerts')
            ->assertOk()
            ->assertSee('Route 2 suspension remains visible');
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

        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], $visibleRouteNames);
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
        $route1Summary = $dashboardData['activeRoutes']->firstWhere('route_name', 'Route 2');

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

                return $routeNames === ['Route 2', 'Route 3', 'Route 4'];
            });
    }

    public function test_public_bus_api_uses_canonical_active_trip_context(): void
    {
        $canonicalBus = $this->makeActiveBus($this->route1, 'CAN-201');
        $legacyBus = $this->makeActiveBus($this->legacyRoute, 'LEG-201');
        $uatBus = $this->makeActiveBus($this->uatRoute, 'UAT-201');
        $breakdownBus = $this->makeBus($this->route1, 'CAN-BRK-201', 'breakdown');
        $maintenanceBus = $this->makeBus($this->route1, 'CAN-MNT-201', 'maintenance');

        $this->makeOngoingTrip($canonicalBus, $this->route1);
        $this->makeOngoingTrip($legacyBus, $this->legacyRoute);
        $this->makeOngoingTrip($uatBus, $this->uatRoute);
        $this->makeOngoingTrip($breakdownBus, $this->route1);
        $this->makeOngoingTrip($maintenanceBus, $this->route1);

        $response = $this->getJson('/api/commuter/buses');

        $response->assertOk();
        $plates = collect($response->json())->pluck('plate_number')->all();

        $this->assertContains('CAN-201', $plates);
        $this->assertNotContains('LEG-201', $plates);
        $this->assertNotContains('UAT-201', $plates);
        $this->assertNotContains('CAN-BRK-201', $plates);
        $this->assertNotContains('CAN-MNT-201', $plates);
    }

    public function test_dashboard_active_bus_data_excludes_breakdown_maintenance_and_active_buses_without_ongoing_trip(): void
    {
        $normalBus = $this->makeActiveBus($this->route1, 'CAN-DASH-201', eta: 4);
        $noTripBus = $this->makeActiveBus($this->route1, 'CAN-DASH-NO-TRIP', eta: 1);
        $breakdownBus = $this->makeBus($this->route1, 'CAN-DASH-BRK', 'breakdown', eta: 2);
        $maintenanceBus = $this->makeBus($this->route1, 'CAN-DASH-MNT', 'maintenance', eta: 3);

        $this->makeOngoingTrip($normalBus, $this->route1);
        $this->makeOngoingTrip($breakdownBus, $this->route1);
        $this->makeOngoingTrip($maintenanceBus, $this->route1);
        Cache::flush();

        $helperBuses = CommuterDashboardCacheService::getActiveBuses();
        $dashboardData = app(CommuterDashboardCacheService::class)->dashboardData();
        $route = $dashboardData['activeRoutes']->firstWhere('route_name', 'Route 2');
        $nearestPlates = $dashboardData['nearestBuses']->pluck('plate')->all();

        $this->assertTrue($helperBuses->pluck('plate_number')->contains('CAN-DASH-201'));
        $this->assertFalse($helperBuses->pluck('plate_number')->contains($noTripBus->plate_number));
        $this->assertFalse($helperBuses->pluck('plate_number')->contains($breakdownBus->plate_number));
        $this->assertFalse($helperBuses->pluck('plate_number')->contains($maintenanceBus->plate_number));
        $this->assertSame(1, $dashboardData['quickStats']['active_buses']);
        $this->assertSame(1, $route->buses_on_route);
        $this->assertSame('CAN-DASH-201', $nearestPlates[0] ?? null);
        $this->assertNotContains('CAN-DASH-NO-TRIP', $nearestPlates);
        $this->assertNotContains('CAN-DASH-BRK', $nearestPlates);
        $this->assertNotContains('CAN-DASH-MNT', $nearestPlates);
    }

    public function test_commuter_routes_active_bus_data_requires_ongoing_trip(): void
    {
        $bus = $this->makeActiveBus($this->route1, 'CAN-ROUTE-401', eta: 4);

        Cache::flush();

        $component = Livewire::test(CommuterRoutes::class);
        $route = collect($component->get('routes'))->firstWhere('route_code', 'Route 2');

        $this->assertSame(0, $route['active_bus_count']);
        $this->assertNull($route['next_bus_eta']);
        $this->assertNull($route['next_bus_eta_label']);

        $this->makeOngoingTrip($bus, $this->route1);
        Cache::flush();

        $component = Livewire::test(CommuterRoutes::class);
        $route = collect($component->get('routes'))->firstWhere('route_code', 'Route 2');

        $this->assertSame(1, $route['active_bus_count']);
        $this->assertNotNull($route['next_bus_eta_label']);
    }

    public function test_operating_bus_with_ongoing_trip_is_visible_as_normal_commuter_service(): void
    {
        $bus = $this->makeBus($this->route1, 'CAN-OPERATING-401', 'operating', eta: 4);
        $this->makeOngoingTrip($bus, $this->route1);
        Cache::flush();

        $this->assertTrue(CommuterDashboardCacheService::getActiveBuses()
            ->pluck('plate_number')
            ->contains($bus->plate_number));

        $apiPlates = $this->getJson('/api/commuter/buses')
            ->assertOk()
            ->json();

        $this->assertContains($bus->plate_number, collect($apiPlates)->pluck('plate_number')->all());

        $component = Livewire::test(CommuterRoutes::class);
        $route = collect($component->get('routes'))->firstWhere('route_code', 'Route 2');

        $this->assertSame(1, $route['active_bus_count']);
    }

    public function test_commuter_routes_excludes_breakdown_and_maintenance_buses_from_available_service(): void
    {
        $normalBus = $this->makeActiveBus($this->route1, 'CAN-ROUTE-ACT', eta: 6);
        $breakdownBus = $this->makeBus($this->route1, 'CAN-ROUTE-BRK', 'breakdown', eta: 1);
        $maintenanceBus = $this->makeBus($this->route1, 'CAN-ROUTE-MNT', 'maintenance', eta: 2);

        $this->makeOngoingTrip($normalBus, $this->route1);
        $this->makeOngoingTrip($breakdownBus, $this->route1);
        $this->makeOngoingTrip($maintenanceBus, $this->route1);
        Cache::flush();

        $component = Livewire::test(CommuterRoutes::class)
            ->call('selectRoute', $this->route1->id);
        $route = collect($component->get('routes'))->firstWhere('route_code', 'Route 2');
        $visiblePlates = collect($component->get('activeBuses'))->pluck('plate_number')->all();

        $this->assertSame(1, $route['active_bus_count']);
        $this->assertNotNull($route['next_bus_eta_label']);
        $this->assertSame(['CAN-ROUTE-ACT'], $visiblePlates);
    }

    public function test_commuter_stops_next_arriving_bus_requires_ongoing_trip(): void
    {
        $origin = Stop::where('route_id', $this->route1->id)->where('sequence', 1)->firstOrFail();
        $bus = $this->makeActiveBus($this->route1, 'CAN-STOP-401', eta: 4, lat: $origin->lat, lng: $origin->lng);

        Cache::flush();

        Livewire::test(CommuterStops::class)
            ->call('selectStop', $origin->id)
            ->assertViewHas('nextBus', fn ($nextBus) => $nextBus === null);

        $this->makeOngoingTrip($bus, $this->route1);
        Cache::flush();

        Livewire::test(CommuterStops::class)
            ->call('selectStop', $origin->id)
            ->assertViewHas('nextBus', fn ($nextBus) => $nextBus?->plate_number === 'CAN-STOP-401');
    }

    public function test_commuter_stops_excludes_breakdown_and_maintenance_buses_from_next_arriving_bus(): void
    {
        $origin = Stop::where('route_id', $this->route1->id)->where('sequence', 1)->firstOrFail();
        $normalBus = $this->makeActiveBus($this->route1, 'CAN-STOP-ACT', eta: 6, lat: $origin->lat, lng: $origin->lng);
        $breakdownBus = $this->makeBus($this->route1, 'CAN-STOP-BRK', 'breakdown', eta: 1, lat: $origin->lat, lng: $origin->lng);
        $maintenanceBus = $this->makeBus($this->route1, 'CAN-STOP-MNT', 'maintenance', eta: 2, lat: $origin->lat, lng: $origin->lng);

        $this->makeOngoingTrip($normalBus, $this->route1);
        $this->makeOngoingTrip($breakdownBus, $this->route1);
        $this->makeOngoingTrip($maintenanceBus, $this->route1);
        Cache::flush();

        Livewire::test(CommuterStops::class)
            ->call('selectStop', $origin->id)
            ->assertViewHas('nextBus', fn ($nextBus) => $nextBus?->plate_number === 'CAN-STOP-ACT');
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
        return $this->makeBus($route, $plate, 'active', $eta, $lat, $lng);
    }

    private function makeBus(Route $route, string $plate, string $status, int $eta = 5, ?float $lat = null, ?float $lng = null): Bus
    {
        return Bus::factory()->create([
            'plate_number' => $plate,
            'route_id' => $route->id,
            'status' => $status,
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

