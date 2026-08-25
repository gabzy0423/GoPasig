<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Trip;
use App\Models\TripPassengerEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FleetUtilizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-14 10:00:00', 'Asia/Manila'));
        Cache::forget('routes_all');
        $this->actingAs(User::factory()->create(['role' => 'fleet_manager']));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_utilization_endpoint_uses_actual_trip_events_and_peak_load(): void
    {
        $route = $this->officialRoute('Route 2');
        $this->officialRoute('Route 3');
        $this->officialRoute('Route 4');
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create([
            'status' => 'operating',
            'route_id' => $route->id,
            'capacity' => 40,
        ]);

        $trip = Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'peak_passengers' => 30,
            'dispatched_at' => now()->subHour(),
            'started_at' => now()->subMinutes(45),
            'ended_at' => null,
        ]);

        TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 18,
            'onboard_after' => 18,
            'recorded_at' => now()->subMinutes(20),
        ]);

        TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
            'event_type' => TripPassengerEvent::TYPE_ALIGHTED,
            'passenger_delta' => 4,
            'onboard_after' => 14,
            'recorded_at' => now()->subMinutes(10),
        ]);

        $payload = $this->getJson(route('fleet.api.utilization-data'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $card = collect($payload['busCards'])->firstWhere('plate', $bus->plate_number);

        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], collect($payload['routes'])->pluck('name')->all());
        $this->assertSame('Operating', $card['status']);
        $this->assertSame(1, $card['trips']);
        $this->assertSame(18, $card['boarded']);
        $this->assertSame(30, $card['peak_load']);
        $this->assertSame(75, $card['util']);
        $this->assertNull($card['distance']);
        $this->assertSame('Route 2', $card['routeLabel']);
    }

    public function test_schedule_rows_do_not_create_fleet_utilization_activity(): void
    {
        $route = $this->officialRoute('Route 2');
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create([
            'status' => 'ready',
            'route_id' => $route->id,
            'capacity' => 40,
        ]);

        Schedule::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now()->toDateString(),
            'passengers' => 999,
        ]);

        $payload = $this->getJson(route('fleet.api.utilization-data'))
            ->assertOk()
            ->json();
        $card = collect($payload['busCards'])->firstWhere('plate', $bus->plate_number);

        $this->assertSame('Ready', $card['status']);
        $this->assertSame(0, $card['trips']);
        $this->assertSame(0, $card['boarded']);
        $this->assertSame(0, $card['peak_load']);
        $this->assertSame(0, $card['util']);
        $this->assertSame(0, collect($payload['chartData'])->last()['deployed']);
    }

    public function test_daily_deployment_chart_uses_started_official_trips_only(): void
    {
        $officialRoute = $this->officialRoute('Route 2');
        $legacyRoute = Route::factory()->create(['name' => 'Route A', 'status' => 'Active']);
        $driver = Driver::factory()->create();
        $officialBus = Bus::factory()->create(['route_id' => $officialRoute->id]);
        $legacyBus = Bus::factory()->create(['route_id' => $legacyRoute->id]);

        Trip::factory()->create([
            'bus_id' => $officialBus->id,
            'driver_id' => $driver->id,
            'route_id' => $officialRoute->id,
            'status' => 'completed',
            'started_at' => now()->setTime(6, 0),
            'ended_at' => now()->setTime(7, 0),
        ]);

        Trip::factory()->create([
            'bus_id' => $legacyBus->id,
            'driver_id' => $driver->id,
            'route_id' => $legacyRoute->id,
            'status' => 'completed',
            'started_at' => now()->setTime(6, 0),
            'ended_at' => now()->setTime(7, 0),
        ]);

        $payload = $this->getJson(route('fleet.api.utilization-data'))
            ->assertOk()
            ->json();
        $today = collect($payload['chartData'])->last();
        $legacyCard = collect($payload['busCards'])->firstWhere('plate', $legacyBus->plate_number);

        $this->assertSame(1, $today['deployed']);
        $this->assertSame(50, $today['deployed_percent']);
        $this->assertSame(['Route 2'], collect($payload['routes'])->pluck('name')->all());
        $this->assertSame('Standby', $legacyCard['routeLabel']);
        $this->assertNull($legacyCard['route']);
    }

    public function test_fragment_contains_actual_utilization_contract_and_refresh_endpoint(): void
    {
        $view = file_get_contents(resource_path('views/fleet/utilization/index.blade.php'));

        $this->assertStringContainsString("route('fleet.api.utilization-data')", $view);
        $this->assertStringContainsString("registerPoller('utilization', 'utilization-data'", $view);
        $this->assertStringContainsString('Daily deployment over 30 days', $view);
        $this->assertStringContainsString('Recorded boarded', $view);
        $this->assertStringContainsString('Distance', $view);
        $this->assertStringNotContainsString('Schedule::where', $view);
        $this->assertStringNotContainsString('Target 80%', $view);
        $this->assertStringNotContainsString('18 km', $view);
    }

    public function test_utilization_fragment_renders_with_the_shared_actual_snapshot(): void
    {
        $response = $this->getJson(route('fleet.dashboard', [
            'tab' => 'utilization',
            'fragment' => 1,
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tab', 'utilization');
        $this->assertStringContainsString('Daily deployment over 30 days', $response->json('html'));
        $this->assertStringContainsString('data-utilization-endpoint', $response->json('html'));
    }

    private function officialRoute(string $name): Route
    {
        return Route::factory()->official($name)->create();
    }
}
