<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Route;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminScheduleConflictTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    public function test_admin_can_access_schedule_create_page(): void
    {
        $this->actingAsAdmin();

        // Seed a dummy route
        Route::create([
            'id' => 1,
            'name' => 'SPED to Temp Pasig City Hall',
            'description' => 'SPED to Temp Pasig City Hall (P2P)',
            'travel_time_minutes' => 25,
            'status' => 'active'
        ]);

        $response = $this->get('/admin/schedules/create');

        $response->assertRedirect('/admin/dashboard#schedules-create');
    }

    public function test_unauthorized_users_cannot_access_schedule_create_page(): void
    {
        $response = $this->get('/admin/schedules/create');
        $response->assertRedirect('/login');

        $driver = User::factory()->create(['role' => 'driver']);
        $response = $this->actingAs($driver)->get('/admin/schedules/create');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_schedule_conflict_page(): void
    {
        $this->actingAsAdmin();

        $response = $this->get('/admin/schedules/conflict');

        $response->assertRedirect('/admin/dashboard#schedules-conflict');
    }

    public function test_unauthorized_users_cannot_access_schedule_conflict_page(): void
    {
        $response = $this->get('/admin/schedules/conflict');
        $response->assertRedirect('/login');

        $driver = User::factory()->create(['role' => 'driver']);
        $response = $this->actingAs($driver)->get('/admin/schedules/conflict');
        $response->assertStatus(403);
    }

    public function test_admin_cannot_create_conflicting_schedule(): void
    {
        $this->actingAsAdmin();

        $route = Route::create([
            'id' => 1,
            'name' => 'Route A',
            'travel_time_minutes' => 30,
            'status' => 'active'
        ]);

        $bus = Bus::create([
            'plate_number' => 'PAS-123',
            'status' => 'inactive'
        ]);

        $driver = Driver::create([
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'emp_id' => 'EMP-001',
            'license_number' => 'LIC-001',
            'license_expiry' => now()->addYear(),
            'status' => 'inactive'
        ]);

        $driver2 = Driver::create([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'emp_id' => 'EMP-003',
            'license_number' => 'LIC-003',
            'license_expiry' => now()->addYear(),
            'status' => 'inactive'
        ]);

        // Create initial schedule
        Schedule::create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'departure_time' => '08:00',
            'arrival_time' => '08:30',
            'status' => 'On time'
        ]);

        // Attempt to create overlapping schedule with same bus but different driver
        $response = $this->postJson('/admin/api/schedules', [
            'route_id' => $route->id,
            'bus_plate' => 'PAS-123',
            'driver_initials' => 'MS',
            'departure_time' => '08:15'
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Bus PAS-123 already assigned to Route Route A at 08:00-08:30'
        ]);
    }

    public function test_admin_cannot_update_conflicting_schedule(): void
    {
        $this->actingAsAdmin();

        $route = Route::create([
            'id' => 1,
            'name' => 'Route A',
            'travel_time_minutes' => 30,
            'status' => 'active'
        ]);

        $bus1 = Bus::create([
            'plate_number' => 'PAS-111',
            'status' => 'inactive'
        ]);

        $bus2 = Bus::create([
            'plate_number' => 'PAS-222',
            'status' => 'inactive'
        ]);

        $driver = Driver::create([
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'emp_id' => 'EMP-002',
            'license_number' => 'LIC-002',
            'license_expiry' => now()->addYear(),
            'status' => 'inactive'
        ]);

        // Create schedule 1
        $schedule1 = Schedule::create([
            'route_id' => $route->id,
            'bus_id' => $bus1->id,
            'driver_id' => $driver->id,
            'departure_time' => '08:00',
            'arrival_time' => '08:30',
            'status' => 'On time'
        ]);

        // Create schedule 2
        $schedule2 = Schedule::create([
            'route_id' => $route->id,
            'bus_id' => $bus2->id,
            'driver_id' => $driver->id,
            'departure_time' => '09:00',
            'arrival_time' => '09:30',
            'status' => 'On time'
        ]);

        // Attempt to update schedule 2 to overlap with schedule 1 using the same driver (which has a 15-minute buffer)
        // Schedule 1 ends at 08:30. Driver buffer is 15 mins, so driver is busy until 08:45.
        // If we set schedule 2 to 08:40, it should conflict with schedule 1 for the driver.
        $response = $this->putJson("/admin/api/schedules/{$schedule2->id}", [
            'route_id' => $route->id,
            'bus_plate' => 'PAS-222',
            'driver_initials' => 'JC',
            'departure_time' => '08:40'
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Driver Juan Cruz already assigned to Route Route A at 08:00-08:30'
        ]);
    }
}
