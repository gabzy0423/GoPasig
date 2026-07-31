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

        $this->dispatcher = User::factory()->create(['role' => 'fleet_manager']);
        
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
        // Seed an available bus and active/available driver
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
            'status' => 'active',
            'operational_status' => 'available',
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

        // Assert bus and driver are now dispatched/assigned
        $bus->refresh();
        $this->assertEquals('ready', $bus->status);

        $driver->refresh();
        $this->assertEquals('active', $driver->status);
        $this->assertEquals('assigned', $driver->operational_status);

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

    public function test_dispatch_creates_single_trip_and_single_dispatch_log(): void
    {
        $bus = Bus::create([
            'plate_number' => 'PAS-X1',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-X1',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456781',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        $response = $this->actingAs($this->dispatcher)->post('/fleet/api/dispatch-now', [
            'route_id' => $this->route->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('trips', 1);
        $this->assertDatabaseCount('dispatch_logs', 1);
    }

    public function test_duplicate_dispatch_rejected(): void
    {
        $bus = Bus::create([
            'plate_number' => 'PAS-X2',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-X2',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456782',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        // First dispatch
        $response1 = $this->actingAs($this->dispatcher)->post('/fleet/api/dispatch-now', [
            'route_id' => $this->route->id,
        ]);
        $response1->assertStatus(200);

        // Replay dispatch immediately with same resource
        try {
            \App\Services\SimulationDispatchService::dispatch($bus, $driver, $this->route);
            $this->fail("Duplicate dispatch should throw DispatchException.");
        } catch (\App\Exceptions\DispatchException $e) {
            $this->assertTrue(
                str_contains($e->getMessage(), 'Active dispatched/ongoing trip exists') ||
                str_contains($e->getMessage(), 'not available for Central Dispatch')
            );
        }
    }

    public function test_maintenance_bus_rejected(): void
    {
        $bus = Bus::create([
            'plate_number' => 'PAS-MNT',
            'status' => 'maintenance',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-MNT',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456783',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        $this->expectException(\App\Exceptions\BusUnavailableException::class);
        $this->expectExceptionMessage('Maintenance.');

        \App\Services\SimulationDispatchService::dispatch($bus, $driver, $this->route);
    }

    public function test_breakdown_bus_rejected(): void
    {
        $bus = Bus::create([
            'plate_number' => 'PAS-BRK',
            'status' => 'breakdown',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-BRK',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456784',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        $this->expectException(\App\Exceptions\BusUnavailableException::class);
        $this->expectExceptionMessage('Breakdown.');

        \App\Services\SimulationDispatchService::dispatch($bus, $driver, $this->route);
    }

    public function test_unavailable_driver_rejected(): void
    {
        $bus = Bus::create([
            'plate_number' => 'PAS-OK',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-SUSP',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456785',
            'license_expiry' => '2027-12-12',
            'status' => 'suspended',
        ]);

        $this->expectException(\App\Exceptions\DriverUnavailableException::class);
        $this->expectExceptionMessage('Suspended.');

        \App\Services\SimulationDispatchService::dispatch($bus, $driver, $this->route);
    }

    public function test_expired_license_rejected(): void
    {
        $bus = Bus::create([
            'plate_number' => 'PAS-OK2',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-EXP',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456786',
            'license_expiry' => '2020-12-12', // expired
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        $this->expectException(\App\Exceptions\DriverUnavailableException::class);
        $this->expectExceptionMessage('License expired.');

        \App\Services\SimulationDispatchService::dispatch($bus, $driver, $this->route);
    }

    public function test_schedule_conflict_rejected(): void
    {
        $bus = Bus::create([
            'plate_number' => 'PAS-CON',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-CON',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456787',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        // Seed conflicting schedule for this bus
        \App\Models\Schedule::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $this->route->id,
            'departure_time' => now()->format('H:i:s'),
            'arrival_time' => now()->addMinutes(60)->format('H:i:s'),
            'service_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);

        $this->expectException(\App\Exceptions\ScheduleConflictException::class);
        $this->expectExceptionMessage('already scheduled');

        \App\Services\SimulationDispatchService::dispatch($bus, $driver, $this->route);
    }

    public function test_transaction_rollback_restores_all_state_and_no_orphan_records(): void
    {
        $bus = Bus::create([
            'plate_number' => 'PAS-ROLL',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-ROLL',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456788',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        // Mock Log facade to throw exception inside transition to force rollback midway
        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->andThrow(new \RuntimeException('Forced rollback'));

        try {
            \App\Services\SimulationDispatchService::dispatch($bus, $driver, $this->route);
            $this->fail("Rollback should have thrown exception.");
        } catch (\RuntimeException $e) {
            $this->assertSame('Forced rollback', $e->getMessage());
        }

        // Verify: no partial updates remain (bus is still standby, no trips created, no dispatch logs)
        $bus->refresh();
        $this->assertSame('inactive', $bus->status);
        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount('dispatch_logs', 0);
    }
}

