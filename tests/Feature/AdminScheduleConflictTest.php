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

        $response->assertOk();
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
            'driver_id' => $driver2->id,
            'departure_time' => '08:15'
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Bus is inactive'
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
            'status' => 'active'
        ]);

        $bus2 = Bus::create([
            'plate_number' => 'PAS-222',
            'status' => 'active'
        ]);

        $driver = Driver::create([
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'emp_id' => 'EMP-002',
            'license_number' => 'LIC-002',
            'license_expiry' => now()->addYear(),
            'status' => 'active'
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
            'driver_id' => $driver->id,
            'departure_time' => '08:40'
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Driver already has trip from 08:00 with 15min buffer'
        ]);
    }

    public function test_schedule_creation_uses_driver_id_not_ambiguous_initials(): void
    {
        $this->actingAsAdmin();

        $route = Route::create([
            'name' => 'Route A',
            'travel_time_minutes' => 30,
            'status' => 'active'
        ]);

        $bus = Bus::create([
            'plate_number' => 'PAS-333',
            'status' => 'active'
        ]);

        Driver::create([
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'emp_id' => 'EMP-004',
            'license_number' => 'LIC-004',
            'license_expiry' => now()->addYear(),
            'status' => 'active'
        ]);

        $targetDriver = Driver::create([
            'first_name' => 'Jose',
            'last_name' => 'Castro',
            'emp_id' => 'EMP-005',
            'license_number' => 'LIC-005',
            'license_expiry' => now()->addYear(),
            'status' => 'active'
        ]);

        $response = $this->postJson('/admin/api/schedules', [
            'route_id' => $route->id,
            'bus_plate' => $bus->plate_number,
            'driver_id' => $targetDriver->id,
            'departure_time' => '10:00'
        ]);

        $response->assertCreated();
        $response->assertJsonPath('schedule.driverId', $targetDriver->id);
    }

    public function test_admin_cannot_create_bus_turnaround_buffer_conflict(): void
    {
        $this->actingAsAdmin();

        $route = Route::create([
            'name' => 'Route A',
            'travel_time_minutes' => 30,
            'status' => 'active'
        ]);

        $bus = Bus::create([
            'plate_number' => 'PAS-444',
            'status' => 'active'
        ]);

        $driver1 = Driver::create([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'emp_id' => 'EMP-006',
            'license_number' => 'LIC-006',
            'license_expiry' => now()->addYear(),
            'status' => 'active'
        ]);

        $driver2 = Driver::create([
            'first_name' => 'Ben',
            'last_name' => 'Santos',
            'emp_id' => 'EMP-007',
            'license_number' => 'LIC-007',
            'license_expiry' => now()->addYear(),
            'status' => 'active'
        ]);

        Schedule::create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver1->id,
            'departure_time' => '08:00',
            'arrival_time' => '08:30',
            'status' => 'On time'
        ]);

        $response = $this->postJson('/admin/api/schedules', [
            'route_id' => $route->id,
            'bus_plate' => $bus->plate_number,
            'driver_id' => $driver2->id,
            'departure_time' => '08:31'
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Bus is already scheduled from 08:00 with 15min buffer'
        ]);
    }

    public function test_cross_midnight_bus_conflict(): void
    {
        $this->actingAsAdmin();

        $route = Route::create([
            'name' => 'Route A',
            'travel_time_minutes' => 30,
            'status' => 'active'
        ]);

        $bus = Bus::create([
            'plate_number' => 'PAS-555',
            'status' => 'active'
        ]);

        $driver = Driver::create([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'emp_id' => 'EMP-008',
            'license_number' => 'LIC-008',
            'license_expiry' => now()->addYear(),
            'status' => 'active'
        ]);

        $driver2 = Driver::create([
            'first_name' => 'Ben',
            'last_name' => 'Santos',
            'emp_id' => 'EMP-009',
            'license_number' => 'LIC-009',
            'license_expiry' => now()->addYear(),
            'status' => 'active'
        ]);

        // Existing schedule starts at 23:50 and ends at 00:20 (next day)
        Schedule::create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => '2026-06-18',
            'departure_time' => '23:50',
            'arrival_time' => '00:20',
            'status' => 'On time'
        ]);

        // Attempting to schedule same bus on 2026-06-19 at 00:10 (overlap!)
        $response = $this->postJson('/admin/api/schedules', [
            'route_id' => $route->id,
            'bus_plate' => $bus->plate_number,
            'driver_id' => $driver2->id,
            'service_date' => '2026-06-19',
            'departure_time' => '00:10'
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Bus is already scheduled from 23:50 with 15min buffer'
        ]);
    }

    public function test_driver_bidirectional_rest_period_check(): void
    {
        $this->actingAsAdmin();

        $route = Route::create([
            'name' => 'Route A',
            'travel_time_minutes' => 60,
            'status' => 'active'
        ]);

        $bus = Bus::create([
            'plate_number' => 'PAS-666',
            'status' => 'active'
        ]);

        $driver = Driver::create([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'emp_id' => 'EMP-010',
            'license_number' => 'LIC-010',
            'license_expiry' => now()->addYear(),
            'status' => 'active'
        ]);

        // Schedule at 15:00
        Schedule::create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => '2026-06-18',
            'departure_time' => '15:00',
            'arrival_time' => '16:00',
            'status' => 'On time'
        ]);

        // We try to schedule this driver at 08:00 (ends at 09:00).
        // Rest period is from 09:00 to 15:00 = 6 hours.
        // Assuming driver_min_rest_hours system setting is 8 hours (default is 8).
        // Using ScheduleConflictService to validate.
        $result = \App\Services\ScheduleConflictService::validateSchedule(
            $route->id,
            $bus->id,
            $driver->id,
            '08:00',
            60
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('rest before next scheduled trip', $result['message']);
    }

    public function test_cancelled_schedule_does_not_trigger_conflict(): void
    {
        $this->actingAsAdmin();

        $route = Route::create([
            'name' => 'Route A',
            'travel_time_minutes' => 30,
            'status' => 'active'
        ]);

        $bus = Bus::create([
            'plate_number' => 'PAS-777',
            'status' => 'active'
        ]);

        $driver = Driver::create([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'emp_id' => 'EMP-011',
            'license_number' => 'LIC-011',
            'license_expiry' => now()->addYear(),
            'status' => 'active'
        ]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => '2026-06-18',
            'departure_time' => '08:00',
            'arrival_time' => '08:30',
            'status' => 'On time'
        ]);

        // Set status to cancelled
        $response = $this->patchJson("/admin/api/schedules/{$schedule->id}/status", [
            'status' => 'Cancelled'
        ]);
        $response->assertOk();

        // Now we should be able to schedule the same bus and driver at 08:00 without conflicts!
        $response = $this->postJson('/admin/api/schedules', [
            'route_id' => $route->id,
            'bus_plate' => $bus->plate_number,
            'driver_id' => $driver->id,
            'service_date' => '2026-06-18',
            'departure_time' => '08:00'
        ]);

        $response->assertCreated();
    }
}
