<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Bus;
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

    /**
     * Test 1: Create bus with default capacity (45)
     * ✅ Should succeed
     */
    public function test_create_bus_with_default_capacity()
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.api.buses.store'), [
            'plate_number' => 'PAS-001',
            'driver_name' => 'John Doe',
            'capacity' => 45,
            'status' => 'active',
            'route_id' => null
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
     * ✅ Should succeed (was failing before with max:100)
     */
    public function test_create_bus_with_capacity_120()
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.api.buses.store'), [
            'plate_number' => 'PAS-120',
            'driver_name' => 'Maria Cruz',
            'capacity' => 120,
            'status' => 'active',
            'route_id' => null
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
     * ✅ Should succeed
     */
    public function test_create_bus_with_max_capacity_150()
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.api.buses.store'), [
            'plate_number' => 'PAS-150',
            'driver_name' => 'Pedro Santos',
            'capacity' => 150,
            'status' => 'active',
            'route_id' => null
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
     * ✅ Should succeed
     */
    public function test_create_bus_with_min_capacity_10()
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.api.buses.store'), [
            'plate_number' => 'PAS-010',
            'driver_name' => 'Jose Rizal',
            'capacity' => 10,
            'status' => 'inactive',
            'route_id' => null
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
     * ❌ Should fail
     */
    public function test_create_bus_with_capacity_below_min()
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.api.buses.store'), [
            'plate_number' => 'PAS-005',
            'driver_name' => 'Invalid Test',
            'capacity' => 5,
            'status' => 'active',
            'route_id' => null
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('buses', ['plate_number' => 'PAS-005']);
    }

    /**
     * Test 6: Create bus with capacity 200 (above max)
     * ❌ Should fail
     */
    public function test_create_bus_with_capacity_above_max()
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.api.buses.store'), [
            'plate_number' => 'PAS-200',
            'driver_name' => 'Invalid Test',
            'capacity' => 200,
            'status' => 'active',
            'route_id' => null
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('buses', ['plate_number' => 'PAS-200']);
    }

    /**
     * Test 7: Update bus capacity to 120 (critical test)
     * ✅ Should succeed
     */
    public function test_update_bus_capacity_to_120()
    {
        $this->actingAsAdmin();

        $bus = Bus::factory()->create(['capacity' => 45]);

        $response = $this->putJson(route('admin.api.buses.update', $bus->id), [
            'plate_number' => $bus->plate_number,
            'driver_name' => $bus->driver_name,
            'capacity' => 120,
            'status' => $bus->status,
            'route_id' => $bus->route_id
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

    /**
     * Test 9: Create endpoint returns proper constraints in response
     */
    public function test_create_view_passes_capacity_constraints()
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.buses.create'));

        $response->assertViewHas('minCapacity', 10);
        $response->assertViewHas('maxCapacity', 150);
        $response->assertViewHas('defaultCapacity', 45);
    }
}
