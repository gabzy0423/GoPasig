<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\MaintenanceRecord;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceDurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SystemSettingSeeder::class);
        
        // Create admin user for auth
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
    }

    /**
     * Test 1: Verify default_maintenance_duration_minutes setting exists in database
     */
    public function test_default_maintenance_duration_setting_exists()
    {
        $setting = SystemSetting::where('key', 'default_maintenance_duration_minutes')->first();
        $this->assertNotNull($setting);
        $this->assertEquals('120', $setting->value);
    }

    /**
     * Test 2: Verify SystemSetting::get() retrieves correct default value
     */
    public function test_system_setting_get_returns_correct_default()
    {
        $duration = SystemSetting::get('default_maintenance_duration_minutes', 120);
        $this->assertEquals(120, $duration);
    }

    /**
     * Test 3: Store maintenance record without explicit duration uses dynamic default
     */
    public function test_store_maintenance_without_duration_uses_dynamic_default()
    {
        $bus = Bus::create([
            'plate_number' => 'TEST-001',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $response = $this->postJson(route('admin.api.maintenance.store'), [
            'bus_id' => $bus->id,
            'type' => 'Oil Change',
            'description' => 'Routine oil change',
            'scheduled_at' => now()->addDays(5),
            'status' => 'scheduled',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        
        $record = MaintenanceRecord::where('bus_id', $bus->id)->first();
        $this->assertNotNull($record);
        $this->assertEquals(120, $record->expected_duration_minutes);
    }

    /**
     * Test 4: Store maintenance record with explicit duration overrides default
     */
    public function test_store_maintenance_with_explicit_duration_overrides_default()
    {
        $bus = Bus::create([
            'plate_number' => 'TEST-002',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $response = $this->postJson(route('admin.api.maintenance.store'), [
            'bus_id' => $bus->id,
            'type' => 'Engine Repair',
            'description' => 'Major engine repair',
            'scheduled_at' => now()->addDays(5),
            'status' => 'scheduled',
            'expected_duration_minutes' => 240,
        ]);

        $response->assertStatus(201);
        
        $record = MaintenanceRecord::where('bus_id', $bus->id)->first();
        $this->assertEquals(240, $record->expected_duration_minutes);
    }

    /**
     * Test 5: Change system setting and verify new records use updated value
     */
    public function test_changing_system_setting_affects_new_records()
    {
        $bus = Bus::create([
            'plate_number' => 'TEST-003',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        // Update the system setting to 180 minutes
        SystemSetting::where('key', 'default_maintenance_duration_minutes')
            ->update(['value' => '180']);

        $response = $this->postJson(route('admin.api.maintenance.store'), [
            'bus_id' => $bus->id,
            'type' => 'Transmission Service',
            'description' => 'Transmission fluid replacement',
            'scheduled_at' => now()->addDays(5),
            'status' => 'scheduled',
        ]);

        $response->assertStatus(201);
        
        $record = MaintenanceRecord::where('bus_id', $bus->id)->first();
        $this->assertEquals(180, $record->expected_duration_minutes);
    }

    /**
     * Test 6: Verify fallback value of 120 works when setting doesn't exist
     */
    public function test_fallback_value_when_setting_missing()
    {
        // Delete the setting
        SystemSetting::where('key', 'default_maintenance_duration_minutes')->delete();

        $duration = SystemSetting::get('default_maintenance_duration_minutes', 120);
        $this->assertEquals(120, $duration);
    }

    /**
     * Test 7: Store maintenance with minimum valid duration (1 minute)
     */
    public function test_store_maintenance_with_minimum_duration()
    {
        $bus = Bus::create([
            'plate_number' => 'TEST-004',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $response = $this->postJson(route('admin.api.maintenance.store'), [
            'bus_id' => $bus->id,
            'type' => 'Quick Check',
            'description' => 'Quick diagnostic',
            'scheduled_at' => now()->addDays(5),
            'status' => 'scheduled',
            'expected_duration_minutes' => 1,
        ]);

        $response->assertStatus(201);
        
        $record = MaintenanceRecord::where('bus_id', $bus->id)->first();
        $this->assertEquals(1, $record->expected_duration_minutes);
    }

    /**
     * Test 8: Store maintenance with large duration value
     */
    public function test_store_maintenance_with_large_duration()
    {
        $bus = Bus::create([
            'plate_number' => 'TEST-005',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $response = $this->postJson(route('admin.api.maintenance.store'), [
            'bus_id' => $bus->id,
            'type' => 'Complete Overhaul',
            'description' => 'Full engine overhaul',
            'scheduled_at' => now()->addDays(5),
            'status' => 'scheduled',
            'expected_duration_minutes' => 1440, // 24 hours
        ]);

        $response->assertStatus(201);
        
        $record = MaintenanceRecord::where('bus_id', $bus->id)->first();
        $this->assertEquals(1440, $record->expected_duration_minutes);
    }

    /**
     * Test 9: Verify multiple records can use different durations
     */
    public function test_multiple_records_with_different_durations()
    {
        $bus1 = Bus::create([
            'plate_number' => 'TEST-006',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);
        $bus2 = Bus::create([
            'plate_number' => 'TEST-007',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        // First record with default duration
        $this->postJson(route('admin.api.maintenance.store'), [
            'bus_id' => $bus1->id,
            'type' => 'Service A',
            'scheduled_at' => now()->addDays(5),
            'status' => 'scheduled',
        ]);

        // Second record with explicit duration
        $this->postJson(route('admin.api.maintenance.store'), [
            'bus_id' => $bus2->id,
            'type' => 'Service B',
            'scheduled_at' => now()->addDays(5),
            'status' => 'scheduled',
            'expected_duration_minutes' => 300,
        ]);

        $record1 = MaintenanceRecord::where('bus_id', $bus1->id)->first();
        $record2 = MaintenanceRecord::where('bus_id', $bus2->id)->first();

        $this->assertEquals(120, $record1->expected_duration_minutes);
        $this->assertEquals(300, $record2->expected_duration_minutes);
    }
}
