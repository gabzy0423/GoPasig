<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Trip;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminBusCapacityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed system settings before each test
        $this->artisan('db:seed', ['--class' => 'SystemSettingSeeder']);
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    private function busUpdatePayload(Bus $bus, array $overrides = []): array
    {
        return array_merge([
            'fleet_number'          => $bus->fleet_number,
            'manufacturer'          => $bus->manufacturer,
            'manufacturer_custom'   => null,
            'model'                 => $bus->model,
            'year_model'            => $bus->year_model,
            'battery_capacity_kwh'  => $bus->battery_capacity_kwh,
            'charging_port_type'    => $bus->charging_port_type,
            'max_charging_power_kw' => $bus->max_charging_power_kw,
            'driver_name'           => $bus->driver_name,
            'capacity'              => $bus->capacity,
            'status'                => $bus->status,
            'route_id'              => $bus->route_id,
        ], $overrides);
    }

    /**
     * Test 1: Create bus with default capacity (45)
     * Should succeed
     */
    public function test_create_bus_with_default_capacity()
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.api.buses.store'), [
            'plate_number'          => 'PAS-001',
            'fleet_number'          => 'BUS-001',
            'vin'                   => '1234567890ABCDEF1',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.00,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'capacity'              => 45,
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('buses', [
            'plate_number' => 'PAS-001',
            'capacity' => 45
        ]);
    }

    /**
     * Test 2: Create bus with capacity 120 (THE CRITICAL BUG FIX)
     * Should succeed
     */
    public function test_create_bus_with_capacity_120()
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.api.buses.store'), [
            'plate_number'          => 'PAS-120',
            'fleet_number'          => 'BUS-120',
            'vin'                   => '1234567890ABCDEF2',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.00,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'capacity'              => 120,
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('buses', [
            'plate_number' => 'PAS-120',
            'capacity' => 120
        ]);
    }

    /**
     * Test 3: Create bus with capacity 150 (max allowed)
     * Should succeed
     */
    public function test_create_bus_with_max_capacity_150()
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.api.buses.store'), [
            'plate_number'          => 'PAS-150',
            'fleet_number'          => 'BUS-150',
            'vin'                   => '1234567890ABCDEF3',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.00,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'capacity'              => 150,
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('buses', [
            'plate_number' => 'PAS-150',
            'capacity' => 150
        ]);
    }

    /**
     * Test 4: Create bus with capacity 10 (min allowed)
     * Should succeed
     */
    public function test_create_bus_with_min_capacity_10()
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.api.buses.store'), [
            'plate_number'          => 'PAS-010',
            'fleet_number'          => 'BUS-010',
            'vin'                   => '1234567890ABCDEF4',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.00,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'capacity'              => 10,
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('buses', [
            'plate_number' => 'PAS-010',
            'capacity' => 10
        ]);
    }

    /**
     * Test 5: Create bus with capacity 5 (below min)
     * Should fail
     */
    public function test_create_bus_with_capacity_below_min()
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.api.buses.store'), [
            'plate_number'          => 'PAS-005',
            'fleet_number'          => 'BUS-005',
            'vin'                   => '1234567890ABCDEF5',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.00,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'capacity'              => 5,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('buses', ['plate_number' => 'PAS-005']);
    }

    /**
     * Test 6: Create bus with capacity 200 (above max)
     * Should fail
     */
    public function test_create_bus_with_capacity_above_max()
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.api.buses.store'), [
            'plate_number'          => 'PAS-200',
            'fleet_number'          => 'BUS-200',
            'vin'                   => '1234567890ABCDEF6',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.00,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'capacity'              => 200,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('buses', ['plate_number' => 'PAS-200']);
    }

    /**
     * Test 7: Update bus capacity to 120 (critical test)
     * Should succeed
     */
    public function test_update_bus_capacity_to_120()
    {
        $this->actingAsAdmin();

        $bus = Bus::factory()->create(['capacity' => 45]);

        $response = $this->putJson(route('admin.api.buses.update', $bus->id), [
            'fleet_number'          => $bus->fleet_number,
            'manufacturer'          => $bus->manufacturer,
            'model'                 => $bus->model,
            'year_model'            => $bus->year_model,
            'battery_capacity_kwh'  => $bus->battery_capacity_kwh,
            'charging_port_type'    => $bus->charging_port_type,
            'max_charging_power_kw' => $bus->max_charging_power_kw,
            'driver_name'           => $bus->driver_name,
            'capacity'              => 120,
            'status'                => $bus->status,
            'route_id'              => $bus->route_id
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('buses', [
            'id' => $bus->id,
            'capacity' => 120
        ]);
    }

    /**
     * Test 8: Verify SystemSetting values are read correctly
     */
    public function test_system_settings_capacity_constraints()
    {
        $this->actingAsAdmin();

        $minCapacity = SystemSetting::get('bus_capacity_min', 10);
        $maxCapacity = SystemSetting::get('bus_capacity_max', 150);
        $defaultCapacity = SystemSetting::get('default_bus_capacity', 45);

        $this->assertEquals(10, $minCapacity);
        $this->assertEquals(150, $maxCapacity);
        $this->assertEquals(45, $defaultCapacity);
    }

    public function test_bus_seeder_uses_official_verified_capacities()
    {
        $this->artisan('db:seed', ['--class' => 'BusSeeder']);

        foreach (['PAS-001', 'PAS-002', 'PAS-003', 'PAS-004', 'PAS-005'] as $plateNumber) {
            $this->assertDatabaseHas('buses', [
                'plate_number' => $plateNumber,
                'capacity' => 26,
                'is_simulated' => false,
            ]);
        }

        $this->assertDatabaseHas('buses', [
            'plate_number' => 'PAS-006',
            'capacity' => 42,
            'is_simulated' => false,
        ]);
    }

    public function test_update_capacity_below_current_passengers_is_rejected()
    {
        $this->actingAsAdmin();

        $bus = Bus::factory()->create([
            'capacity' => 26,
            'passengers' => 20,
            'status' => 'inactive',
        ]);

        $response = $this->putJson(
            route('admin.api.buses.update', $bus->id),
            $this->busUpdatePayload($bus, ['capacity' => 15])
        );

        $response->assertStatus(422);
        $this->assertDatabaseHas('buses', [
            'id' => $bus->id,
            'capacity' => 26,
            'passengers' => 20,
        ]);
    }

    public function test_update_capacity_for_ready_bus_is_rejected()
    {
        $this->actingAsAdmin();

        $bus = Bus::factory()->create([
            'capacity' => 26,
            'passengers' => 0,
            'status' => 'ready',
        ]);

        $response = $this->putJson(
            route('admin.api.buses.update', $bus->id),
            $this->busUpdatePayload($bus, ['capacity' => 42])
        );

        $response->assertStatus(422);
        $this->assertDatabaseHas('buses', [
            'id' => $bus->id,
            'capacity' => 26,
        ]);
    }

    public function test_update_capacity_for_operating_bus_is_rejected()
    {
        $this->actingAsAdmin();

        $bus = Bus::factory()->create([
            'capacity' => 26,
            'passengers' => 0,
            'status' => 'operating',
        ]);

        $response = $this->putJson(
            route('admin.api.buses.update', $bus->id),
            $this->busUpdatePayload($bus, ['capacity' => 42])
        );

        $response->assertStatus(422);
        $this->assertDatabaseHas('buses', [
            'id' => $bus->id,
            'capacity' => 26,
        ]);
    }

    public function test_update_capacity_for_bus_with_dispatched_trip_is_rejected()
    {
        $this->actingAsAdmin();

        $bus = Bus::factory()->create([
            'capacity' => 26,
            'passengers' => 0,
            'status' => 'inactive',
        ]);
        $driver = Driver::factory()->create();
        $route = Route::factory()->create();

        Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'dispatched',
            'started_at' => null,
            'gps_session' => 'INACTIVE',
        ]);

        $response = $this->putJson(
            route('admin.api.buses.update', $bus->id),
            $this->busUpdatePayload($bus, ['capacity' => 42])
        );

        $response->assertStatus(422);
        $this->assertDatabaseHas('buses', [
            'id' => $bus->id,
            'capacity' => 26,
        ]);
    }

    public function test_update_capacity_for_inactive_bus_without_runtime_passengers_is_allowed()
    {
        $this->actingAsAdmin();

        $bus = Bus::factory()->create([
            'capacity' => 26,
            'passengers' => 0,
            'status' => 'inactive',
        ]);

        $response = $this->putJson(
            route('admin.api.buses.update', $bus->id),
            $this->busUpdatePayload($bus, ['capacity' => 42])
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('buses', [
            'id' => $bus->id,
            'capacity' => 42,
        ]);
    }
}
