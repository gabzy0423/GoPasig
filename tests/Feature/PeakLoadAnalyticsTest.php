<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Driver;
use App\Models\Bus;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Carbon\Carbon;

class PeakLoadAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_peak_load_lifecycle(): void
    {
        // 1. Setup route, bus, driver, and user
        $route = Route::create([
            'name' => 'Route 2',
            'description' => 'Test Route 2',
            'polyline_coordinates' => [[14.5, 121.0]],
            'status' => 'Active',
        ]);

        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'outbound',
            'origin_name' => 'SPED',
            'destination_name' => 'Ligaya',
            'polyline_coordinates' => [[14.5, 121.0], [14.51, 121.01]],
            'geometry_version' => 1,
        ]);

        $bus = Bus::create([
            'plate_number' => 'PAS-999',
            'status' => 'ready',
            'capacity' => 60,
            'lat' => 14.5,
            'lng' => 121.0,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $user = User::factory()->create(['role' => 'driver', 'name' => 'Driver One']);
        $driver = Driver::create([
            'user_id' => $user->id,
            'emp_id' => 'EMP-9999',
            'first_name' => 'Driver',
            'last_name' => 'One',
            'license_number' => 'N01-99-999999',
            'license_expiry' => '2028-12-12',
            'status' => 'inactive',
            'assigned_bus' => 'PAS-999',
            'assigned_route' => (string) $route->id,
            'performance_score' => 90,
        ]);

        Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'status' => 'dispatched',
            'gps_session' => 'OFF',
            'dispatched_at' => now(),
            'peak_passengers' => 0,
        ]);

        // 2. Start trip session (toggleTrip to active)
        $response = $this->actingAs($user)->postJson('/driver/trip/toggle', ['status' => 'active']);
        $response->assertStatus(200);

        // Verify ongoing trip was created in database
        $this->assertDatabaseHas('trips', [
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'status' => 'ongoing',
            'peak_passengers' => 0,
        ]);

        // 3. Update passenger load (increment to 20)
        $response = $this->actingAs($user)->postJson('/driver/trip/pax', ['change' => 20]);
        $response->assertStatus(200);

        // Verify peak load in database is 20
        $this->assertDatabaseHas('trips', [
            'driver_id' => $driver->id,
            'status' => 'ongoing',
            'peak_passengers' => 20,
        ]);

        // 4. Update passenger load (decrement to 10)
        $response = $this->actingAs($user)->postJson('/driver/trip/pax', ['change' => -10]);
        $response->assertStatus(200);

        // Verify peak load in database remains 20 (since peak is max)
        $this->assertDatabaseHas('trips', [
            'driver_id' => $driver->id,
            'status' => 'ongoing',
            'peak_passengers' => 20,
        ]);

        // 5. Update passenger load (increment to 55 - overloading)
        $response = $this->actingAs($user)->postJson('/driver/trip/pax', ['change' => 45]);
        $response->assertStatus(200);

        // Verify peak load in database is updated to 55
        $this->assertDatabaseHas('trips', [
            'driver_id' => $driver->id,
            'status' => 'ongoing',
            'peak_passengers' => 55,
        ]);

        // 6. Complete trip session (toggleTrip to inactive)
        $response = $this->actingAs($user)->postJson('/driver/trip/toggle', ['status' => 'inactive']);
        $response->assertStatus(200);

        $this->assertDatabaseHas('trips', [
            'driver_id' => $driver->id,
            'status' => 'completed',
            'peak_passengers' => 55,
        ]);

        // 7. Access admin analytics API and check driver performance table peakLoad
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->getJson('/admin/api/analytics');
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertTrue($data['success']);
        
        $driverPerf = collect($data['driverPerformance'])->firstWhere('name', 'Driver One');
        $this->assertNotNull($driverPerf);
        
        // Assert the peak load is exactly 55 (does not get capped at 45 by any formula!)
        $this->assertEquals(55, $driverPerf['peakLoad']);
    }
}
