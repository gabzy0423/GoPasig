<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\Trip;
use App\Models\TripLog;
use App\Models\TripPassengerEvent;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\TripPassengerEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
        $this->assertSame(0, TripPassengerEvent::count());
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
            ->assertJsonPath('passengers', 5)
            ->assertJsonPath('route_variant_stop_id', null);

        $this->assertSame(5, $this->bus->fresh()->passengers);
        $this->assertSame(1, $this->driver->fresh()->pax_today);
        $this->assertSame(5, $trip->fresh()->peak_passengers);
        $this->assertDatabaseHas('trip_passenger_events', [
            'trip_id' => $trip->id,
            'driver_id' => $this->driver->id,
            'bus_id' => $this->bus->id,
            'route_id' => $this->route->id,
            'route_variant_stop_id' => null,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 1,
            'onboard_after' => 5,
        ]);

        $this->actingAs($this->user)
            ->postJson('/driver/trip/pax', ['change' => -1])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('passengers', 4);

        $this->assertSame(4, $this->bus->fresh()->passengers);
        $this->assertSame(1, $this->driver->fresh()->pax_today);
        $this->assertSame(5, $trip->fresh()->peak_passengers);
        $this->assertDatabaseHas('trip_passenger_events', [
            'trip_id' => $trip->id,
            'driver_id' => $this->driver->id,
            'bus_id' => $this->bus->id,
            'route_id' => $this->route->id,
            'event_type' => TripPassengerEvent::TYPE_ALIGHTED,
            'passenger_delta' => 1,
            'onboard_after' => 4,
        ]);
        $this->assertSame(1, Trip::count());
        $this->assertSame(2, TripPassengerEvent::where('trip_id', $trip->id)->count());
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
        $this->assertSame(0, TripPassengerEvent::count());
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
        $this->assertSame(0, TripPassengerEvent::count());
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
        $this->assertSame(0, TripPassengerEvent::count());
    }

    /** @test */
    public function passenger_event_bounds_use_only_the_accepted_delta(): void
    {
        $this->bus->update([
            'status' => 'operating',
            'capacity' => 10,
            'passengers' => 8,
        ]);
        $this->driver->update(['pax_today' => 4]);

        $trip = Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'route_variant_id' => $this->routeVariant->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now(),
            'dispatched_at' => now()->subMinutes(10),
            'peak_passengers' => 8,
        ]);

        $this->actingAs($this->user)
            ->postJson('/driver/trip/pax', ['change' => 5])
            ->assertOk()
            ->assertJsonPath('passengers', 10)
            ->assertJsonPath('pax_today', 2);

        $this->assertSame(10, $this->bus->fresh()->passengers);
        $this->assertSame(10, $trip->fresh()->peak_passengers);
        $this->assertDatabaseHas('trip_passenger_events', [
            'trip_id' => $trip->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 2,
            'onboard_after' => 10,
        ]);

        $this->actingAs($this->user)
            ->postJson('/driver/trip/pax', ['change' => 3])
            ->assertOk()
            ->assertJsonPath('passengers', 10);

        $this->assertSame(1, TripPassengerEvent::where('trip_id', $trip->id)->count());

        $this->actingAs($this->user)
            ->postJson('/driver/trip/pax', ['change' => -25])
            ->assertOk()
            ->assertJsonPath('passengers', 0);

        $this->assertSame(10, $trip->fresh()->peak_passengers);
        $this->assertSame(2, $this->driver->fresh()->pax_today);
        $this->assertDatabaseHas('trip_passenger_events', [
            'trip_id' => $trip->id,
            'event_type' => TripPassengerEvent::TYPE_ALIGHTED,
            'passenger_delta' => 10,
            'onboard_after' => 0,
        ]);

        $this->actingAs($this->user)
            ->postJson('/driver/trip/pax', ['change' => -1])
            ->assertOk()
            ->assertJsonPath('passengers', 0);

        $this->assertSame(2, TripPassengerEvent::where('trip_id', $trip->id)->count());
    }

    public function test_duplicate_passenger_request_id_is_applied_once(): void
    {
        $trip = $this->createOperatingPassengerTrip();
        $requestId = (string) Str::uuid();

        $this->actingAs($this->user)
            ->postJson('/driver/trip/pax', ['change' => 1, 'request_id' => $requestId])
            ->assertOk()
            ->assertJsonPath('passengers', 1)
            ->assertJsonPath('duplicate', false);

        $this->actingAs($this->user)
            ->postJson('/driver/trip/pax', ['change' => 1, 'request_id' => $requestId])
            ->assertOk()
            ->assertJsonPath('passengers', 1)
            ->assertJsonPath('duplicate', true);

        $this->assertSame(1, $this->bus->fresh()->passengers);
        $this->assertSame(1, $this->driver->fresh()->pax_today);
        $this->assertSame(1, TripPassengerEvent::where('trip_id', $trip->id)->count());
        $this->assertDatabaseHas('trip_passenger_events', [
            'trip_id' => $trip->id,
            'request_id' => $requestId,
            'passenger_delta' => 1,
            'onboard_after' => 1,
        ]);
    }

    public function test_rapid_distinct_passenger_requests_preserve_every_delta_in_order(): void
    {
        $trip = $this->createOperatingPassengerTrip(capacity: 10);

        foreach (range(1, 5) as $expectedPassengers) {
            $this->actingAs($this->user)
                ->postJson('/driver/trip/pax', [
                    'change' => 1,
                    'request_id' => (string) Str::uuid(),
                ])
                ->assertOk()
                ->assertJsonPath('passengers', $expectedPassengers);
        }

        $this->assertSame(5, $this->bus->fresh()->passengers);
        $this->assertSame(5, $this->driver->fresh()->pax_today);
        $this->assertSame(5, $trip->fresh()->peak_passengers);
        $this->assertSame(
            [1, 2, 3, 4, 5],
            TripPassengerEvent::where('trip_id', $trip->id)
                ->orderBy('id')
                ->pluck('onboard_after')
                ->all()
        );
    }

    public function test_passenger_event_failure_rolls_back_bus_driver_and_trip_updates(): void
    {
        $trip = $this->createOperatingPassengerTrip();
        $this->driver->update(['pax_today' => 9]);

        $eventWriter = \Mockery::mock(TripPassengerEventService::class);
        $eventWriter->shouldReceive('record')
            ->once()
            ->andThrow(new \RuntimeException('Forced passenger event failure.'));
        $this->app->instance(TripPassengerEventService::class, $eventWriter);

        $this->actingAs($this->user)
            ->postJson('/driver/trip/pax', [
                'change' => 1,
                'request_id' => (string) Str::uuid(),
            ])
            ->assertStatus(500);

        $this->assertSame(0, $this->bus->fresh()->passengers);
        $this->assertSame(9, $this->driver->fresh()->pax_today);
        $this->assertSame(0, $trip->fresh()->peak_passengers);
        $this->assertSame(0, TripPassengerEvent::where('trip_id', $trip->id)->count());
    }

    public function test_manual_completion_waits_for_passenger_load_to_reach_zero(): void
    {
        $trip = $this->createOperatingPassengerTrip();

        $this->actingAs($this->user)
            ->postJson('/driver/trip/pax', [
                'change' => 1,
                'request_id' => (string) Str::uuid(),
            ])
            ->assertOk();

        $this->actingAs($this->user)
            ->postJson('/driver/trip/toggle', ['status' => 'inactive'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Cannot end the trip while passengers remain onboard. Reach the final stop or record alighting first.'
            );

        $this->assertSame('ongoing', $trip->fresh()->status);
        $this->assertSame('operating', $this->bus->fresh()->status);
        $this->assertSame(1, $this->bus->fresh()->passengers);
        $this->assertDatabaseMissing('trip_logs', ['trip_id' => $trip->id]);

        $this->actingAs($this->user)
            ->postJson('/driver/trip/pax', [
                'change' => -1,
                'request_id' => (string) Str::uuid(),
            ])
            ->assertOk()
            ->assertJsonPath('passengers', 0);

        $this->actingAs($this->user)
            ->postJson('/driver/trip/toggle', ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $tripLog = TripLog::where('trip_id', $trip->id)->firstOrFail();
        $this->assertSame('completed', $trip->fresh()->status);
        $this->assertSame(1, $tripLog->passengers);
        $this->assertSame(1, $tripLog->alighted_passengers);
    }

    public function test_passenger_update_is_rejected_after_trip_finalization(): void
    {
        $trip = $this->createOperatingPassengerTrip();

        app(\App\Services\TripLifecycleService::class)->completeTrip($trip);

        $this->actingAs($this->user)
            ->postJson('/driver/trip/pax', [
                'change' => 1,
                'request_id' => (string) Str::uuid(),
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Passenger management is unavailable because the assigned trip is not currently operating.'
            );

        $this->assertSame('completed', $trip->fresh()->status);
        $this->assertSame('ready', $this->bus->fresh()->status);
        $this->assertSame(0, $this->bus->fresh()->passengers);
        $this->assertSame(0, TripPassengerEvent::where('trip_id', $trip->id)->count());
        $this->assertSame(1, TripLog::where('trip_id', $trip->id)->count());
    }

    public function test_driver_dashboard_pax_today_uses_manila_day_boarded_events(): void
    {
        $trip = $this->createOperatingPassengerTrip();
        $this->driver->update(['pax_today' => 999]);

        TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'driver_id' => $this->driver->id,
            'bus_id' => $this->bus->id,
            'route_id' => $this->route->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 3,
            'onboard_after' => 3,
            'recorded_at' => now(),
        ]);
        TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'driver_id' => $this->driver->id,
            'bus_id' => $this->bus->id,
            'route_id' => $this->route->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 20,
            'onboard_after' => 20,
            'recorded_at' => now()->subDay(),
        ]);

        $stats = app(DashboardService::class)->getDriverStats($this->driver->fresh());

        $this->assertSame(3, $stats->pax_today);
    }

    public function test_driver_trip_ui_queues_rapid_passenger_requests_with_unique_ids(): void
    {
        $this->createOperatingPassengerTrip();

        $this->actingAs($this->user)
            ->get('/driver/trip')
            ->assertOk()
            ->assertSee('passengerRequestQueue', false)
            ->assertSee('crypto.randomUUID', false)
            ->assertSee('request_id: passengerRequest.requestId', false);
    }

    private function createOperatingPassengerTrip(int $passengers = 0, int $capacity = 30): Trip
    {
        $this->bus->update([
            'status' => 'operating',
            'capacity' => $capacity,
            'passengers' => $passengers,
            'route_id' => $this->route->id,
        ]);
        $this->driver->update([
            'assigned_bus' => $this->bus->plate_number,
            'assigned_route' => $this->route->id,
            'status' => 'active',
            'operational_status' => 'driving',
        ]);

        return Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'route_variant_id' => $this->routeVariant->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now(),
            'dispatched_at' => now()->subMinutes(5),
            'peak_passengers' => $passengers,
        ]);
    }
}
