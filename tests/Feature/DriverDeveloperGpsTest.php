<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\GPSLog;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Trip;
use App\Models\User;
use App\Models\VehiclePosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DriverDeveloperGpsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Driver $driver;
    private Bus $bus;
    private Route $route;
    private RouteVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'driver']);
        $this->route = Route::create([
            'name' => 'Route 2',
            'status' => 'Active',
        ]);
        $this->variant = RouteVariant::create([
            'route_id' => $this->route->id,
            'direction' => 'outbound',
            'origin_name' => 'SPED',
            'destination_name' => 'Ligaya',
            'is_default' => true,
        ]);
        RouteVariantStop::create([
            'route_variant_id' => $this->variant->id,
            'name' => 'SPED',
            'lat' => 14.5600,
            'lng' => 121.0800,
            'sequence' => 1,
        ]);
        RouteVariantStop::create([
            'route_variant_id' => $this->variant->id,
            'name' => 'Ligaya',
            'lat' => 14.5700,
            'lng' => 121.0900,
            'sequence' => 2,
        ]);

        $this->bus = Bus::factory()->create([
            'plate_number' => 'DEV-GPS-001',
            'status' => 'operating',
            'route_id' => $this->route->id,
            'lat' => 14.5590,
            'lng' => 121.0790,
        ]);
        $this->driver = Driver::factory()->create([
            'user_id' => $this->user->id,
            'assigned_bus' => $this->bus->plate_number,
            'assigned_route' => $this->route->id,
            'status' => 'active',
            'operational_status' => 'driving',
        ]);
    }

    public function test_driver_developer_gps_is_not_available_outside_local_environment(): void
    {
        $this->makeOngoingTrip();
        Config::set('app.env', 'testing');

        $this->actingAs($this->user)
            ->postJson(route('driver.trip.developer-gps'), [
                'lat' => 14.5600,
                'lng' => 121.0800,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('vehicle_positions', 0);
        $this->assertSame(14.5590, (float) $this->bus->fresh()->lat);
    }

    public function test_driver_developer_gps_requires_an_ongoing_trip(): void
    {
        Config::set('app.env', 'local');

        $this->actingAs($this->user)
            ->postJson(route('driver.trip.developer-gps'), [
                'lat' => 14.5600,
                'lng' => 121.0800,
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_driver_developer_gps_updates_only_local_bus_preview_state(): void
    {
        $trip = $this->makeOngoingTrip();
        Config::set('app.env', 'local');

        $this->actingAs($this->user)
            ->postJson(route('driver.trip.developer-gps'), [
                'lat' => 14.5600,
                'lng' => 121.0800,
                'speed' => 0,
                'accuracy' => 5,
                'next_stop' => 'SPED',
                'eta' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('source', 'developer')
            ->assertJsonPath('trip_id', $trip->id);

        $this->assertSame(14.5600, (float) $this->bus->fresh()->lat);
        $this->assertSame(121.0800, (float) $this->bus->fresh()->lng);
        $this->assertSame('operating', $this->bus->fresh()->status);
        $this->assertSame('ongoing', $trip->fresh()->status);
        $this->assertDatabaseHas('vehicle_positions', [
            'bus_id' => $this->bus->id,
            'trip_id' => $trip->id,
            'lat' => 14.5600,
            'lng' => 121.0800,
            'gps_quality_state' => 'DEVELOPER',
        ]);
        $this->assertSame(0, GPSLog::where('trip_id', $trip->id)->count());
    }

    public function test_driver_trip_page_exposes_route_variant_stop_presets_only_in_local_mode(): void
    {
        $this->makeOngoingTrip();
        Config::set('app.env', 'local');

        $this->actingAs($this->user)
            ->get(route('driver.trip'))
            ->assertOk()
            ->assertSee('Developer GPS')
            ->assertSee('Origin - SPED')
            ->assertSee('Destination - Ligaya');

        Config::set('app.env', 'testing');

        $this->actingAs($this->user)
            ->get(route('driver.trip'))
            ->assertOk()
            ->assertDontSee('Developer GPS');
    }

    private function makeOngoingTrip(): Trip
    {
        return Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'route_variant_id' => $this->variant->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now(),
            'dispatched_at' => now()->subMinute(),
        ]);
    }
}
