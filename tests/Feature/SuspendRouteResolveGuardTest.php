<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\ServiceAlert;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuspendRouteResolveGuardTest extends TestCase
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
    public function test1_resolving_suspended_route_alert_with_zero_ongoing_trips_proceeds_immediately()
    {
        $this->route->update(['status' => 'Suspended']);

        $alert = ServiceAlert::create([
            'route_id' => $this->route->id,
            'title' => 'Flooding along Route 1',
            'message' => 'Route 1 is closed.',
            'severity' => 'critical',
            'status' => 'active',
            'affected_routes' => 'Route 1',
            'suspend_route' => true,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/admin/api/alerts/{$alert->id}/resolve");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Alert successfully resolved!',
            ]);

        $alert->refresh();
        $this->route->refresh();

        $this->assertEquals('resolved', $alert->status);
        $this->assertEquals('Active', $this->route->status);
    }

    /** @test */
    public function test2_resolving_suspended_route_alert_with_ongoing_trips_requires_confirmation()
    {
        $this->route->update(['status' => 'Suspended']);

        $alert = ServiceAlert::create([
            'route_id' => $this->route->id,
            'title' => 'Flooding along Route 1',
            'message' => 'Route 1 is closed.',
            'severity' => 'critical',
            'status' => 'active',
            'affected_routes' => 'Route 1',
            'suspend_route' => true,
        ]);

        $trip1 = Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'ongoing',
        ]);

        $trip2 = Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'ongoing',
        ]);

        // First attempt without confirmation
        $response = $this->actingAs($this->admin)->postJson("/admin/api/alerts/{$alert->id}/resolve");

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'requiresConfirmation' => true,
                'remainingActiveTrips' => 2,
                'message' => 'This route still has 2 ongoing trips. Resolving the suspension will allow new dispatches. Continue?',
            ]);

        $alert->refresh();
        $this->route->refresh();

        // Alert remains active and route remains suspended
        $this->assertEquals('active', $alert->status);
        $this->assertEquals('Suspended', $this->route->status);
    }

    /** @test */
    public function test3_confirmed_resolve_request_succeeds_and_restores_route()
    {
        $this->route->update(['status' => 'Suspended']);

        $alert = ServiceAlert::create([
            'route_id' => $this->route->id,
            'title' => 'Flooding along Route 1',
            'message' => 'Route 1 is closed.',
            'severity' => 'critical',
            'status' => 'active',
            'affected_routes' => 'Route 1',
            'suspend_route' => true,
        ]);

        Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'ongoing',
        ]);

        // Second request with confirm = true
        $response = $this->actingAs($this->admin)->postJson("/admin/api/alerts/{$alert->id}/resolve", [
            'confirm' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Alert successfully resolved!',
            ]);

        $alert->refresh();
        $this->route->refresh();

        $this->assertEquals('resolved', $alert->status);
        $this->assertEquals('Active', $this->route->status);
    }

    /** @test */
    public function test4_resolving_one_alert_when_multiple_suspensions_exist_keeps_route_suspended()
    {
        $this->route->update(['status' => 'Suspended']);

        $alert1 = ServiceAlert::create([
            'route_id' => $this->route->id,
            'title' => 'Flooding along Route 1',
            'message' => 'Flood alert.',
            'severity' => 'critical',
            'status' => 'active',
            'affected_routes' => 'Route 1',
            'suspend_route' => true,
        ]);

        $alert2 = ServiceAlert::create([
            'route_id' => $this->route->id,
            'title' => 'Road Accident along Route 1',
            'message' => 'Accident alert.',
            'severity' => 'high',
            'status' => 'active',
            'affected_routes' => 'Route 1',
            'suspend_route' => true,
        ]);

        // Resolve alert 1 with confirm
        $response = $this->actingAs($this->admin)->postJson("/admin/api/alerts/{$alert1->id}/resolve", [
            'confirm' => true,
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $alert1->refresh();
        $alert2->refresh();
        $this->route->refresh();

        $this->assertEquals('resolved', $alert1->status);
        $this->assertEquals('active', $alert2->status);
        // Route remains suspended because alert2 is still active
        $this->assertEquals('Suspended', $this->route->status);
    }

    /** @test */
    public function test5_final_suspension_alert_resolved_restores_route_to_active()
    {
        $this->route->update(['status' => 'Suspended']);

        $alert1 = ServiceAlert::create([
            'route_id' => $this->route->id,
            'title' => 'Flooding along Route 1',
            'message' => 'Flood alert resolved.',
            'status' => 'resolved',
            'affected_routes' => 'Route 1',
            'suspend_route' => true,
        ]);

        $alert2 = ServiceAlert::create([
            'route_id' => $this->route->id,
            'title' => 'Road Accident along Route 1',
            'message' => 'Accident alert.',
            'status' => 'active',
            'affected_routes' => 'Route 1',
            'suspend_route' => true,
        ]);

        // Resolve final active alert
        $response = $this->actingAs($this->admin)->postJson("/admin/api/alerts/{$alert2->id}/resolve", [
            'confirm' => true,
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->route->refresh();
        $this->assertEquals('Active', $this->route->status);
    }

    /** @test */
    public function test6_ongoing_trips_remain_ongoing_and_unmutated_throughout_resolve_workflow()
    {
        $this->route->update(['status' => 'Suspended']);

        $alert = ServiceAlert::create([
            'route_id' => $this->route->id,
            'title' => 'Flooding along Route 1',
            'message' => 'Flood alert.',
            'status' => 'active',
            'affected_routes' => 'Route 1',
            'suspend_route' => true,
        ]);

        $trip = Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'ongoing',
        ]);

        // Attempt 1: Warning returned
        $this->actingAs($this->admin)->postJson("/admin/api/alerts/{$alert->id}/resolve");

        $trip->refresh();
        $this->assertEquals('ongoing', $trip->status);

        // Attempt 2: Confirmed resolve
        $this->actingAs($this->admin)->postJson("/admin/api/alerts/{$alert->id}/resolve", ['confirm' => true]);

        $trip->refresh();
        $this->assertEquals('ongoing', $trip->status);
    }
}
