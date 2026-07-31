<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Trip;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\VehiclePosition;
use App\Models\GPSLog;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Schedule;
use App\Models\Stop;
use App\Enums\TripStatus;
use App\Enums\GpsSessionStatus;
use App\Services\TripLifecycleService;
use App\Services\SimulationDispatchService;
use Illuminate\Support\Facades\Event;
use App\Events\TripStarted;
use App\Events\TripCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TripLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_valid_operational_workflow()
    {
        Event::fake();

        // 1. Initial State: Standby/free resources
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);
        $route = Route::factory()->create();

        $this->assertEquals('inactive', $bus->status);
        $this->assertEquals('available', $driver->operational_status);

        // 2. Dispatch Assignment
        $trip = SimulationDispatchService::dispatch($bus, $driver, $route);

        $bus->refresh();
        $driver->refresh();
        $trip->refresh();

        // Verify Dispatched State
        $this->assertEquals('ready', $bus->status);
        $this->assertEquals('assigned', $driver->operational_status);
        $this->assertEquals('dispatched', $trip->status);
        $this->assertEquals('OFF', $trip->gps_session);
        $this->assertNull($trip->started_at);
        $this->assertNotNull($trip->dispatched_at);

        // 3. Start Live Trip Session
        $lifecycleService = app(TripLifecycleService::class);
        $lifecycleService->startTrip($trip);

        $bus->refresh();
        $driver->refresh();
        $trip->refresh();

        // Verify Ongoing State
        $this->assertEquals('operating', $bus->status);
        $this->assertEquals('driving', $driver->operational_status);
        $this->assertEquals('ongoing', $trip->status);
        $this->assertEquals('ACTIVE', $trip->gps_session);
        $this->assertNotNull($trip->started_at);
        $this->assertNotNull($trip->gps_session_started_at);

        // 4. End Live Trip Session
        $lifecycleService->completeTrip($trip);

        $bus->refresh();
        $driver->refresh();
        $trip->refresh();

        // Verify point-to-point leg completion keeps the operational assignment.
        $this->assertEquals('ready', $bus->status);
        $this->assertEquals('assigned', $driver->operational_status);
        $this->assertSame($route->id, $bus->route_id);
        $this->assertSame($bus->plate_number, $driver->assigned_bus);
        $this->assertSame((string) $route->id, (string) $driver->assigned_route);
        $this->assertEquals('completed', $trip->status);
        $this->assertEquals('CLOSED', $trip->gps_session);
        $this->assertNotNull($trip->ended_at);
    }

    public function test_invalid_lifecycle_transitions_are_rejected()
    {
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);
        $route = Route::factory()->create();

        // Dispatch first
        $trip = SimulationDispatchService::dispatch($bus, $driver, $route);
        $lifecycleService = app(TripLifecycleService::class);

        // A. Dispatched trip cannot be ended directly
        $this->expectException(\InvalidArgumentException::class);
        $lifecycleService->completeTrip($trip);
    }

    public function test_start_session_for_completed_trip_is_rejected()
    {
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);
        $route = Route::factory()->create();

        // Dispatch
        $trip = SimulationDispatchService::dispatch($bus, $driver, $route);
        $lifecycleService = app(TripLifecycleService::class);

        // Start and then complete
        $lifecycleService->startTrip($trip);
        $lifecycleService->completeTrip($trip);

        // B. Completed trip cannot be started again
        $this->expectException(\InvalidArgumentException::class);
        $lifecycleService->startTrip($trip);
    }

    public function test_gps_telemetry_ingestion_guard_protection()
    {
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);
        $route = Route::factory()->create();

        // Dispatch (GPS Session = OFF, status = dispatched)
        $trip = SimulationDispatchService::dispatch($bus, $driver, $route);

        // Simulate API route location update when GPS Session is OFF
        $response = $this->postJson(route('api.driver.location', $trip->id), [
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 20,
            'heading' => 90,
            'accuracy' => 10,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Verify rejected with 409 Conflict
        $response->assertStatus(409);

        // Start Live Session (GPS Session = ACTIVE, status = ongoing)
        $lifecycleService = app(TripLifecycleService::class);
        $lifecycleService->startTrip($trip);

        // Simulate API location update again
        $responseActive = $this->postJson(route('api.driver.location', $trip->id), [
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 20,
            'heading' => 90,
            'accuracy' => 10,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Verify processed synchronously while the session is active.
        $responseActive->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['log_id', 'processing_ms']);

        $this->assertDatabaseHas('gps_logs', [
            'trip_id' => $trip->id,
            'lat' => 14.5593,
            'processing_status' => 'processed',
        ]);

        $position = VehiclePosition::where('bus_id', $bus->id)->first();
        $this->assertNotNull($position);
        $this->assertEquals($trip->id, $position->trip_id);

        // Complete Live Session (GPS Session = CLOSED)
        $lifecycleService->completeTrip($trip);

        // Simulate API location update after completion
        $responseClosed = $this->postJson(route('api.driver.location', $trip->id), [
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 20,
            'heading' => 90,
            'accuracy' => 10,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Verify rejected with 409 Conflict
        $responseClosed->assertStatus(409);
    }

    public function test_complete_leg_preserves_direction_assignment_and_planned_schedules(): void
    {
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);
        $route = Route::factory()->create();
        $variant = $this->variantFor($route, 'outbound');

        $trip = SimulationDispatchService::dispatch($bus, $driver, $route, null, 'Directional dispatch.', $variant);
        app(TripLifecycleService::class)->startTrip($trip);

        $futureSchedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now('Asia/Manila')->addDay()->toDateString(),
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        GPSLog::create([
            'trip_id' => $trip->id,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 5,
            'timestamp' => now(),
            'processing_status' => 'processed',
        ]);

        app(TripLifecycleService::class)->completeTrip($trip);

        $trip->refresh();
        $bus->refresh();
        $driver->refresh();
        $futureSchedule->refresh();

        $this->assertSame('completed', $trip->status);
        $this->assertSame('CLOSED', $trip->gps_session);
        $this->assertNotNull($trip->ended_at);
        $this->assertSame($bus->id, $trip->bus_id);
        $this->assertSame($driver->id, $trip->driver_id);
        $this->assertSame($route->id, $trip->route_id);
        $this->assertSame($variant->id, $trip->route_variant_id);

        $this->assertSame('ready', $bus->status);
        $this->assertSame($route->id, $bus->route_id);
        $this->assertSame($driver->first_name . ' ' . $driver->last_name, $bus->driver_name);
        $this->assertSame('assigned', $driver->operational_status);
        $this->assertSame($bus->plate_number, $driver->assigned_bus);
        $this->assertSame((string) $route->id, (string) $driver->assigned_route);

        $this->assertSame(Schedule::STATUS_ON_TIME, $futureSchedule->status);
        $this->assertSame(1, Trip::where('bus_id', $bus->id)->count());
        $this->assertSame(1, GPSLog::where('trip_id', $trip->id)->count());
    }

    public function test_completed_leg_rejects_more_telemetry_without_deleting_history(): void
    {
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);
        $route = Route::factory()->create();

        $trip = SimulationDispatchService::dispatch($bus, $driver, $route);
        app(TripLifecycleService::class)->startTrip($trip);

        $accepted = $this->postJson(route('api.driver.location', $trip->id), [
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 20,
            'heading' => 90,
            'accuracy' => 10,
            'timestamp' => now()->toIso8601String(),
        ]);
        $accepted->assertOk();
        $historyCount = GPSLog::where('trip_id', $trip->id)->count();

        app(TripLifecycleService::class)->completeTrip($trip);

        $closed = $this->postJson(route('api.driver.location', $trip->id), [
            'lat' => 14.5594,
            'lng' => 121.0806,
            'speed' => 20,
            'heading' => 90,
            'accuracy' => 10,
            'timestamp' => now()->toIso8601String(),
        ]);

        $closed->assertStatus(409);
        $this->assertSame($historyCount, GPSLog::where('trip_id', $trip->id)->count());
        $this->assertSame('CLOSED', $trip->fresh()->gps_session);
    }

    public function test_starting_linked_trip_sets_only_linked_schedule_actual_departure_time(): void
    {
        $bus = Bus::factory()->create(['status' => 'ready']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'assigned']);
        $route = Route::factory()->create();
        $variant = $this->variantFor($route, 'outbound');

        $linkedSchedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now('Asia/Manila')->toDateString(),
            'status' => Schedule::STATUS_ON_TIME,
            'actual_departure_time' => null,
            'actual_arrival_time' => null,
        ]);

        $unlinkedSchedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now('Asia/Manila')->toDateString(),
            'status' => Schedule::STATUS_DELAYED,
            'actual_departure_time' => null,
            'actual_arrival_time' => null,
        ]);

        $trip = Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'schedule_id' => $linkedSchedule->id,
            'status' => TripStatus::DISPATCHED->value,
            'gps_session' => GpsSessionStatus::OFF->value,
            'started_at' => null,
            'gps_session_started_at' => null,
        ]);

        app(TripLifecycleService::class)->startTrip($trip);

        $trip->refresh();
        $linkedSchedule->refresh();
        $unlinkedSchedule->refresh();

        $this->assertSame('ongoing', $trip->status);
        $this->assertSame('ACTIVE', $trip->gps_session);
        $this->assertNotNull($linkedSchedule->actual_departure_time);
        $this->assertNull($linkedSchedule->actual_arrival_time);
        $this->assertSame(Schedule::STATUS_ON_TIME, $linkedSchedule->status);
        $this->assertNull($unlinkedSchedule->actual_departure_time);
        $this->assertNull($unlinkedSchedule->actual_arrival_time);
        $this->assertSame(Schedule::STATUS_DELAYED, $unlinkedSchedule->status);
    }

    public function test_starting_ad_hoc_trip_updates_no_schedule(): void
    {
        $bus = Bus::factory()->create(['status' => 'ready']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'assigned']);
        $route = Route::factory()->create();
        $variant = $this->variantFor($route, 'outbound');
        $schedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now('Asia/Manila')->toDateString(),
            'actual_departure_time' => null,
            'actual_arrival_time' => null,
        ]);

        $trip = Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'schedule_id' => null,
            'status' => TripStatus::DISPATCHED->value,
            'gps_session' => GpsSessionStatus::OFF->value,
        ]);

        app(TripLifecycleService::class)->startTrip($trip);

        $schedule->refresh();

        $this->assertNull($schedule->actual_departure_time);
        $this->assertNull($schedule->actual_arrival_time);
    }

    public function test_completing_linked_trip_sets_only_linked_schedule_actual_arrival_time(): void
    {
        $bus = Bus::factory()->create(['status' => 'operating']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'driving']);
        $route = Route::factory()->create();
        $variant = $this->variantFor($route, 'outbound');

        $linkedSchedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now('Asia/Manila')->toDateString(),
            'status' => Schedule::STATUS_ON_TIME,
            'actual_departure_time' => '08:05:00',
            'actual_arrival_time' => null,
        ]);

        $unlinkedSchedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now('Asia/Manila')->toDateString(),
            'actual_departure_time' => null,
            'actual_arrival_time' => null,
        ]);

        $trip = Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'schedule_id' => $linkedSchedule->id,
            'status' => TripStatus::ONGOING->value,
            'gps_session' => GpsSessionStatus::ACTIVE->value,
        ]);

        app(TripLifecycleService::class)->completeTrip($trip);

        $trip->refresh();
        $linkedSchedule->refresh();
        $unlinkedSchedule->refresh();

        $this->assertSame('completed', $trip->status);
        $this->assertSame('CLOSED', $trip->gps_session);
        $this->assertSame('08:05:00', $linkedSchedule->actual_departure_time);
        $this->assertNotNull($linkedSchedule->actual_arrival_time);
        $this->assertSame(Schedule::STATUS_ON_TIME, $linkedSchedule->status);
        $this->assertNull($unlinkedSchedule->actual_departure_time);
        $this->assertNull($unlinkedSchedule->actual_arrival_time);
    }

    public function test_completing_ad_hoc_trip_does_not_apply_driver_schedule_heuristic(): void
    {
        $bus = Bus::factory()->create(['status' => 'operating']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'driving']);
        $route = Route::factory()->create();
        $variant = $this->variantFor($route, 'outbound');

        $firstSchedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now('Asia/Manila')->toDateString(),
            'departure_time' => '07:00',
            'actual_departure_time' => null,
            'actual_arrival_time' => null,
        ]);

        $secondSchedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now('Asia/Manila')->toDateString(),
            'departure_time' => '08:00',
            'actual_departure_time' => null,
            'actual_arrival_time' => null,
        ]);

        $trip = Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'schedule_id' => null,
            'status' => TripStatus::ONGOING->value,
            'gps_session' => GpsSessionStatus::ACTIVE->value,
        ]);

        app(TripLifecycleService::class)->completeTrip($trip);

        $firstSchedule->refresh();
        $secondSchedule->refresh();

        $this->assertNull($firstSchedule->actual_departure_time);
        $this->assertNull($firstSchedule->actual_arrival_time);
        $this->assertNull($secondSchedule->actual_departure_time);
        $this->assertNull($secondSchedule->actual_arrival_time);
    }
    private function variantFor(Route $route, string $direction): RouteVariant
    {
        Stop::create(['route_id' => $route->id, 'name' => 'SPED', 'lat' => 14.5000, 'lng' => 121.0000, 'sequence' => 1]);
        Stop::create(['route_id' => $route->id, 'name' => 'Ligaya', 'lat' => 14.5100, 'lng' => 121.0100, 'sequence' => 2]);

        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => $direction,
            'origin_name' => 'SPED',
            'destination_name' => 'Ligaya',
            'polyline_coordinates' => [[14.5000, 121.0000], [14.5100, 121.0100]],
            'geometry_version' => 1,
            'geometry_status' => 'valid',
            'is_default' => true,
        ]);

        RouteVariantStop::create(['route_variant_id' => $variant->id, 'name' => 'SPED', 'lat' => 14.5000, 'lng' => 121.0000, 'radius_meters' => 50, 'sequence' => 1]);
        RouteVariantStop::create(['route_variant_id' => $variant->id, 'name' => 'Ligaya', 'lat' => 14.5100, 'lng' => 121.0100, 'radius_meters' => 50, 'sequence' => 2]);

        return $variant;
    }
}