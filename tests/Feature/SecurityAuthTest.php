<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Driver;
use App\Models\Schedule;
use App\Models\Stop;
use App\Models\ServiceAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecurityAuthTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $nonAdminUser;
    protected $testBus;
    protected $testRoute;
    protected $testDriver;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->nonAdminUser = User::factory()->create(['role' => 'driver']);

        // Create test data with correct status
        $this->testRoute = Route::factory()->create(['status' => 'active']);
        $this->testBus = Bus::factory()->create(['status' => 'active']);
        $this->testDriver = Driver::factory()->create(['status' => 'active', 'license_expiry' => now()->addYear()]);
    }

    // ============================================================
    // BusController Tests
    // ============================================================

    /** @test */
    public function test_non_admin_cannot_create_bus()
    {
        $response = $this->actingAs($this->nonAdminUser)->postJson('/admin/api/buses', [
            'plate_number' => 'TEST-001',
            'fleet_number' => 'BUS-111',
            'vin' => '1234567890ABCDEF1',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2024,
            'battery_capacity_kwh' => 350.00,
            'charging_port_type' => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'capacity' => 50,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can create buses',
        ]);
    }

    /** @test */
    public function test_admin_can_create_bus()
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/api/buses', [
            'plate_number' => 'TEST-002',
            'fleet_number' => 'BUS-222',
            'vin' => '1234567890ABCDEF2',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2024,
            'battery_capacity_kwh' => 350.00,
            'charging_port_type' => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'capacity' => 50,
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('buses', ['plate_number' => 'TEST-002']);
    }

    /** @test */
    public function test_non_admin_cannot_update_bus()
    {
        $bus = Bus::factory()->create();

        $response = $this->actingAs($this->nonAdminUser)->putJson("/admin/api/buses/{$bus->id}", [
            'fleet_number' => 'BUS-333',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2024,
            'battery_capacity_kwh' => 350.00,
            'charging_port_type' => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'capacity' => 60,
            'status' => 'inactive',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can update buses',
        ]);
    }

    /** @test */
    public function test_admin_can_update_bus()
    {
        $bus = Bus::factory()->create();

        $response = $this->actingAs($this->adminUser)->putJson("/admin/api/buses/{$bus->id}", [
            'fleet_number' => 'BUS-444',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2024,
            'battery_capacity_kwh' => 350.00,
            'charging_port_type' => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'capacity' => 60,
            'status' => 'inactive',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('buses', ['id' => $bus->id, 'fleet_number' => 'BUS-444']);
    }

    /** @test */
    public function test_non_admin_cannot_delete_bus()
    {
        $bus = Bus::factory()->create();

        $response = $this->actingAs($this->nonAdminUser)->deleteJson("/admin/api/buses/{$bus->id}");

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can delete buses',
        ]);

        $this->assertDatabaseHas('buses', ['id' => $bus->id]);
    }

    /** @test */
    public function test_admin_can_delete_bus()
    {
        $bus = Bus::factory()->create();

        $response = $this->actingAs($this->adminUser)->deleteJson("/admin/api/buses/{$bus->id}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('buses', ['id' => $bus->id]);
    }

    /** @test */
    public function test_non_admin_cannot_assign_route()
    {
        $bus = Bus::factory()->create();
        $route = Route::factory()->create();
 
        $response = $this->actingAs($this->nonAdminUser)->putJson("/admin/api/buses/{$bus->id}/assign-route", [
            'route_id' => $route->id,
        ]);
 
        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can assign routes',
        ]);
    }

    // ============================================================
    // ScheduleController Tests
    // ============================================================

    /** @test */
    public function test_non_admin_cannot_create_schedule()
    {
        $response = $this->actingAs($this->nonAdminUser)->postJson('/admin/api/schedules', [
            'route_id' => $this->testRoute->id,
            'bus_plate' => $this->testBus->plate_number,
            'driver_id' => $this->testDriver->id,
            'departure_time' => '08:00',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can create schedules',
        ]);
    }

    /** @test */
    public function test_admin_can_create_schedule()
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/api/schedules', [
            'route_id' => $this->testRoute->id,
            'bus_plate' => $this->testBus->plate_number,
            'driver_id' => $this->testDriver->id,
            'departure_time' => '08:00',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function test_non_admin_cannot_update_schedule()
    {
        $schedule = Schedule::factory()->create();

        $response = $this->actingAs($this->nonAdminUser)->putJson("/admin/api/schedules/{$schedule->id}", [
            'route_id' => $this->testRoute->id,
            'bus_plate' => $this->testBus->plate_number,
            'driver_id' => $this->testDriver->id,
            'departure_time' => '09:00',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can update schedules',
        ]);
    }

    /** @test */
    public function test_non_admin_cannot_update_schedule_status()
    {
        $schedule = Schedule::factory()->create();

        $response = $this->actingAs($this->nonAdminUser)->patchJson("/admin/api/schedules/{$schedule->id}/status", [
            'status' => 'delayed',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can update schedule status',
        ]);
    }

    /** @test */
    public function test_non_admin_cannot_delete_schedule()
    {
        $schedule = Schedule::factory()->create();

        $response = $this->actingAs($this->nonAdminUser)->deleteJson("/admin/api/schedules/{$schedule->id}");

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can delete schedules',
        ]);
    }

    // ============================================================
    // RouteController Tests
    // ============================================================

    /** @test */
    public function test_non_admin_cannot_create_route()
    {
        $response = $this->actingAs($this->nonAdminUser)->postJson('/admin/api/routes', [
            'name' => 'Route Security Test',
            'status' => 'Active',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can create routes',
        ]);
    }

    /** @test */
    public function test_admin_can_create_route()
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/api/routes', [
            'name' => 'Route Security Test',
            'status' => 'Active',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function test_non_admin_cannot_update_route()
    {
        $route = Route::factory()->create();

        $response = $this->actingAs($this->nonAdminUser)->putJson("/admin/api/routes/{$route->id}", [
            'name' => 'Updated Route',
            'status' => 'Active',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can update routes',
        ]);
    }

    /** @test */
    public function test_non_admin_cannot_delete_route()
    {
        $route = Route::factory()->create();

        $response = $this->actingAs($this->nonAdminUser)->deleteJson("/admin/api/routes/{$route->id}");

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can delete routes',
        ]);
    }

    // ============================================================
    // StopController Tests
    // ============================================================

    /** @test */
    public function test_non_admin_cannot_create_stop()
    {
        $response = $this->actingAs($this->nonAdminUser)->postJson('/admin/api/stops', [
            'route_id' => $this->testRoute->id,
            'name' => 'Stop Security Test',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can create stops',
        ]);
    }

    /** @test */
    public function test_admin_can_create_stop()
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/api/stops', [
            'route_id' => $this->testRoute->id,
            'name' => 'Stop Security Test',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function test_non_admin_cannot_reorder_stops()
    {
        $stop1 = Stop::create(['route_id' => $this->testRoute->id, 'name' => 'Stop 1', 'lat' => 14.5593, 'lng' => 121.0805, 'sequence' => 1]);
        $stop2 = Stop::create(['route_id' => $this->testRoute->id, 'name' => 'Stop 2', 'lat' => 14.5620, 'lng' => 121.0820, 'sequence' => 2]);

        $response = $this->actingAs($this->nonAdminUser)->putJson("/admin/api/routes/{$this->testRoute->id}/stops/reorder", [
            'stop_ids' => [$stop2->id, $stop1->id],
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can reorder stops',
        ]);
    }

    /** @test */
    public function test_non_admin_cannot_delete_stop()
    {
        $stop = Stop::create(['route_id' => $this->testRoute->id, 'name' => 'Stop Test', 'lat' => 14.5593, 'lng' => 121.0805, 'sequence' => 1]);

        $response = $this->actingAs($this->nonAdminUser)->deleteJson("/admin/api/stops/{$stop->id}");

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can delete stops',
        ]);
    }

    // ============================================================
    // ServiceAlertController Tests
    // ============================================================

    /** @test */
    public function test_non_admin_cannot_create_alert()
    {
        $response = $this->actingAs($this->nonAdminUser)->postJson('/admin/api/alerts', [
            'title' => 'Security Test Alert',
            'message' => 'This is a security test',
            'severity' => 'Low',
            'type' => 'delay',
            'affects' => ['Route 1'],
            'timing' => 'now',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can create alerts',
        ]);
    }

    /** @test */
    public function test_admin_can_create_alert()
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/api/alerts', [
            'title' => 'Security Test Alert',
            'message' => 'This is a security test',
            'severity' => 'Low',
            'type' => 'delay',
            'affects' => [$this->testRoute->name],
            'timing' => 'now',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function test_non_admin_cannot_update_alert()
    {
        $alert = ServiceAlert::factory()->create();

        $response = $this->actingAs($this->nonAdminUser)->putJson("/admin/api/alerts/{$alert->id}", [
            'title' => 'Updated Alert',
            'message' => 'Updated message',
            'severity' => 'High',
            'type' => 'delay',
            'affects' => ['Route 1'],
            'timing' => 'now',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can update alerts',
        ]);
    }

    /** @test */
    public function test_non_admin_cannot_resolve_alert()
    {
        $alert = ServiceAlert::factory()->create();

        $response = $this->actingAs($this->nonAdminUser)->postJson("/admin/api/alerts/{$alert->id}/resolve");

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can resolve alerts',
        ]);
    }

    /** @test */
    public function test_non_admin_cannot_resolve_all_alerts()
    {
        $response = $this->actingAs($this->nonAdminUser)->postJson('/admin/api/alerts/resolve-all');

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can resolve all alerts',
        ]);
    }

    /** @test */
    public function test_non_admin_cannot_delete_alert()
    {
        $alert = ServiceAlert::factory()->create();

        $response = $this->actingAs($this->nonAdminUser)->deleteJson("/admin/api/alerts/{$alert->id}");

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Only admins can delete alerts',
        ]);
    }

    // ============================================================
    // Unauthenticated User Tests
    // ============================================================

    /** @test */
    public function test_unauthenticated_user_cannot_create_bus()
    {
        $response = $this->postJson('/admin/api/buses', [
            'plate_number' => 'TEST-001',
            'capacity' => 50,
            'status' => 'active',
        ]);

        // Should get redirected or 401 Unauthorized
        $this->assertTrue($response->status() === 401 || $response->status() === 302);
    }
}
