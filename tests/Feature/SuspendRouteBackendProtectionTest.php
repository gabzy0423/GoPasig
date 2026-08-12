<?php

namespace Tests\Feature;

use App\Exceptions\RouteSuspendedException;
use App\Models\Bus;
use App\Models\DispatchLog;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\ServiceAlert;
use App\Models\Trip;
use App\Models\User;
use App\Services\SimulationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuspendRouteBackendProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Route $route;
    protected Bus $bus;
    protected Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->route = Route::factory()->official()->withUsableVariant()->create();

        $this->bus = Bus::create([
            'plate_number' => 'PASIG-001',
            'status' => Bus::STATUS_INACTIVE,
            'driver_name' => Bus::DEFAULT_DRIVER_NAME,
        ]);

        $this->driver = Driver::factory()->create([
            'status' => 'active',
            'operational_status' => 'available',
        ]);
    }

    /** @test */
    public function dispatch_to_active_route_succeeds()
    {
        $trip = SimulationDispatchService::dispatch($this->bus, $this->driver, $this->route, $this->admin->id);

        $this->assertNotNull($trip);
        $this->assertEquals('dispatched', $trip->status);
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'route_id' => $this->route->id]);
    }

    /** @test */
    public function dispatch_to_suspended_route_is_blocked_and_throws_exception()
    {
        $this->route->update(['status' => 'Suspended']);

        ServiceAlert::create([
            'route_id' => $this->route->id,
            'title' => 'Flooding Warning',
            'message' => 'Heavy flood in Pasig area.',
            'severity' => 'critical',
            'status' => 'active',
            'affected_routes' => 'Route 1',
            'suspend_route' => true,
        ]);

        $this->expectException(RouteSuspendedException::class);
        $this->expectExceptionMessage('Dispatch Denied: Route Route 2 is currently suspended by an active Service Alert');

        SimulationDispatchService::dispatch($this->bus, $this->driver, $this->route, $this->admin->id);
    }

    /** @test */
    public function rejected_dispatch_leaves_bus_driver_and_trips_table_unchanged()
    {
        $this->route->update(['status' => 'Suspended']);

        $initialTripCount = Trip::count();
        $initialDispatchLogCount = DispatchLog::count();

        try {
            SimulationDispatchService::dispatch($this->bus, $this->driver, $this->route, $this->admin->id);
        } catch (RouteSuspendedException $e) {
            // Expected exception
        }

        $this->assertEquals($initialTripCount, Trip::count());
        $this->assertEquals($initialDispatchLogCount, DispatchLog::count());

        $this->bus->refresh();
        $this->driver->refresh();

        $this->assertEquals(Bus::STATUS_INACTIVE, $this->bus->status);
        $this->assertEquals('active', $this->driver->status);
        $this->assertEquals('available', $this->driver->operational_status);
    }

    /** @test */
    public function scheduled_dispatch_http_endpoint_returns_422_when_route_is_suspended()
    {
        $this->route->update(['status' => 'Suspended']);

        $schedule = Schedule::create([
            'route_id' => $this->route->id,
            'route_variant_id' => $this->route->variants()->sole()->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '08:00:00',
            'arrival_time' => '10:00:00',
            'service_date' => now()->toDateString(),
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/admin/api/schedules/{$schedule->id}/dispatch");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $schedule->refresh();
        $this->assertNull($schedule->trip);
        $this->assertDatabaseMissing('trips', ['schedule_id' => $schedule->id]);
    }

    /** @test */
    public function existing_ongoing_trips_on_suspended_route_remain_untouched()
    {
        // Create an existing ongoing trip on Route 1
        $ongoingTrip = Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'ongoing',
            'dispatched_at' => now()->subMinutes(30),
            'started_at' => now()->subMinutes(25),
        ]);

        // Route becomes suspended
        $this->route->update(['status' => 'Suspended']);

        // Check that ongoing trip remains active and untouched
        $ongoingTrip->refresh();
        $this->assertEquals('ongoing', $ongoingTrip->status);
        $this->assertEquals($this->route->id, $ongoingTrip->route_id);
    }
}
