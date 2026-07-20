<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Stop;
use App\Models\Trip;
use App\Models\CommuterTrip;
use App\Models\CommuterSession;
use App\Models\MaintenanceRecord;
use App\Models\Incident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoPasigScenariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_bus_offline_auto_incidents(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $route = Route::create([
            'name' => 'Route A',
            'description' => 'Pasig City Hall to Megamall',
            'polyline_coordinates' => [[14.5593, 121.0805]],
            'status' => 'Active',
        ]);

        $bus = Bus::create([
            'plate_number' => 'PAS-555',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 10,
            'passengers' => 5,
            'route_id' => $route->id,
        ]);

        // Directly set updated_at in database to bypass Eloquent override
        \Illuminate\Support\Facades\DB::table('buses')
            ->where('id', $bus->id)
            ->update(['updated_at' => now()->subMinutes(3)]);

        $user = User::factory()->create(['role' => 'driver']);
        $driver = Driver::create([
            'user_id' => $user->id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'emp_id' => 'EMP-001',
            'license_number' => 'N99-99-000001',
            'license_expiry' => '2027-12-12',
            'assigned_bus' => 'PAS-555',
            'status' => 'active',
        ]);

        $trip = Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'started_at' => now()->subMinutes(10),
        ]);

        // Call the Fleet Overview API or hit dashboard, which triggers getOverviewDataArray
        $response = $this->actingAs($dispatcher)->getJson('/fleet/api/overview-data');
        $response->assertStatus(200);

        // Verify that a "signal lost" incident was auto-created
        $this->assertDatabaseHas('incidents', [
            'trip_id' => $trip->id,
            'type' => 'Delay',
            'status' => 'reported',
        ]);

        $incident = Incident::where('trip_id', $trip->id)->first();
        $this->assertStringContainsString('PAS-555 signal lost', $incident->description);
    }

    public function test_breakdown_and_maintenance_alerts_commuter(): void
    {
        $route = Route::create([
            'name' => 'Route A',
            'description' => 'Pasig Route',
            'polyline_coordinates' => [[14.5593, 121.0805]],
            'status' => 'Active',
        ]);

        $stop = Stop::create([
            'name' => 'City Hall Stop',
            'lat' => 14.5593,
            'lng' => 121.0805,
            'route_id' => $route->id,
            'sequence' => 1,
        ]);

        $bus = Bus::create([
            'plate_number' => 'PAS-999',
            'status' => 'breakdown', // Set to breakdown
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
            'route_id' => $route->id,
        ]);

        $session = CommuterSession::create([
            'session_token' => 'test-session-token',
            'expires_at' => now()->addHours(2),
        ]);

        // Scenario: Commuter ON_BUS on the broken down bus
        $commuterTrip = CommuterTrip::create([
            'session_token' => 'test-session-token',
            'route_id' => $route->id,
            'origin_stop_id' => $stop->id,
            'destination_stop_id' => $stop->id,
            'status' => 'ON_BUS',
            'bus_id' => $bus->id,
        ]);

        // Render GeofenceDetector or Tracker Livewire component to test alert computations
        $detector = new \App\Livewire\Commuter\GeofenceDetector();
        // Mock request context with commuter cookie
        request()->cookies->set('commuter_session_token', 'test-session-token');

        $viewData = $detector->render()->getData();
        $this->assertEquals("Breakdown detected — please alight safely. Rescue bus incoming.", $viewData['breakdownAlert']);

        // Test with status = maintenance
        $bus->update(['status' => 'maintenance']);
        $viewData = $detector->render()->getData();
        $this->assertEquals("Pasensya na — ang inyong bus ay may maintenance issue. Mangyaring bumaba sa susunod na hintuan.", $viewData['maintenanceAlert']);
    }

    public function test_dispatch_intelligence_nearest_inactive_bus(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $route = Route::create([
            'name' => 'Route A',
            'description' => 'Test Route',
            'polyline_coordinates' => [[14.5, 121.0]],
            'status' => 'Active',
        ]);

        $stop = Stop::create([
            'name' => 'Start Stop',
            'lat' => 14.5000,
            'lng' => 121.0000,
            'route_id' => $route->id,
            'sequence' => 1,
        ]);

        // Far inactive bus
        Bus::create([
            'plate_number' => 'PAS-FAR',
            'status' => 'available',
            'capacity' => 45,
            'lat' => 14.6000, // ~11 km away
            'lng' => 121.1000,
            'speed' => 0,
            'passengers' => 0,
        ]);

        // Close inactive bus
        Bus::create([
            'plate_number' => 'PAS-NEAR',
            'status' => 'available',
            'capacity' => 45,
            'lat' => 14.5010, // ~150 meters away
            'lng' => 121.0010,
            'speed' => 0,
            'passengers' => 0,
        ]);

        // Create 30 WAITING trips with valid sessions to exceed typical threshold of 20
        for ($i = 0; $i < 30; $i++) {
            $token = 'comm' . $i;
            CommuterSession::create([
                'session_token' => $token,
                'expires_at' => now()->addHour(),
            ]);

            CommuterTrip::create([
                'session_token' => $token,
                'route_id' => $route->id,
                'origin_stop_id' => $stop->id,
                'destination_stop_id' => $stop->id,
                'status' => 'WAITING',
            ]);
        }

        $controller = new \App\Http\Controllers\Fleet\DispatchIntelligenceController();
        $routesData = $controller->fetchRoutesData('Monday', '06:00-08:00', 1);

        $routePayload = $routesData->firstWhere('id', $route->id);
        $this->assertEquals('red', $routePayload->status);
        $this->assertNotNull($routePayload->suggested_bus);
        $this->assertEquals('PAS-NEAR', $routePayload->suggested_bus['plate_number']);
    }

    public function test_maintenance_lock_and_expected_duration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $bus = Bus::create([
            'plate_number' => 'PAS-777',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5,
            'lng' => 121.0,
            'speed' => 0,
            'passengers' => 0,
        ]);

        // Post a maintenance record with custom expected duration
        $response = $this->actingAs($admin)->postJson('/admin/api/maintenance', [
            'bus_id' => $bus->id,
            'type' => 'Corrective',
            'description' => 'Engine overhaul',
            'scheduled_at' => '2026-06-20T08:00',
            'status' => 'scheduled',
            'expected_duration_minutes' => 360, // 6 hours
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('maintenance_records', [
            'bus_id' => $bus->id,
            'expected_duration_minutes' => 360,
        ]);

        $bus->refresh();
        // Bus status should be set to maintenance
        $this->assertEquals('maintenance', $bus->status);

        // Fetch bus profile via dispatcher API to verify completion time
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);
        $profileResponse = $this->actingAs($dispatcher)->getJson("/fleet/api/maintenance-bus/{$bus->plate_number}");
        $profileResponse->assertStatus(200);
        $data = $profileResponse->json();
        $this->assertNotNull($data['bus']['completion_time']);
    }
}
