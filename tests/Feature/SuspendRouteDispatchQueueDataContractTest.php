<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\ServiceAlert;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuspendRouteDispatchQueueDataContractTest extends TestCase
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

        $this->route = Route::create([
            'name' => 'Route 1',
            'status' => 'Active',
        ]);

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
    public function test1_active_route_returns_normal_payload()
    {
        $schedule = Schedule::create([
            'route_id' => $this->route->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '10:00:00',
            'arrival_time' => '10:30:00',
            'service_date' => now('Asia/Manila')->toDateString(),
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/api/schedules/dispatch-queue/today');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'dispatches' => [
                    [
                        'id' => $schedule->id,
                        'isRouteSuspended' => false,
                        'suspensionReason' => null,
                        'remainingActiveTrips' => 0,
                        'dispatchState' => 'ready',
                        'canDispatch' => true,
                    ]
                ]
            ]);
    }

    /** @test */
    public function test2_suspended_route_with_active_alert_returns_suspended_state()
    {
        $this->route->update(['status' => 'Suspended']);

        $alert = ServiceAlert::create([
            'route_id' => $this->route->id,
            'title' => 'Flooding along Route 1',
            'message' => 'Route 1 is closed due to high water level.',
            'severity' => 'critical',
            'status' => 'active',
            'affected_routes' => 'Route 1',
            'suspend_route' => true,
        ]);

        $schedule = Schedule::create([
            'route_id' => $this->route->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '10:00:00',
            'arrival_time' => '10:30:00',
            'service_date' => now('Asia/Manila')->toDateString(),
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/api/schedules/dispatch-queue/today');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'dispatches' => [
                    [
                        'id' => $schedule->id,
                        'isRouteSuspended' => true,
                        'suspensionReason' => 'Flooding along Route 1',
                        'dispatchState' => 'route_suspended',
                        'canDispatch' => false,
                        'dispatchBlockedReason' => 'Flooding along Route 1',
                    ]
                ]
            ]);
    }

    /** @test */
    public function test3_suspended_route_counts_ongoing_trips_without_mutating_them()
    {
        $this->route->update(['status' => 'Suspended']);

        $trip1 = Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'ongoing',
        ]);

        $otherBus = Bus::create(['plate_number' => 'PAS-002', 'status' => Bus::STATUS_INACTIVE]);
        $otherDriver = Driver::factory()->create(['status' => 'active']);
        $trip2 = Trip::create([
            'bus_id' => $otherBus->id,
            'driver_id' => $otherDriver->id,
            'route_id' => $this->route->id,
            'status' => 'ongoing',
        ]);

        $schedule = Schedule::create([
            'route_id' => $this->route->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '10:00:00',
            'arrival_time' => '10:30:00',
            'service_date' => now('Asia/Manila')->toDateString(),
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/api/schedules/dispatch-queue/today');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'dispatches' => [
                    [
                        'id' => $schedule->id,
                        'remainingActiveTrips' => 2,
                    ]
                ]
            ]);

        $trip1->refresh();
        $trip2->refresh();
        $this->assertEquals('ongoing', $trip1->status);
        $this->assertEquals('ongoing', $trip2->status);
    }

    /** @test */
    public function test4_completed_and_cancelled_trips_are_excluded_from_counter()
    {
        $this->route->update(['status' => 'Suspended']);

        Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'completed',
        ]);

        Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'cancelled',
        ]);

        $schedule = Schedule::create([
            'route_id' => $this->route->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '10:00:00',
            'arrival_time' => '10:30:00',
            'service_date' => now('Asia/Manila')->toDateString(),
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/api/schedules/dispatch-queue/today');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'dispatches' => [
                    [
                        'id' => $schedule->id,
                        'remainingActiveTrips' => 0,
                    ]
                ]
            ]);
    }

    /** @test */
    public function test5_multiple_active_suspension_alerts_selects_latest_alert()
    {
        $this->route->update(['status' => 'Suspended']);

        ServiceAlert::create([
            'route_id' => $this->route->id,
            'title' => 'Older Alert Title',
            'message' => 'Older alert message.',
            'severity' => 'info',
            'status' => 'active',
            'affected_routes' => 'Route 1',
            'suspend_route' => true,
            'created_at' => now()->subMinutes(10),
        ]);

        ServiceAlert::create([
            'route_id' => $this->route->id,
            'title' => 'Latest Alert Title',
            'message' => 'Latest alert message.',
            'severity' => 'critical',
            'status' => 'active',
            'affected_routes' => 'Route 1',
            'suspend_route' => true,
            'created_at' => now(),
        ]);

        $schedule = Schedule::create([
            'route_id' => $this->route->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '10:00:00',
            'arrival_time' => '10:30:00',
            'service_date' => now('Asia/Manila')->toDateString(),
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/api/schedules/dispatch-queue/today');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'dispatches' => [
                    [
                        'id' => $schedule->id,
                        'suspensionReason' => 'Latest Alert Title',
                    ]
                ]
            ]);
    }

    /** @test */
    public function test6_suspended_route_without_active_alert_uses_fallback_reason()
    {
        $this->route->update(['status' => 'Suspended']);

        $schedule = Schedule::create([
            'route_id' => $this->route->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '10:00:00',
            'arrival_time' => '10:30:00',
            'service_date' => now('Asia/Manila')->toDateString(),
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/api/schedules/dispatch-queue/today');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'dispatches' => [
                    [
                        'id' => $schedule->id,
                        'isRouteSuspended' => true,
                        'suspensionReason' => 'Route is currently suspended.',
                        'dispatchState' => 'route_suspended',
                        'canDispatch' => false,
                    ]
                ]
            ]);
    }

    /** @test */
    public function test7_already_dispatched_schedule_retains_dispatched_state_precedence()
    {
        $this->route->update(['status' => 'Suspended']);

        $schedule = Schedule::create([
            'route_id' => $this->route->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '10:00:00',
            'arrival_time' => '10:30:00',
            'service_date' => now('Asia/Manila')->toDateString(),
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $trip = Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'schedule_id' => $schedule->id,
            'status' => 'dispatched',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/api/schedules/dispatch-queue/today');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'dispatches' => [
                    [
                        'id' => $schedule->id,
                        'tripId' => $trip->id,
                        'isDispatched' => true,
                        'dispatchState' => 'dispatched',
                        'canDispatch' => false,
                        'isRouteSuspended' => true,
                    ]
                ]
            ]);
    }

    /** @test */
    public function test8_cancelled_schedule_retains_cancelled_state_precedence()
    {
        $this->route->update(['status' => 'Suspended']);

        $schedule = Schedule::create([
            'route_id' => $this->route->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '10:00:00',
            'arrival_time' => '10:30:00',
            'service_date' => now('Asia/Manila')->toDateString(),
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/api/schedules/dispatch-queue/today');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'dispatches' => []
            ]);

        $reflection = new \ReflectionMethod(\App\Http\Controllers\Admin\ScheduleController::class, 'dispatchQueueState');
        $reflection->setAccessible(true);
        $state = $reflection->invoke(new \App\Http\Controllers\Admin\ScheduleController(), $schedule, 'Flooding along Route 1');

        $this->assertEquals('cancelled', $state['state']);
        $this->assertFalse($state['canDispatch']);
    }

    /** @test */
    public function test9_invalid_resource_precedence_retains_specific_reason()
    {
        $this->route->update(['status' => 'Suspended']);

        // Bus in maintenance status (invalid resource)
        $this->bus->update(['status' => Bus::STATUS_MAINTENANCE]);

        $schedule = Schedule::create([
            'route_id' => $this->route->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '10:00:00',
            'arrival_time' => '10:30:00',
            'service_date' => now('Asia/Manila')->toDateString(),
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/api/schedules/dispatch-queue/today');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'dispatches' => [
                    [
                        'id' => $schedule->id,
                        'dispatchState' => 'blocked',
                        'canDispatch' => false,
                        'isRouteSuspended' => true,
                    ]
                ]
            ]);
        
        $this->assertStringContainsString('Bus unavailable', $response->json('dispatches.0.dispatchBlockedReason'));
    }

    /** @test */
    public function test10_scheduled_dispatch_endpoint_remains_protected_against_suspended_route()
    {
        $this->route->update(['status' => 'Suspended']);

        $schedule = Schedule::create([
            'route_id' => $this->route->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '10:00:00',
            'arrival_time' => '10:30:00',
            'service_date' => now('Asia/Manila')->toDateString(),
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/admin/api/schedules/{$schedule->id}/dispatch");

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseMissing('trips', ['schedule_id' => $schedule->id]);
    }

    /** @test */
    public function test11_n_plus_1_query_efficiency_verification()
    {
        // Seed 10 schedules
        for ($i = 0; $i < 10; $i++) {
            Schedule::create([
                'route_id' => $this->route->id,
                'bus_id' => $this->bus->id,
                'driver_id' => $this->driver->id,
                'departure_time' => sprintf('%02d:00:00', ($i % 12) + 8),
                'arrival_time' => sprintf('%02d:30:00', ($i % 12) + 8),
                'service_date' => now('Asia/Manila')->toDateString(),
                'status' => Schedule::STATUS_ON_TIME,
            ]);
        }

        DB::enableQueryLog();

        $response = $this->actingAs($this->admin)->getJson('/admin/api/schedules/dispatch-queue/today');
        $response->assertStatus(200);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(50, $queryCount);
    }
}
