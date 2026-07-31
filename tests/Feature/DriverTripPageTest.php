<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverTripPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Driver $driver;
    protected Route $route;
    protected RouteVariant $routeVariant;
    protected Bus $bus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'driver']);

        $this->route = Route::create([
            'name' => 'Route 1',
            'status' => 'Active',
        ]);

        $this->routeVariant = RouteVariant::create([
            'route_id' => $this->route->id,
            'direction' => 'outbound',
            'origin_name' => 'Pasig Mega Market',
            'destination_name' => 'San Joaquin',
            'is_default' => true,
        ]);

        $this->bus = Bus::create([
            'plate_number' => 'PAS-001',
            'status' => Bus::STATUS_INACTIVE,
            'driver_name' => Bus::DEFAULT_DRIVER_NAME,
        ]);

        $this->driver = Driver::factory()->create([
            'user_id' => $this->user->id,
            'assigned_bus' => 'PAS-001',
            'assigned_route' => $this->route->id,
            'status' => 'active',
            'operational_status' => 'available',
        ]);
    }

    /** @test */
    public function test1_driver_with_ongoing_trip_loads_page_with_active_trip_and_variant()
    {
        $trip = Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'route_variant_id' => $this->routeVariant->id,
            'status' => 'ongoing',
            'dispatched_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get('/driver/trip');

        $response->assertStatus(200);
        $response->assertViewHas('activeTrip', function ($viewTrip) use ($trip) {
            return $viewTrip && $viewTrip->id === $trip->id && $viewTrip->relationLoaded('routeVariant');
        });
    }

    /** @test */
    public function test2_driver_with_dispatched_trip_loads_page_with_dispatched_active_trip()
    {
        $trip = Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'route_variant_id' => $this->routeVariant->id,
            'status' => 'dispatched',
            'dispatched_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get('/driver/trip');

        $response->assertStatus(200);
        $response->assertViewHas('activeTrip', function ($viewTrip) use ($trip) {
            return $viewTrip && $viewTrip->id === $trip->id;
        });
    }

    /** @test */
    public function test3_driver_with_no_active_trip_renders_page_with_null_active_trip()
    {
        $response = $this->actingAs($this->user)->get('/driver/trip');

        $response->assertStatus(200);
        $response->assertViewHas('activeTrip', null);
    }

    /** @test */
    public function test4_user_without_driver_profile_is_redirected_by_role_middleware()
    {
        $userNoDriver = User::factory()->create(['role' => 'driver']);

        $response = $this->actingAs($userNoDriver)->get('/driver/trip');

        $response->assertStatus(302);
    }

    /** @test */
    public function test5_deterministic_priority_selects_ongoing_trip_over_dispatched_trip()
    {
        $dispatchedTrip = Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'dispatched',
            'dispatched_at' => now()->subMinute(),
        ]);

        $otherBus = Bus::create(['plate_number' => 'PAS-002', 'status' => Bus::STATUS_INACTIVE]);
        $ongoingTrip = Trip::create([
            'bus_id' => $otherBus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'ongoing',
            'dispatched_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get('/driver/trip');

        $response->assertStatus(200);
        $response->assertViewHas('activeTrip', function ($viewTrip) use ($ongoingTrip) {
            return $viewTrip && $viewTrip->id === $ongoingTrip->id;
        });
    }

    /** @test */
    public function test6_non_active_trip_statuses_are_excluded()
    {
        Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'completed',
            'dispatched_at' => now()->subHour(),
        ]);

        Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'cancelled',
            'dispatched_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($this->user)->get('/driver/trip');

        $response->assertStatus(200);
        $response->assertViewHas('activeTrip', null);
    }
    /** @test */
    public function passenger_updates_are_rejected_before_the_assigned_trip_is_operating(): void
    {
        $this->bus->update([
            'status' => 'ready',
            'passengers' => 4,
        ]);
        $this->driver->update(['pax_today' => 7]);

        Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'route_variant_id' => $this->routeVariant->id,
            'status' => 'dispatched',
            'gps_session' => 'OFF',
            'started_at' => null,
            'dispatched_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->postJson('/driver/trip/pax', ['change' => 1]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Passenger management is unavailable because the assigned trip is not currently operating.');

        $this->assertSame(4, $this->bus->fresh()->passengers);
        $this->assertSame(7, $this->driver->fresh()->pax_today);
        $this->assertSame(1, Trip::count());
    }

    /** @test */
    public function passenger_updates_succeed_only_for_the_operating_assigned_trip(): void
    {
        $this->bus->update([
            'status' => 'operating',
            'capacity' => 30,
            'passengers' => 4,
        ]);
        $this->driver->update(['pax_today' => 7]);

        $trip = Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'route_variant_id' => $this->routeVariant->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now(),
            'dispatched_at' => now()->subMinutes(5),
            'peak_passengers' => 4,
        ]);

        $this->actingAs($this->user)
            ->postJson('/driver/trip/pax', ['change' => 1])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('passengers', 5);

        $this->assertSame(5, $this->bus->fresh()->passengers);
        $this->assertSame(8, $this->driver->fresh()->pax_today);
        $this->assertSame(5, $trip->fresh()->peak_passengers);

        $this->actingAs($this->user)
            ->postJson('/driver/trip/pax', ['change' => -1])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('passengers', 4);

        $this->assertSame(4, $this->bus->fresh()->passengers);
        $this->assertSame(8, $this->driver->fresh()->pax_today);
        $this->assertSame(1, Trip::count());
    }

    /** @test */
    public function passenger_updates_are_rejected_after_the_trip_stops_operating(): void
    {
        $this->bus->update([
            'status' => 'ready',
            'passengers' => 6,
        ]);
        $this->driver->update(['pax_today' => 9]);

        Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'route_variant_id' => $this->routeVariant->id,
            'status' => 'completed',
            'gps_session' => 'CLOSED',
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'dispatched_at' => now()->subHours(2),
        ]);

        $this->actingAs($this->user)
            ->postJson('/driver/trip/pax', ['change' => -1])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertSame(6, $this->bus->fresh()->passengers);
        $this->assertSame(9, $this->driver->fresh()->pax_today);
        $this->assertSame(1, Trip::count());
    }
    /** @test */
    public function passenger_updates_are_rejected_when_no_assigned_trip_exists(): void
    {
        $this->bus->update([
            'status' => 'operating',
            'passengers' => 3,
        ]);
        $this->driver->update(['pax_today' => 2]);

        $this->actingAs($this->user)
            ->postJson('/driver/trip/pax', ['change' => 1])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertSame(3, $this->bus->fresh()->passengers);
        $this->assertSame(2, $this->driver->fresh()->pax_today);
        $this->assertSame(0, Trip::count());
    }

    /** @test */
    public function passenger_updates_are_rejected_when_the_assigned_bus_is_not_operating(): void
    {
        $this->bus->update([
            'status' => Bus::STATUS_BREAKDOWN,
            'passengers' => 5,
        ]);
        $this->driver->update(['pax_today' => 4]);

        Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'route_variant_id' => $this->routeVariant->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now(),
            'dispatched_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($this->user)
            ->postJson('/driver/trip/pax', ['change' => 1])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertSame(5, $this->bus->fresh()->passengers);
        $this->assertSame(4, $this->driver->fresh()->pax_today);
        $this->assertSame(1, Trip::count());
    }
}
