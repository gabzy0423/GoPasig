<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Stop;
use App\Models\SystemSetting;
use App\Models\DemandHistory;
use App\Models\Trip;
use App\Models\TripLog;
use App\Models\User;
use App\Models\TimeSlotConfiguration;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Module8AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $route;
    protected $bus;
    protected $driver;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(\Database\Seeders\SystemSettingSeeder::class);
        
        // Ensure timeslots exist
        TimeSlotConfiguration::create([
            'name' => 'Morning Rush',
            'start_time' => '07:00:00',
            'end_time' => '08:00:00',
            'time_slot_display' => '07:00-08:00',
            'is_active' => true,
            'order' => 1
        ]);

        // Seed settings
        SystemSetting::updateOrCreate(['key' => 'analytics_fallback_peak_hour'], ['value' => '7–8 AM']);
        SystemSetting::updateOrCreate(['key' => 'analytics_top_stops_limit'], ['value' => '10']);
        SystemSetting::updateOrCreate(['key' => 'analytics_top_drivers_limit'], ['value' => '5']);
        SystemSetting::updateOrCreate(['key' => 'analytics_historical_trend_limit'], ['value' => '30']);

        // Create admin
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);

        // Create a route
        $this->route = Route::create([
            'name' => 'Route Emerald',
            'color' => '#50C878',
            'status' => 'Active',
        ]);

        // Create a bus
        $this->bus = Bus::create([
            'plate_number' => 'XYZ-123',
            'status' => 'active',
            'capacity' => 50,
            'lat' => 14.5,
            'lng' => 121.0,
            'speed' => 0,
            'passengers' => 0,
        ]);

        // Create a driver
        $this->driver = Driver::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'performance_score' => 95,
        ]);
    }

    /**
     * Test date range filters use service_date instead of created_at.
     */
    public function test_analytics_date_filters_use_service_date()
    {
        // Schedule created today for yesterday
        Schedule::create([
            'route_id' => $this->route->id,
            'service_date' => Carbon::yesterday()->toDateString(),
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '08:00:00',
            'arrival_time' => '09:00:00',
            'passengers' => 25,
            'status' => 'On time',
        ]);

        // Schedule created today for today
        Schedule::create([
            'route_id' => $this->route->id,
            'service_date' => Carbon::today()->toDateString(),
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '08:00:00',
            'arrival_time' => '09:00:00',
            'passengers' => 40,
            'status' => 'On time',
        ]);

        // Request analytics for today
        $response = $this->getJson(route('admin.api.analytics', [
            'start' => Carbon::today()->toDateString(),
            'end' => Carbon::today()->toDateString()
        ]));

        $response->assertStatus(200);
        // Should only count today's passenger count (40), not yesterday's (25)
        $this->assertEquals(40, (int) str_replace(',', '', $response->json('kpis.total_pax_today')));
    }

    /**
     * Test alighted, boarded, and peak load come from trip_logs separately.
     */
    public function test_trip_passenger_flow_disparity()
    {
        $schedule = Schedule::create([
            'route_id' => $this->route->id,
            'service_date' => Carbon::today()->toDateString(),
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '08:00:00',
            'arrival_time' => '09:00:00',
            'passengers' => 30,
            'status' => 'On time',
        ]);

        $trip = Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'completed',
            'peak_passengers' => 35,
            'started_at' => Carbon::today()->setTime(8, 0),
            'ended_at' => Carbon::today()->setTime(9, 0),
        ]);

        TripLog::create([
            'driver_id' => $this->driver->id,
            'trip_id' => $trip->id,
            'bus_id' => $this->bus->id,
            'route_id' => $this->route->id,
            'passengers' => 30,
            'alighted_passengers' => 22,
            'peak_passengers' => 35,
            'completed_at' => Carbon::today()->setTime(9, 0),
            'status' => 'completed',
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => Carbon::today()->toDateString(),
            'end' => Carbon::today()->toDateString()
        ]));

        $response->assertStatus(200);
        $tripRow = $response->json('tripPaxTable.0');
        $this->assertNotNull($tripRow);
        $this->assertEquals(30, $tripRow['boarded']);
        $this->assertEquals(22, $tripRow['alighted']);
        $this->assertEquals(35, $tripRow['peakLoad']);
        $this->assertNotEquals($tripRow['boarded'], $tripRow['alighted']);
        $this->assertNotEquals($tripRow['boarded'], $tripRow['peakLoad']);
    }

    /**
     * Test prevention of double counting on weekly passenger totals.
     */
    public function test_weekly_passenger_double_counting_prevention()
    {
        $todayStr = Carbon::today()->toDateString();

        // Historical data populated for today
        DemandHistory::create([
            'date' => $todayStr,
            'route_id' => $this->route->id,
            'total_commuters' => 100,
            'day_of_week' => Carbon::today()->format('l'),
            'time_slot' => '07:00-08:00',
            'buses_dispatched' => 0
        ]);

        // Today's schedule passengers
        Schedule::create([
            'route_id' => $this->route->id,
            'service_date' => $todayStr,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '07:30:00',
            'arrival_time' => '08:30:00',
            'passengers' => 20,
            'status' => 'On time',
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => $todayStr,
            'end' => $todayStr
        ]));

        $response->assertStatus(200);
        // Pax this week should exclude the 100 historical commuters from today
        // to avoid double counting it with today's live schedule (20).
        $this->assertEquals(20, (int) str_replace(',', '', $response->json('kpis.pax_this_week')));
    }

    /**
     * Test driver performance metrics are calculated dynamically.
     */
    public function test_driver_performance_calculated_dynamically()
    {
        Schedule::create([
            'route_id' => $this->route->id,
            'service_date' => Carbon::today()->toDateString(),
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '08:00:00',
            'arrival_time' => '09:00:00',
            'passengers' => 35,
            'status' => 'On time',
        ]);

        $response = $this->getJson(route('admin.api.analytics'));
        $response->assertStatus(200);

        $driverPerf = collect($response->json('driverPerformance'))->firstWhere('name', 'John Doe');
        $this->assertNotNull($driverPerf);
        $this->assertEquals(1, $driverPerf['trips']);
        $this->assertEquals(35, $driverPerf['pax']);
    }

    /**
     * Test top limits configurations.
     */
    public function test_analytics_limits_configurations()
    {
        // Set limits to 2
        SystemSetting::where('key', 'analytics_top_stops_limit')->update(['value' => '2']);
        SystemSetting::where('key', 'analytics_top_drivers_limit')->update(['value' => '2']);

        // Create stops
        for ($i = 1; $i <= 5; $i++) {
            Stop::create([
                'route_id' => $this->route->id,
                'name' => "Stop $i",
                'lat' => 14.5 + ($i * 0.01),
                'lng' => 121.0 + ($i * 0.01),
                'sequence' => $i,
            ]);
        }

        // Create drivers
        for ($i = 1; $i <= 5; $i++) {
            Driver::factory()->create([
                'first_name' => "Driver",
                'last_name' => "$i",
                'performance_score' => 80 + $i,
            ]);
        }

        $response = $this->getJson(route('admin.api.analytics'));
        $response->assertStatus(200);

        $this->assertLessThanOrEqual(2, count($response->json('stopBoarding')));
        $this->assertLessThanOrEqual(2, count($response->json('driverPerformance')));
    }
}
