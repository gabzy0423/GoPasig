<?php

namespace Tests\Feature;

use App\Livewire\Commuter\Tracker;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Stop;
use App\Models\Trip;
use App\Models\VehiclePosition;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class CommuterGpsFreshnessSourceTest extends TestCase
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
        $this->route1 = $this->makeRoute('Route 2');
        $this->route2 = $this->makeRoute('Route 3');
        $this->route3 = $this->makeRoute('Route 4');
        $this->driver = Driver::factory()->create();

        foreach ([$this->legacyRoute, $this->route1, $this->route2, $this->route3] as $route) {
            Stop::create([
                'route_id' => $route->id,
                'name' => $route->name . ' Origin',
                'lat' => 14.5,
                'lng' => 121.0,
                'sequence' => 1,
                'radius_meters' => 100,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_fresh_last_gps_fix_at_renders_live_for_tracker_surfaces(): void
    {
        $bus = $this->activeBusWithTrip($this->route1, 'GPS-001');
        $this->position($bus, now()->subSeconds(10));

        Livewire::test(Tracker::class)
            ->call('updateLocation', 14.5, 121.0)
            ->assertViewHas('activeBuses', fn ($buses) => $this->busState($buses, 'GPS-001') === 'LIVE')
            ->assertViewHas('nearestBus', fn ($nearestBus) => $nearestBus?->gps_freshness_state === 'LIVE')
            ->assertSee('LIVE');
    }

    public function test_thirty_to_one_hundred_nineteen_second_fix_renders_stale(): void
    {
        $bus = $this->activeBusWithTrip($this->route1, 'GPS-030');
        $this->position($bus, now()->subSeconds(30));

        Livewire::test(Tracker::class)
            ->assertViewHas('activeBuses', function ($buses) {
                $bus = collect($buses)->firstWhere('plate_number', 'GPS-030');

                return $bus?->gps_freshness_state === 'STALE'
                    && $bus?->gps_freshness_age_seconds === 30;
            })
            ->assertSee('STALE (30s)');
    }

    public function test_one_hundred_twenty_second_fix_renders_offline(): void
    {
        $bus = $this->activeBusWithTrip($this->route1, 'GPS-120');
        $this->position($bus, now()->subSeconds(120));

        Livewire::test(Tracker::class)
            ->assertViewHas('activeBuses', fn ($buses) => $this->busState($buses, 'GPS-120') === 'OFFLINE')
            ->assertSee('OFFLINE');
    }

    public function test_null_last_gps_fix_at_never_renders_live(): void
    {
        $bus = $this->activeBusWithTrip($this->route1, 'GPS-NULL');
        $this->position($bus, null);

        Livewire::test(Tracker::class)
            ->assertViewHas('activeBuses', fn ($buses) => $this->busState($buses, 'GPS-NULL') === 'UNKNOWN')
            ->assertSee('UNKNOWN');
    }

    public function test_bus_updated_at_change_without_new_gps_fix_does_not_refresh_public_freshness(): void
    {
        $bus = $this->activeBusWithTrip($this->route1, 'GPS-EDIT');
        $this->position($bus, now()->subSeconds(90));

        $bus->forceFill([
            'passengers' => 12,
            'updated_at' => now(),
        ])->save();

        Livewire::test(Tracker::class)
            ->assertViewHas('activeBuses', function ($buses) {
                $bus = collect($buses)->firstWhere('plate_number', 'GPS-EDIT');

                return $bus?->gps_freshness_state === 'STALE'
                    && $bus?->gps_freshness_age_seconds === 90;
            })
            ->assertSee('STALE (90s)');
    }

    public function test_simulated_buses_retain_estimated_badge_behavior(): void
    {
        $bus = $this->activeBusWithTrip($this->route1, 'GPS-SIM', ['is_simulated' => true]);
        $this->position($bus, now()->subSeconds(10));

        Livewire::test(Tracker::class)
            ->assertViewHas('activeBuses', function ($buses) {
                $bus = collect($buses)->firstWhere('plate_number', 'GPS-SIM');

                return $bus?->is_simulated === true
                    && $bus?->gps_freshness_state === 'LIVE';
            })
            ->assertSee('Estimated')
            ->assertSee('LIVE');
    }

    public function test_tracker_payload_keeps_canonical_routes_only(): void
    {
        $canonicalBus = $this->activeBusWithTrip($this->route1, 'GPS-CAN');
        $legacyBus = $this->activeBusWithTrip($this->legacyRoute, 'GPS-LEG');
        $this->position($canonicalBus, now()->subSeconds(10));
        $this->position($legacyBus, now()->subSeconds(10));

        Livewire::test(Tracker::class)
            ->assertViewHas('routes', fn ($routes) => $routes->pluck('name')->all() === ['Route 2', 'Route 3', 'Route 4'])
            ->assertViewHas('activeBuses', function ($buses) {
                $plates = collect($buses)->pluck('plate_number')->all();

                return in_array('GPS-CAN', $plates, true)
                    && ! in_array('GPS-LEG', $plates, true);
            });
    }

    public function test_guest_tracker_access_remains_functional(): void
    {
        $this->get('/commuter/tracker')
            ->assertOk()
            ->assertSee('Live Bus Tracker');
    }

    public function test_tracker_map_popover_uses_payload_freshness_not_bus_updated_at(): void
    {
        $script = file_get_contents(public_path('js/commuter-dashboard/tracker.js'));

        $this->assertStringContainsString('bus.gps_freshness_state', $script);
        $this->assertStringContainsString('bus.gps_freshness_age_seconds', $script);
        $this->assertStringNotContainsString('bus.updated_at', $script);
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

    private function activeBusWithTrip(Route $route, string $plate, array $attributes = []): Bus
    {
        $bus = Bus::factory()->create(array_merge([
            'plate_number' => $plate,
            'route_id' => $route->id,
            'status' => 'active',
            'eta' => 5,
            'lat' => 14.5,
            'lng' => 121.0,
            'speed' => 10,
            'passengers' => 5,
            'capacity' => 45,
        ], $attributes));

        Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now(),
        ]);

        return $bus;
    }

    private function position(Bus $bus, ?Carbon $lastGpsFixAt): VehiclePosition
    {
        return VehiclePosition::create([
            'bus_id' => $bus->id,
            'trip_id' => Trip::where('bus_id', $bus->id)->latest('id')->value('id'),
            'lat' => $bus->lat,
            'lng' => $bus->lng,
            'heading' => 0,
            'speed' => $bus->speed,
            'status' => 'Moving',
            'last_updated_at' => now(),
            'last_gps_fix_at' => $lastGpsFixAt,
        ]);
    }

    private function busState($buses, string $plate): ?string
    {
        return collect($buses)->firstWhere('plate_number', $plate)?->gps_freshness_state;
    }
}


