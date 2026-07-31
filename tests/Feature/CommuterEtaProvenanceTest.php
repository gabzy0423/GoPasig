<?php

namespace Tests\Feature;

use App\Livewire\Commuter\CommuterRoutes;
use App\Livewire\Commuter\CommuterStops;
use App\Livewire\Commuter\Tracker;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Stop;
use App\Models\Trip;
use App\Models\TripProgress;
use App\Services\CommuterDashboardCacheService;
use App\Services\CommuterEtaProvenanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class CommuterEtaProvenanceTest extends TestCase
{
    use RefreshDatabase;

    private Route $route1;
    private Route $route2;
    private Route $route3;
    private Route $legacyRoute;
    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-07-23 00:00:00', 'UTC'));

        $this->legacyRoute = $this->makeRoute('Route A');
        $this->route1 = $this->makeRoute('Route 1');
        $this->route2 = $this->makeRoute('Route 2');
        $this->route3 = $this->makeRoute('Route 3');
        $this->driver = Driver::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_usable_geometry_and_trip_progress_upcoming_eta_is_authoritative(): void
    {
        [$variant, $origin] = $this->variantWithStops($this->route1, 'outbound', usable: true);
        $bus = $this->activeBus($this->route1, 'ETA-AUTH', eta: 5);
        $trip = $this->ongoingTrip($bus, $this->route1, $variant);
        TripProgress::create([
            'trip_id' => $trip->id,
            'completed_stops_count' => 0,
            'remaining_stops_count' => 1,
            'trip_percentage' => 0,
            'route_adherence' => 'On Route',
            'current_delay_minutes' => 0,
            'upcoming_etas' => [[
                'stop_id' => $origin->canonical_stop_id,
                'eta_timestamp' => now()->addMinutes(5)->toIso8601String(),
                'distance_remaining_meters' => 500,
                'delay_minutes' => 0,
            ]],
        ]);

        $eta = app(CommuterEtaProvenanceService::class)->forBus($bus, $origin->canonical_stop_id, $origin->id);

        $this->assertSame('authoritative', $eta->state);
        $this->assertSame(5, $eta->minutes);
        $this->assertTrue($eta->is_authoritative);
    }

    public function test_missing_geometry_is_not_authoritative(): void
    {
        [$variant] = $this->variantWithStops($this->route1, 'outbound', usable: false);
        $bus = $this->activeBus($this->route1, 'ETA-GEOM', eta: null);
        $this->ongoingTrip($bus, $this->route1, $variant);

        $eta = app(CommuterEtaProvenanceService::class)->forBus($bus);

        $this->assertSame('missing_geometry', $eta->state);
        $this->assertFalse($eta->is_authoritative);
        $this->assertSame('ETA unavailable - official route data pending', $eta->label);
    }

    public function test_missing_trip_progress_is_not_authoritative(): void
    {
        [$variant] = $this->variantWithStops($this->route1, 'outbound', usable: true);
        $bus = $this->activeBus($this->route1, 'ETA-PROG', eta: null);
        $this->ongoingTrip($bus, $this->route1, $variant);

        $eta = app(CommuterEtaProvenanceService::class)->forBus($bus);

        $this->assertSame('missing_trip_progress', $eta->state);
        $this->assertFalse($eta->is_authoritative);
        $this->assertSame('Awaiting trip progress', $eta->label);
    }

    public function test_buses_eta_fallback_is_labeled_legacy_next_stop_eta(): void
    {
        [$variant] = $this->variantWithStops($this->route1, 'outbound', usable: false);
        $bus = $this->activeBus($this->route1, 'ETA-LEG', eta: 5);
        $this->ongoingTrip($bus, $this->route1, $variant);

        $eta = app(CommuterEtaProvenanceService::class)->forBus($bus);

        $this->assertSame('legacy_bus_eta', $eta->state);
        $this->assertSame('Next stop: ~5 min', $eta->label);
        $this->assertFalse($eta->is_authoritative);
        $this->assertSame('missing_geometry', $eta->blocked_state);
    }

    public function test_pending_official_route_geometry_never_fabricates_authoritative_eta(): void
    {
        [$variant] = $this->variantWithStops($this->route1, 'outbound', usable: false);
        $bus = $this->activeBus($this->route1, 'ETA-PEND', eta: 5);
        $trip = $this->ongoingTrip($bus, $this->route1, $variant);
        TripProgress::create([
            'trip_id' => $trip->id,
            'completed_stops_count' => 0,
            'remaining_stops_count' => 1,
            'trip_percentage' => 0,
            'route_adherence' => 'On Route',
            'current_delay_minutes' => 0,
            'upcoming_etas' => [[
                'stop_id' => 999,
                'eta_timestamp' => now()->addMinutes(5)->toIso8601String(),
                'distance_remaining_meters' => 500,
                'delay_minutes' => 0,
            ]],
        ]);

        $eta = app(CommuterEtaProvenanceService::class)->forBus($bus);

        $this->assertSame('legacy_bus_eta', $eta->state);
        $this->assertFalse($eta->is_authoritative);
    }

    public function test_provenance_is_route_variant_aware_for_inbound_and_outbound(): void
    {
        [$outbound, $outboundOrigin] = $this->variantWithStops($this->route1, 'outbound', usable: true);
        [$inbound, $inboundOrigin] = $this->variantWithStops($this->route1, 'inbound', usable: true);
        $bus = $this->activeBus($this->route1, 'ETA-DIR', eta: 6);
        $trip = $this->ongoingTrip($bus, $this->route1, $inbound);
        TripProgress::create([
            'trip_id' => $trip->id,
            'completed_stops_count' => 0,
            'remaining_stops_count' => 1,
            'trip_percentage' => 0,
            'route_adherence' => 'On Route',
            'current_delay_minutes' => 0,
            'upcoming_etas' => [[
                'stop_id' => $inboundOrigin->canonical_stop_id,
                'eta_timestamp' => now()->addMinutes(4)->toIso8601String(),
                'distance_remaining_meters' => 400,
                'delay_minutes' => 0,
            ]],
        ]);

        $service = app(CommuterEtaProvenanceService::class);

        $this->assertSame('authoritative', $service->forBus($bus, $inboundOrigin->canonical_stop_id, $inboundOrigin->id)->state);
        $this->assertSame('legacy_bus_eta', $service->forBus($bus, $outboundOrigin->canonical_stop_id, $outboundOrigin->id)->state);
    }

    public function test_tracker_dashboard_routes_and_stops_use_consistent_provenance(): void
    {
        [$variant, $origin] = $this->variantWithStops($this->route1, 'outbound', usable: true);
        $bus = $this->activeBus($this->route1, 'ETA-SURF', eta: 5, lat: $origin->lat, lng: $origin->lng);
        $trip = $this->ongoingTrip($bus, $this->route1, $variant);
        TripProgress::create([
            'trip_id' => $trip->id,
            'completed_stops_count' => 0,
            'remaining_stops_count' => 1,
            'trip_percentage' => 0,
            'route_adherence' => 'On Route',
            'current_delay_minutes' => 0,
            'upcoming_etas' => [[
                'stop_id' => $origin->canonical_stop_id,
                'eta_timestamp' => now()->addMinutes(5)->toIso8601String(),
                'distance_remaining_meters' => 500,
                'delay_minutes' => 0,
            ]],
        ]);

        Livewire::test(Tracker::class)
            ->assertViewHas('activeBuses', fn ($buses) => collect($buses)->firstWhere('plate_number', 'ETA-SURF')?->eta_provenance_state === 'authoritative');

        $dashboard = app(CommuterDashboardCacheService::class)->dashboardData();
        $this->assertSame('authoritative', $dashboard['nearestBuses']->firstWhere('plate', 'ETA-SURF')->eta_provenance_state);

        $routes = Livewire::test(CommuterRoutes::class)->call('selectRoute', $this->route1->id);
        $this->assertSame('authoritative', collect($routes->get('activeBuses'))->firstWhere('plate_number', 'ETA-SURF')['next_stop_eta_provenance_state']);
        $this->assertSame('authoritative', collect($routes->get('routeStops'))->firstWhere('stop_id', $origin->canonical_stop_id)['next_bus_eta_provenance_state']);

        Livewire::test(CommuterStops::class)
            ->call('selectStop', $origin->canonical_stop_id)
            ->assertViewHas('nextBus', fn ($nextBus) => $nextBus?->eta_provenance_state === 'authoritative');
    }

    public function test_public_access_and_canonical_filtering_remain_intact(): void
    {
        $this->get('/commuter/tracker')->assertOk();
        $this->get('/commuter/routes')
            ->assertOk()
            ->assertSee('Route 1')
            ->assertSee('Route 2')
            ->assertSee('Route 3')
            ->assertDontSee('Route A');
    }

    private function makeRoute(string $name): Route
    {
        return Route::create([
            'name' => $name,
            'description' => $name . ' Description',
            'status' => 'Active',
            'color' => '#003F87',
            'polyline_coordinates' => null,
        ]);
    }

    private function variantWithStops(Route $route, string $direction, bool $usable): array
    {
        $originStop = Stop::create([
            'route_id' => $route->id,
            'name' => $route->name.' '.$direction.' Origin',
            'lat' => 14.5 + ($direction === 'inbound' ? 0.01 : 0),
            'lng' => 121.0 + ($direction === 'inbound' ? 0.01 : 0),
            'sequence' => 1,
            'radius_meters' => 100,
        ]);
        $destinationStop = Stop::create([
            'route_id' => $route->id,
            'name' => $route->name.' '.$direction.' Destination',
            'lat' => 14.51 + ($direction === 'inbound' ? 0.01 : 0),
            'lng' => 121.01 + ($direction === 'inbound' ? 0.01 : 0),
            'sequence' => 2,
            'radius_meters' => 100,
        ]);

        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => $direction,
            'origin_name' => $originStop->name,
            'destination_name' => $destinationStop->name,
            'polyline_coordinates' => $usable ? [[$originStop->lat, $originStop->lng], [$destinationStop->lat, $destinationStop->lng]] : null,
            'geometry_version' => $usable ? 1 : 0,
            'geometry_status' => $usable ? 'valid' : 'pending',
            'is_default' => $direction === 'outbound',
        ]);

        $originVariantStop = RouteVariantStop::create([
            'route_variant_id' => $variant->id,
            'canonical_stop_id' => $originStop->id,
            'name' => $originStop->name,
            'lat' => $usable ? $originStop->lat : null,
            'lng' => $usable ? $originStop->lng : null,
            'radius_meters' => 100,
            'sequence' => 1,
            'coordinate_status' => $usable ? 'verified' : 'pending',
        ]);
        RouteVariantStop::create([
            'route_variant_id' => $variant->id,
            'canonical_stop_id' => $destinationStop->id,
            'name' => $destinationStop->name,
            'lat' => $usable ? $destinationStop->lat : null,
            'lng' => $usable ? $destinationStop->lng : null,
            'radius_meters' => 100,
            'sequence' => 2,
            'coordinate_status' => $usable ? 'verified' : 'pending',
        ]);

        return [$variant->fresh('stops'), $originVariantStop];
    }

    private function activeBus(Route $route, string $plate, ?int $eta, ?float $lat = null, ?float $lng = null): Bus
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

    private function ongoingTrip(Bus $bus, Route $route, RouteVariant $variant): Trip
    {
        return Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now(),
        ]);
    }
}
