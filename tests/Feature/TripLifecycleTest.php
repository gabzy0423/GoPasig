<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Trip;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\VehiclePosition;
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

        // 1. Initial State: Available resources
        $bus = Bus::factory()->create(['status' => 'available']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);
        $route = Route::factory()->create();

        $this->assertEquals('available', $bus->status);
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

        // Verify Completed/Returned State
        $this->assertEquals('available', $bus->status);
        $this->assertEquals('available', $driver->operational_status);
        $this->assertEquals('completed', $trip->status);
        $this->assertEquals('CLOSED', $trip->gps_session);
        $this->assertNotNull($trip->ended_at);
    }

    public function test_invalid_lifecycle_transitions_are_rejected()
    {
        $bus = Bus::factory()->create(['status' => 'available']);
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
        $bus = Bus::factory()->create(['status' => 'available']);
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
        $bus = Bus::factory()->create(['status' => 'available']);
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
}

