<?php

namespace Tests\Feature;

use App\Exceptions\DuplicateDispatchException;
use App\Exceptions\RouteSuspendedException;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\ServiceAlert;
use App\Models\Trip;
use App\Models\User;
use App\Services\ScheduleConflictService;
use App\Services\SimulationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrectiveDispatchValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Route $route;
    protected Bus $bus;
    protected Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->route = Route::factory()->official()->withUsableVariant()->create();

        $this->bus = Bus::create([
            'plate_number' => 'PAS-001',
            'status' => Bus::STATUS_INACTIVE,
            'driver_name' => Bus::DEFAULT_DRIVER_NAME,
        ]);

        $this->driver = Driver::factory()->create([
            'status' => 'active',
            'operational_status' => 'available',
        ]);
    }

    /** @test */
    public function uat_c01_active_route_manual_dispatch_succeeds_despite_unrelated_schedules()
    {
        // Seed an unrelated schedule on the bus and driver earlier in the day
        Schedule::create([
            'route_id' => $this->route->id,
            'route_variant_id' => $this->route->variants()->sole()->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '12:00:00',
            'arrival_time' => '12:25:00',
            'service_date' => now()->toDateString(),
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        // Manual dispatch requested later in the day
        $trip = SimulationDispatchService::dispatch($this->bus, $this->driver, $this->route, $this->admin->id);

        $this->assertNotNull($trip);
        $this->assertEquals('dispatched', $trip->status);
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'bus_id' => $this->bus->id, 'driver_id' => $this->driver->id]);
    }

    /** @test */
    public function uat_c02_suspended_route_dispatch_remains_blocked()
    {
        $this->route->update(['status' => 'Suspended']);

        ServiceAlert::create([
            'route_id' => $this->route->id,
            'title' => 'Emergency Closure',
            'message' => 'Route suspended.',
            'severity' => 'critical',
            'status' => 'active',
            'affected_routes' => 'Route 2',
            'suspend_route' => true,
        ]);

        $this->expectException(RouteSuspendedException::class);
        SimulationDispatchService::dispatch($this->bus, $this->driver, $this->route, $this->admin->id);
    }

    /** @test */
    public function uat_c03_duplicate_operational_dispatch_remains_blocked()
    {
        // Start a trip for the bus
        Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'ongoing',
        ]);

        $this->expectException(\App\Exceptions\BusUnavailableException::class);
        SimulationDispatchService::dispatch($this->bus, $this->driver, $this->route, $this->admin->id);
    }

    /** @test */
    public function uat_c04_exact_scheduled_dispatch_succeeds_and_prevents_duplicate_dispatch()
    {
        $schedule = Schedule::create([
            'route_id' => $this->route->id,
            'route_variant_id' => $this->route->variants()->sole()->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '14:00:00',
            'arrival_time' => '14:30:00',
            'service_date' => now()->toDateString(),
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/admin/api/schedules/{$schedule->id}/dispatch");

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $schedule->refresh();
        $this->assertNotNull($schedule->trip);
        $this->assertEquals($schedule->id, $schedule->trip->schedule_id);

        // Second dispatch attempt of the same schedule must fail
        $secondResponse = $this->actingAs($this->admin)->postJson("/admin/api/schedules/{$schedule->id}/dispatch");
        $secondResponse->assertStatus(422);
    }

    /** @test */
    public function uat_c05_schedule_authoring_conflict_validation_remains_functional()
    {
        // Verify ScheduleConflictService remains functional for timetable management
        $validation = ScheduleConflictService::validateSchedule(
            $this->route->id,
            $this->bus->id,
            $this->driver->id,
            '08:00:00',
            30
        );

        $this->assertIsArray($validation);
        $this->assertArrayHasKey('valid', $validation);
    }
}
