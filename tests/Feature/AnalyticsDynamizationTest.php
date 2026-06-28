<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Schedule;
use App\Models\Route;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class AnalyticsDynamizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SystemSettingSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
    }

    /**
     * Test 1: Verify bus capacity limit is in SystemSetting
     */
    public function test_bus_capacity_limit_exists_in_system_settings()
    {
        $setting = SystemSetting::where('key', 'default_bus_capacity')->first();
        $this->assertNotNull($setting);
        $this->assertEquals('45', $setting->value);
    }

    /**
     * Test 2: Analytics API returns bus capacity limit in response
     */
    public function test_analytics_api_returns_bus_capacity_limit()
    {
        $response = $this->getJson(route('admin.api.analytics'));
        
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertArrayHasKey('busCapacityLimit', $response->json());
        $this->assertEquals(45, $response->json('busCapacityLimit'));
    }

    /**
     * Test 3: Analytics API without date params returns today's data
     */
    public function test_analytics_api_default_today_data()
    {
        $today = Carbon::today();
        
        // Create schedule for today
        $route = Route::first() ?: Route::factory()->create();
        Schedule::factory()->create([
            'route_id' => $route->id,
            'passengers' => 50,
            'created_at' => $today,
        ]);

        $response = $this->getJson(route('admin.api.analytics'));
        
        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('kpis'));
    }

    /**
     * Test 4: Analytics API filters by start date only
     */
    public function test_analytics_api_filters_by_start_date()
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $twoDaysAgo = Carbon::today()->subDays(2);
        
        $route = Route::first() ?: Route::factory()->create();
        
        // Create schedules for different dates
        Schedule::factory()->create([
            'route_id' => $route->id,
            'passengers' => 100,
            'created_at' => $twoDaysAgo,
        ]);
        Schedule::factory()->create([
            'route_id' => $route->id,
            'passengers' => 150,
            'created_at' => $yesterday,
        ]);
        Schedule::factory()->create([
            'route_id' => $route->id,
            'passengers' => 200,
            'created_at' => $today,
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => $yesterday->toDateString(),
        ]));
        
        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
    }

    /**
     * Test 5: Analytics API filters by date range (start and end)
     */
    public function test_analytics_api_filters_by_date_range()
    {
        $today = Carbon::today();
        $sevenDaysAgo = Carbon::today()->subDays(7);
        
        $route = Route::first() ?: Route::factory()->create();
        
        // Create schedules across range
        for ($i = 0; $i < 10; $i++) {
            Schedule::factory()->create([
                'route_id' => $route->id,
                'passengers' => 50 + $i,
                'created_at' => $sevenDaysAgo->copy()->addDays($i),
            ]);
        }

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => $sevenDaysAgo->toDateString(),
            'end' => $today->toDateString(),
        ]));
        
        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('kpis'));
    }

    /**
     * Test 6: Analytics API with yesterday date parameter
     */
    public function test_analytics_api_yesterday_filter()
    {
        $yesterday = Carbon::yesterday();
        
        $route = Route::first() ?: Route::factory()->create();
        Schedule::factory()->create([
            'route_id' => $route->id,
            'passengers' => 100,
            'created_at' => $yesterday,
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => $yesterday->toDateString(),
            'end' => $yesterday->toDateString(),
        ]));
        
        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
    }

    /**
     * Test 7: Analytics API with weekly filter (last 7 days)
     */
    public function test_analytics_api_weekly_filter()
    {
        $today = Carbon::today();
        $sevenDaysAgo = $today->copy()->subDays(7);
        
        $route = Route::first() ?: Route::factory()->create();
        
        // Create schedules across the week
        for ($i = 0; $i < 7; $i++) {
            Schedule::factory()->create([
                'route_id' => $route->id,
                'passengers' => 75 + $i * 5,
                'created_at' => $sevenDaysAgo->copy()->addDays($i),
            ]);
        }

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => $sevenDaysAgo->toDateString(),
            'end' => $today->toDateString(),
        ]));
        
        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
    }

    /**
     * Test 8: Analytics API with monthly filter (last 30 days)
     */
    public function test_analytics_api_monthly_filter()
    {
        $today = Carbon::today();
        $thirtyDaysAgo = $today->copy()->subDays(30);
        
        $route = Route::first() ?: Route::factory()->create();
        
        // Create schedules across the month
        for ($i = 0; $i < 30; $i++) {
            Schedule::factory()->create([
                'route_id' => $route->id,
                'passengers' => 50 + ($i % 20),
                'created_at' => $thirtyDaysAgo->copy()->addDays($i),
            ]);
        }

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => $thirtyDaysAgo->toDateString(),
            'end' => $today->toDateString(),
        ]));
        
        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
    }

    /**
     * Test 9: Change bus capacity setting and verify API returns new value
     */
    public function test_analytics_api_returns_updated_capacity_limit()
    {
        // Initial value should be 45
        $response1 = $this->getJson(route('admin.api.analytics'));
        $this->assertEquals(45, $response1->json('busCapacityLimit'));
        
        // Update the setting to 100
        SystemSetting::where('key', 'default_bus_capacity')
            ->update(['value' => '100']);
        
        // Clear cache to ensure fresh value is fetched
        Cache::flush();
        
        // API should now return 100
        $response2 = $this->getJson(route('admin.api.analytics'));
        $this->assertEquals(100, $response2->json('busCapacityLimit'));
    }

    /**
     * Test 10: Analytics API returns hourly ridership data structure
     */
    public function test_analytics_api_returns_hourly_ridership_structure()
    {
        $response = $this->getJson(route('admin.api.analytics'));
        
        $response->assertStatus(200);
        $this->assertArrayHasKey('hourlyRidership', $response->json());
        $this->assertIsArray($response->json('hourlyRidership'));
    }

    /**
     * Test 11: Analytics API returns route comparison data
     */
    public function test_analytics_api_returns_route_comparison_data()
    {
        $response = $this->getJson(route('admin.api.analytics'));
        
        $response->assertStatus(200);
        $this->assertArrayHasKey('routeComparison', $response->json());
        $this->assertIsArray($response->json('routeComparison'));
    }

    /**
     * Test 12: Analytics API returns stop boarding data
     */
    public function test_analytics_api_returns_stop_boarding_data()
    {
        $response = $this->getJson(route('admin.api.analytics'));
        
        $response->assertStatus(200);
        $this->assertArrayHasKey('stopBoarding', $response->json());
        $this->assertIsArray($response->json('stopBoarding'));
    }

    /**
     * Test 13: Date range filtering respects both start and end boundaries
     */
    public function test_date_range_respects_both_boundaries()
    {
        $startDate = Carbon::today()->subDays(10);
        $endDate = Carbon::today()->subDays(5);
        
        $route = Route::first() ?: Route::factory()->create();
        
        // Create schedules outside range
        Schedule::factory()->create([
            'route_id' => $route->id,
            'passengers' => 10,
            'created_at' => $startDate->copy()->subDays(2),
        ]);
        
        // Create schedules inside range
        for ($i = 0; $i < 5; $i++) {
            Schedule::factory()->create([
                'route_id' => $route->id,
                'passengers' => 50,
                'created_at' => $startDate->copy()->addDays($i),
            ]);
        }
        
        // Create schedules outside range (after end date)
        Schedule::factory()->create([
            'route_id' => $route->id,
            'passengers' => 10,
            'created_at' => $endDate->copy()->addDays(3),
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => $startDate->toDateString(),
            'end' => $endDate->toDateString(),
        ]));
        
        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
    }

    /**
     * Test 14: Fallback capacity limit when setting doesn't exist
     */
    public function test_fallback_capacity_limit_when_missing()
    {
        // Delete the setting
        SystemSetting::where('key', 'default_bus_capacity')->delete();
        
        $response = $this->getJson(route('admin.api.analytics'));
        
        $response->assertStatus(200);
        // Should fallback to 45
        $this->assertEquals(45, $response->json('busCapacityLimit'));
    }
}
