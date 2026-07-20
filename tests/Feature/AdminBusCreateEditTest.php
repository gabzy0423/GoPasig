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
            'fleet_number' => 'BUS-009',
            'vin' => '1234567890ABCDEF9',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2024,
            'battery_capacity_kwh' => 350.00,
            'charging_port_type' => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $response = $this->get("/admin/buses/{$bus->id}/edit");
 
        $response->assertStatus(200);
        $response->assertViewHas('bus');
        $response->assertViewHas('routes');
        $response->assertViewHas('drivers');
    }

    public function test_admin_can_access_bus_show_page(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::create([
            'plate_number' => 'PAS-9999',
            'fleet_number' => 'BUS-009',
            'vin' => '1234567890ABCDEF9',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2024,
            'battery_capacity_kwh' => 350.00,
            'charging_port_type' => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $response = $this->get("/admin/buses/{$bus->id}");

        $response->assertStatus(200);
        $response->assertViewHas('bus');
    }

    public function test_unauthorized_users_cannot_access_bus_show_page(): void
    {
        $bus = Bus::create([
            'plate_number' => 'PAS-9999',
            'fleet_number' => 'BUS-009',
            'vin' => '1234567890ABCDEF9',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2024,
            'battery_capacity_kwh' => 350.00,
            'charging_port_type' => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $response = $this->get("/admin/buses/{$bus->id}");
        $response->assertRedirect('/login');

        $driver = User::factory()->create(['role' => 'driver']);
        $response = $this->actingAs($driver)->get("/admin/buses/{$bus->id}");
        $response->assertStatus(403);
    }

    public function test_admin_can_store_bus_via_api(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/admin/api/buses', [
            'plate_number' => 'PAS-8888',
            'fleet_number' => 'BUS-001',
            'vin' => '1234567890ABCDEF1',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2024,
            'battery_capacity_kwh' => 350.00,
            'charging_port_type' => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'capacity' => 50,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Bus successfully registered!'
        ]);

        $this->assertDatabaseHas('buses', [
            'plate_number' => 'PAS-8888',
            'fleet_number' => 'BUS-001',
            'status' => 'inactive',
        ]);
    }

    public function test_admin_can_update_bus_via_api(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::create([
            'plate_number' => 'PAS-7777',
            'fleet_number' => 'BUS-007',
            'vin' => '1234567890ABCDEF7',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2024,
            'battery_capacity_kwh' => 350.00,
            'charging_port_type' => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'driver_name' => 'Old Driver',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $response = $this->putJson("/admin/api/buses/{$bus->id}", [
            'fleet_number' => 'BUS-007',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2024,
            'battery_capacity_kwh' => 350.00,
            'charging_port_type' => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'driver_name' => 'Old Driver',
            'capacity' => 55,
            'route_id' => null,
            'status' => 'breakdown',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Bus successfully updated!'
        ]);

        $this->assertDatabaseHas('buses', [
            'id' => $bus->id,
            'driver_name' => 'Old Driver',
            'capacity' => 55,
            'status' => 'breakdown',
        ]);
    }

    public function test_admin_cannot_update_inactive_bus_to_active_without_driver_and_route(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::create([
            'plate_number' => 'PAS-7778',
            'fleet_number' => 'BUS-008',
            'vin' => '1234567890ABCDEF8',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2024,
            'battery_capacity_kwh' => 350.00,
            'charging_port_type' => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'driver_name' => 'Old Driver',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $response = $this->putJson("/admin/api/buses/{$bus->id}", [
            'fleet_number' => 'BUS-008',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2024,
            'battery_capacity_kwh' => 350.00,
            'charging_port_type' => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'driver_name' => 'New Driver',
            'capacity' => 55,
            'route_id' => null,
            'status' => 'active', // active status requires driver and route
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_update_active_bus_keeping_active_status(): void
    {
        $this->actingAsAdmin();

        $route = \App\Models\Route::factory()->create();
        $driver = \App\Models\Driver::factory()->create();

        $bus = Bus::create([
            'plate_number' => 'PAS-7779',
            'fleet_number' => 'BUS-079',
            'vin' => '1234567890ABCDEF9',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2024,
            'battery_capacity_kwh' => 350.00,
            'charging_port_type' => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'driver_name' => $driver->first_name . ' ' . $driver->last_name,
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
            'route_id' => $route->id,
        ]);

        $response = $this->putJson("/admin/api/buses/{$bus->id}", [
            'fleet_number' => 'BUS-079',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2024,
            'battery_capacity_kwh' => 350.00,
            'charging_port_type' => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'driver_name' => $driver->first_name . ' ' . $driver->last_name,
            'capacity' => 55,
            'route_id' => $route->id,
            'status' => 'active',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('buses', [
            'id' => $bus->id,
            'status' => 'active',
            'capacity' => 55,
        ]);
    }
}
