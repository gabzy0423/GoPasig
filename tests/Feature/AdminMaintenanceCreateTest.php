<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\User;
use App\Models\MaintenanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMaintenanceCreateTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    public function test_admin_can_access_maintenance_create_page(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::create([
            'plate_number' => 'PAS-1234',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $response = $this->get('/admin/maintenance/create');

        $response->assertRedirect('/admin/dashboard#maintenance');
    }

    public function test_unauthorized_users_cannot_access_maintenance_create_page(): void
    {
        $response = $this->get('/admin/maintenance/create');
        $response->assertRedirect('/login');

        $driver = User::factory()->create(['role' => 'driver']);
        $response = $this->actingAs($driver)->get('/admin/maintenance/create');
        $response->assertStatus(403);
    }

    public function test_admin_can_store_maintenance_session_via_api(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::create([
            'plate_number' => 'PAS-5678',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $response = $this->postJson('/admin/api/maintenance', [
            'bus_id' => $bus->id,
            'type' => 'Preventive Maintenance',
            'description' => 'Technician: Juan Dela Cruz | Notes: Routine checkup',
            'scheduled_at' => '2026-06-12T14:00',
            'status' => 'scheduled',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Maintenance scheduled successfully. Bus locked to maintenance status.'
        ]);

        $this->assertDatabaseHas('maintenance_records', [
            'bus_id' => $bus->id,
            'type' => 'Preventive Maintenance',
            'status' => 'scheduled',
        ]);

        $bus->refresh();
        $this->assertEquals('maintenance', $bus->status);
    }
}
