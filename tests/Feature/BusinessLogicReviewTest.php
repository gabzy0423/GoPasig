<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Schedule;
use App\Models\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessLogicReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_gps_coordinate_boundary_validation(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'driver']);
        $bus = Bus::factory()->create([
            'plate_number' => 'XYZ-1234',
            'status' => 'active'
        ]);
        $driver = Driver::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Driver',
            'emp_id' => 'EMP-111',
            'license_number' => 'N11-11-111111',
            'license_expiry' => '2028-12-12',
            'status' => 'active',
            'assigned_bus' => 'XYZ-1234',
            'assigned_route' => null,
        ]);

        // Start ongoing trip so the driver controller can proceed
        \Illuminate\Support\Facades\DB::table('trips')->insert([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => 1,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 0.0 is technically numeric and passes basic Laravel validation, but live
        // processing must reject it synchronously before VehiclePosition is updated.
        $response = $this->actingAs($user)->postJson('/driver/trip/gps', [
            'lat' => 0.0,
            'lng' => 0.0,
            'speed' => 10
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'invalid');

        $log = \App\Models\GPSLog::where('lat', 0.0)->where('lng', 0.0)->first();
        $this->assertNotNull($log);
        $this->assertEquals('invalid', $log->processing_status);
        $this->assertDatabaseMissing('vehicle_positions', [
            'bus_id' => $bus->id,
            'trip_id' => $log->trip_id,
        ]);
    }

    public function test_gps_impossible_speed_jump(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'driver']);
        $route = Route::create([
            'name' => 'Test Route',
            'route_code' => 'TR-1',
            'status' => 'active'
        ]);
        $bus = Bus::factory()->create([
            'plate_number' => 'XYZ-1234',
            'status' => 'active',
            'route_id' => $route->id
        ]);
        $driver = Driver::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Driver',
            'emp_id' => 'EMP-111',
            'license_number' => 'N11-11-111111',
            'license_expiry' => '2028-12-12',
            'status' => 'active',
            'assigned_bus' => 'XYZ-1234',
            'assigned_route' => $route->id,
        ]);

        // Start ongoing trip so speed checking triggers
        $tripId = \Illuminate\Support\Facades\DB::table('trips')->insertGetId([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        // Log initial GPS position (Pasig area)
        \Illuminate\Support\Facades\DB::table('gps_logs')->insert([
            'trip_id' => $tripId,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 10,
            'timestamp' => now()->subMinutes(5),
            'processing_status' => 'processed',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        // Post a new GPS coordinate that is very far (but still within service bounds, e.g. north latitude limit)
        // distance from 14.5593 to 14.80 is ~26.7 km, done in 5 minutes (speed ~ 320 km/h, max is 80)
        $response = $this->actingAs($user)->postJson('/driver/trip/gps', [
            'lat' => 14.80,
            'lng' => 121.0805,
            'speed' => 10
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'invalid');

        $log = \App\Models\GPSLog::where('lat', 14.80)->where('lng', 121.0805)->first();
        $this->assertNotNull($log);
        $this->assertEquals('invalid', $log->processing_status);
    }

    public function test_block_bus_deletion_with_historical_records(): void
    {
        $this->seed();
        $admin = User::factory()->create(['role' => 'admin']);
        $bus = Bus::factory()->create([
            'plate_number' => 'XYZ-1234',
            'status' => 'inactive'
        ]);

        // Add a completed/historical trip for the bus
        Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => 1,
            'route_id' => 1,
            'status' => 'completed',
            'started_at' => now()->subDays(2),
            'ended_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($admin)->deleteJson("/admin/api/buses/{$bus->id}");

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'success' => false,
            'message' => 'Cannot delete bus with historical trip records. Deactivate the bus instead to preserve operational data.'
        ]);
    }

    public function test_legacy_schedule_records_do_not_block_bus_deletion(): void
    {
        $this->seed();
        $admin = User::factory()->create(['role' => 'admin']);
        $bus = Bus::factory()->create([
            'plate_number' => 'SCH-ONLY',
            'status' => 'inactive'
        ]);

        Schedule::factory()->create([
            'bus_id' => $bus->id,
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $response = $this->actingAs($admin)->deleteJson("/admin/api/buses/{$bus->id}");

        $response->assertOk();
        $response->assertJsonFragment([
            'success' => true,
            'message' => 'Bus successfully deleted!'
        ]);
        $this->assertDatabaseMissing('buses', ['id' => $bus->id]);
    }

    public function test_delete_brand_new_bus_succeeds(): void
    {
        $this->seed();
        $admin = User::factory()->create(['role' => 'admin']);
        $bus = Bus::factory()->create([
            'plate_number' => 'NEW-999',
            'status' => 'inactive'
        ]);

        $response = $this->actingAs($admin)->deleteJson("/admin/api/buses/{$bus->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'success' => true,
            'message' => 'Bus successfully deleted!'
        ]);
    }
}

