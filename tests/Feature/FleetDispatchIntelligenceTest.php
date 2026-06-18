<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Route;
use App\Models\Stop;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\CommuterTrip;
use App\Models\DemandThreshold;
use App\Models\DemandHistory;
use App\Models\DispatchLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Carbon\Carbon;

class FleetDispatchIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private $dispatcher;
    private $route;
    private $stop1;
    private $stop2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = User::factory()->create(['role' => 'dispatcher']);
        
        $this->route = Route::create([
            'id' => 1,
            'name' => 'Route A',
            'description' => 'SPED to Pasig City Hall',
            'polyline_coordinates' => [[14.5593, 121.0805], [14.5838, 121.0620]],
            'status' => 'Active',
        ]);

        $this->stop1 = Stop::create([
            'route_id' => $this->route->id,
            'name' => 'SPED Terminal',
            'lat' => 14.5593,
            'lng' => 121.0805,
            'sequence' => 1,
        ]);

        $this->stop2 = Stop::create([
            'route_id' => $this->route->id,
            'name' => 'Pasig City Hall',
            'lat' => 14.5838,
            'lng' => 121.0620,
            'sequence' => 2,
        ]);
    }

    public function test_dispatcher_can_access_dispatch_intelligence(): void
    {
        $response = $this->actingAs($this->dispatcher)->get('/fleet/dispatch-intelligence');
        $response->assertRedirect('/fleet/dashboard?tab=dispatch-intelligence');
        
        $dashboardResponse = $this->actingAs($this->dispatcher)->get('/fleet/dashboard?tab=dispatch-intelligence');
        $dashboardResponse->assertStatus(200);
    }

    public function test_unauthorized_users_cannot_access_dispatch_intelligence(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get('/fleet/dispatch-intelligence');
        $response->assertStatus(403);

        $driver = User::factory()->create(['role' => 'driver']);
        $response = $this->actingAs($driver)->get('/fleet/dispatch-intelligence');
        $response->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/fleet/dispatch-intelligence');
        $response->assertRedirect('/login');
    }

    public function test_api_data_loads_successfully(): void
    {
        $response = $this->actingAs($this->dispatcher)->get('/fleet/api/dispatch-data');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'routesData',
            'activeAlerts',
            'customThreshold',
            'recentDispatches',
            'historicalPatterns'
        ]);
    }

    public function test_can_update_threshold(): void
    {
        $response = $this->actingAs($this->dispatcher)->post('/fleet/api/dispatch-save-threshold', [
            'route_id' => $this->route->id,
            'day' => Carbon::now()->englishDayOfWeek,
            'time_slot' => '08:00-10:00',
            'threshold' => 25,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('demand_thresholds', [
            'route_id' => $this->route->id,
            'threshold_count' => 25,
        ]);
    }

    public function test_can_simulate_commuter_activity(): void
    {
        $response = $this->actingAs($this->dispatcher)->post('/fleet/api/dispatch-add-commuter', [
            'route_id' => $this->route->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('commuter_trips', [
            'route_id' => $this->route->id,
            'status' => 'WAITING',
        ]);
    }

    public function test_can_dispatch_bus_and_reset_queue(): void
    {
        // Seed an inactive bus and driver
        $bus = Bus::create([
            'plate_number' => 'PAS-555',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-5555',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456789',
            'license_expiry' => '2027-12-12',
            'status' => 'inactive',
        ]);

        // Seed commuter checking in
        $token = 'test-token-123';
        \App\Models\CommuterSession::create([
            'session_token' => $token,
            'expires_at' => now()->addHours(24),
        ]);

        CommuterTrip::create([
            'session_token' => $token,
            'route_id' => $this->route->id,
            'origin_stop_id' => $this->stop1->id,
            'destination_stop_id' => $this->stop2->id,
            'status' => 'WAITING',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->dispatcher)->post('/fleet/api/dispatch-now', [
            'route_id' => $this->route->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Assert bus and driver are now active
        $bus->refresh();
        $this->assertEquals('active', $bus->status);

        $driver->refresh();
        $this->assertEquals('active', $driver->status);

        // Assert pending commuter check-in is now boarded (ON_BUS)
        $this->assertDatabaseHas('commuter_trips', [
            'route_id' => $this->route->id,
            'status' => 'ON_BUS',
        ]);

        // Assert Dispatch Log exists
        $this->assertDatabaseCount('dispatch_logs', 1);
    }

    public function test_dispatch_fails_when_no_inactive_bus_or_driver_available(): void
    {
        $response = $this->actingAs($this->dispatcher)->post('/fleet/api/dispatch-now', [
            'route_id' => $this->route->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);
    }
}
