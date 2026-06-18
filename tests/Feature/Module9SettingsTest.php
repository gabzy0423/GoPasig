<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class Module9SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SystemSettingSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /**
     * Test validation rules for setting types.
     */
    public function test_setting_validation_rules()
    {
        // 1. Integer key must fail with non-integer value
        $response = $this->actingAs($this->admin)->postJson(route('admin.api.settings.store'), [
            'type' => 'system',
            'key' => 'bus_capacity_default',
            'value' => 'not-an-integer',
        ]);
        $response->assertStatus(422);

        // 2. Integer key succeeds with valid integer
        $response = $this->actingAs($this->admin)->postJson(route('admin.api.settings.store'), [
            'type' => 'system',
            'key' => 'bus_capacity_default',
            'value' => '50',
        ]);
        $response->assertStatus(200);
        $this->assertEquals('50', SystemSetting::get('bus_capacity_default'));

        // 3. Float/Numeric key must fail with invalid numeric string
        $response = $this->actingAs($this->admin)->postJson(route('admin.api.settings.store'), [
            'type' => 'system',
            'key' => 'map_default_latitude',
            'value' => 'abc',
        ]);
        $response->assertStatus(422);

        // 4. Float/Numeric key succeeds with valid numeric
        $response = $this->actingAs($this->admin)->postJson(route('admin.api.settings.store'), [
            'type' => 'system',
            'key' => 'map_default_latitude',
            'value' => '14.1234',
        ]);
        $response->assertStatus(200);
        $this->assertEquals('14.1234', SystemSetting::get('map_default_latitude'));

        // 5. Time key fails with invalid H:i format
        $response = $this->actingAs($this->admin)->postJson(route('admin.api.settings.store'), [
            'type' => 'system',
            'key' => 'schedule_default_departure_time',
            'value' => '18:99',
        ]);
        $response->assertStatus(422);

        // 6. Time key succeeds with valid format
        $response = $this->actingAs($this->admin)->postJson(route('admin.api.settings.store'), [
            'type' => 'system',
            'key' => 'schedule_default_departure_time',
            'value' => '08:30',
        ]);
        $response->assertStatus(200);
        $this->assertEquals('08:30', SystemSetting::get('schedule_default_departure_time'));
    }

    /**
     * Test dynamic cache TTL config.
     */
    public function test_dynamic_cache_ttl()
    {
        // Seed standard TTL settings
        SystemSetting::updateOrCreate(['key' => 'system_setting_cache_ttl_seconds'], ['value' => '50']);
        Cache::forget('system_setting_cache_ttl_val');

        // Verify SystemSetting::get fetches using the new TTL config
        SystemSetting::get('system_setting_cache_ttl_seconds');
        $this->assertTrue(Cache::has('system_setting_system_setting_cache_ttl_seconds'));
    }

    /**
     * Test cache invalidation on save.
     */
    public function test_cache_invalidation_on_save()
    {
        // Cache some dummy values
        Cache::put('routes_all', 'dummy-routes', 30);
        Cache::put('commuter_dashboard_aggregate', 'dummy-dashboard', 30);

        // Perform save
        $response = $this->actingAs($this->admin)->postJson(route('admin.api.settings.store'), [
            'type' => 'system',
            'key' => 'bus_capacity_default',
            'value' => '40',
        ]);
        $response->assertStatus(200);

        // Caches must be cleared
        $this->assertFalse(Cache::has('routes_all'));
        $this->assertFalse(Cache::has('commuter_dashboard_aggregate'));
    }

    /**
     * Test adding new settings dynamically.
     */
    public function test_add_new_settings_dynamically()
    {
        $response = $this->actingAs($this->admin)->postJson(route('admin.api.settings.store'), [
            'type' => 'system',
            'key' => 'brand_new_custom_setting_key',
            'value' => 'Custom Value',
            'description' => 'A custom setting description for test',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Custom Value', SystemSetting::get('brand_new_custom_setting_key'));
    }
}
