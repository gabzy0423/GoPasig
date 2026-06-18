<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\User;
use App\Models\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBusCreateEditTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    public function test_admin_can_access_bus_create_page(): void
    {
        $this->actingAsAdmin();

        $response = $this->get('/admin/buses/create');

        $response->assertStatus(200);
        $response->assertViewHas('minCapacity', 10);
        $response->assertViewHas('maxCapacity', 150);
        $response->assertViewHas('defaultCapacity', 45);
    }

    public function test_unauthorized_users_cannot_access_bus_create_page(): void
    {
        $response = $this->get('/admin/buses/create');
        $response->assertRedirect('/login');

        $driver = User::factory()->create(['role' => 'driver']);
        $response = $this->actingAs($driver)->get('/admin/buses/create');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_bus_edit_page(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::create([
            'plate_number' => 'PAS-9999',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $response = $this->get("/admin/buses/{$bus->id}/edit");

        $response->assertRedirect('/admin/dashboard#buses');
    }

    public function test_admin_can_store_bus_via_api(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/admin/api/buses', [
            'plate_number' => 'PAS-8888',
            'driver_name' => 'Cardo Dalisay',
            'capacity' => 50,
            'route_id' => null,
            'status' => 'active',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Bus successfully registered!'
        ]);

        $this->assertDatabaseHas('buses', [
            'plate_number' => 'PAS-8888',
            'driver_name' => 'Cardo Dalisay',
            'capacity' => 50,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_update_bus_via_api(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::create([
            'plate_number' => 'PAS-7777',
            'driver_name' => 'Old Driver',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $response = $this->putJson("/admin/api/buses/{$bus->id}", [
            'plate_number' => 'PAS-7777', // plate number matches
            'driver_name' => 'New Driver',
            'capacity' => 55,
            'route_id' => null,
            'status' => 'active',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Bus successfully updated!'
        ]);

        $this->assertDatabaseHas('buses', [
            'id' => $bus->id,
            'driver_name' => 'New Driver',
            'capacity' => 55,
            'status' => 'active',
        ]);
    }
}
