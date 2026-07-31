<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\Route;
use App\Models\ServiceAlert;
use App\Models\Trip;
use App\Services\CommuterDashboardCacheService;
use App\Services\RouteStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CommuterIncidentRouteHealthTest extends TestCase
{
    use RefreshDatabase;

    private Route $route1;
    private Route $route2;
    private Route $route3;
    private Route $legacyRoute;
    private Route $uatRoute;
    private Driver $driver;
    private Bus $bus;
    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->route1 = $this->makeRoute('Route 1');
        $this->route2 = $this->makeRoute('Route 2');
        $this->route3 = $this->makeRoute('Route 3');
        $this->legacyRoute = $this->makeRoute('Route A');
        $this->uatRoute = $this->makeRoute('PHASE3C-UAT Point-to-Point A-B');
        $this->driver = Driver::factory()->create();
        $this->bus = $this->makeActiveBus($this->route1, 'P5B-CAN-1');
        $this->trip = $this->makeTrip($this->route1, $this->bus);
    }

    public function test_active_breakdown_incident_makes_public_route_health_disrupted(): void
    {
        $this->incident(Incident::getBreakdownType());

        $this->assertSame('Disrupted', $this->health());
    }

    public function test_active_accident_incident_makes_public_route_health_disrupted(): void
    {
        $this->incident(Incident::getAccidentType());

        $this->assertSame('Disrupted', $this->health());
    }

    public function test_active_traffic_delay_incident_makes_public_route_health_minor_delay(): void
    {
        $this->incident('Traffic Delay');

        $this->assertSame('Minor Delay', $this->health());
    }

    public function test_active_heavy_traffic_delay_incident_makes_public_route_health_minor_delay(): void
    {
        $this->incident(Incident::getTrafficDelayType());

        $this->assertSame('Minor Delay', $this->health());
    }

    public function test_passenger_concern_only_does_not_degrade_public_route_health(): void
    {
        $this->incident(Incident::getPassengerConcernType());

        $this->assertSame('On Track', $this->health());
    }

    public function test_disrupted_incident_precedes_minor_delay_incident(): void
    {
        $this->incident('Traffic Delay');
        $this->incident(Incident::getBreakdownType());

        $this->assertSame('Disrupted', $this->health());
    }

    public function test_resolving_breakdown_while_traffic_delay_remains_falls_back_to_minor_delay(): void
    {
        $delay = $this->incident('Traffic Delay');
        $breakdown = $this->incident(Incident::getBreakdownType());

        $this->assertSame('Disrupted', $this->health());

        $breakdown->update(['status' => 'resolved']);

        $this->assertSame('Minor Delay', $this->health());
        $this->assertSame('reported', $delay->fresh()->status);
    }

    public function test_resolving_all_incidents_falls_back_to_existing_public_health_inputs(): void
    {
        $delay = $this->incident('Traffic Delay');
        $breakdown = $this->incident(Incident::getBreakdownType());

        ServiceAlert::create([
            'route_id' => $this->route1->id,
            'title' => 'Route 1 delay alert',
            'message' => 'Manual public delay alert remains active.',
            'severity' => 'warning',
            'status' => 'active',
            'type' => 'delay',
            'affected_routes' => 'Route 1',
        ]);

        $delay->update(['status' => 'resolved']);
        $breakdown->update(['status' => 'resolved']);

        $this->assertSame('Minor Delay', $this->health());

        ServiceAlert::query()->delete();

        $this->assertSame('On Track', $this->health());
    }

    public function test_service_alert_and_route_suspended_health_still_work_with_incident_integration(): void
    {
        ServiceAlert::create([
            'route_id' => $this->route1->id,
            'title' => 'Route 1 delay alert',
            'message' => 'Manual public delay alert.',
            'severity' => 'warning',
            'status' => 'active',
            'type' => 'delay',
            'affected_routes' => 'Route 1',
        ]);

        $this->assertSame('Minor Delay', $this->health());

        ServiceAlert::query()->delete();
        $this->route1->update(['status' => 'Suspended']);

        $this->assertSame('Disrupted', app(RouteStatusService::class)->getCommuterRouteHealth($this->route1->fresh(), collect()));
    }

    public function test_dashboard_route_health_applies_incidents_through_centralized_service_and_keeps_routes_canonical(): void
    {
        $this->incident(Incident::getBreakdownType());
        $legacyBus = $this->makeActiveBus($this->legacyRoute, 'P5B-LEG-1', eta: 99);
        $uatBus = $this->makeActiveBus($this->uatRoute, 'P5B-UAT-1', eta: 99);
        $this->makeTrip($this->legacyRoute, $legacyBus);
        $this->makeTrip($this->uatRoute, $uatBus);

        $data = app(CommuterDashboardCacheService::class)->dashboardData();
        $routes = $data['activeRoutes']->keyBy('route_name');

        $this->assertSame(['Route 1', 'Route 2', 'Route 3'], $data['activeRoutes']->pluck('route_name')->all());
        $this->assertSame('Disrupted', $routes['Route 1']->health_status);
        $this->assertFalse($data['nearestBuses']->pluck('plate')->contains($legacyBus->plate_number));
        $this->assertFalse($data['nearestBuses']->pluck('plate')->contains($uatBus->plate_number));
    }

    private function health(): string
    {
        return app(RouteStatusService::class)->getCommuterRouteHealth($this->route1->fresh(), collect());
    }

    private function incident(string $type, string $status = 'reported'): Incident
    {
        return Incident::create([
            'trip_id' => $this->trip->id,
            'driver_id' => $this->driver->id,
            'type' => $type,
            'description' => 'Internal test description that must not be exposed publicly.',
            'status' => $status,
            'reported_at' => now(),
        ]);
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

    private function makeActiveBus(Route $route, string $plate, int $eta = 4): Bus
    {
        return Bus::create([
            'plate_number' => $plate,
            'fleet_number' => 'FLEET-'.$plate,
            'vin' => 'P5BTESTVIN'.str_pad((string) $route->id, 7, '0', STR_PAD_LEFT),
            'manufacturer' => 'UAT',
            'model' => 'Phase 5B Test',
            'year_model' => 2026,
            'route_id' => $route->id,
            'driver_name' => 'Phase 5B Driver',
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
    }

    private function makeTrip(Route $route, Bus $bus): Trip
    {
        return Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now(),
            'dispatched_at' => now(),
            'gps_session_started_at' => now(),
            'peak_passengers' => 0,
        ]);
    }
}
