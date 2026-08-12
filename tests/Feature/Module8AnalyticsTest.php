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
            'name' => 'Route 2',
            'color' => '#BA7517',
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
     * Test Passengers Handled uses boarded events while other passenger totals remain deferred.
     */
    public function test_passengers_handled_is_zero_without_events_and_not_schedule_backed()
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
        $this->assertEquals(0, $response->json('kpis.total_pax_today'));
        $this->assertEquals(0, $response->json('kpis.avg_pax_trip'));
    }

    /**
     * Test trip load records keep peak load on Trip while passenger movement starts at zero without events.
     */
    public function test_trip_load_table_uses_trip_peak_load()
    {
        Schedule::create([
            'route_id' => $this->route->id,
            'service_date' => Carbon::today()->toDateString(),
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'departure_time' => '08:00:00',
            'arrival_time' => '09:00:00',
            'passengers' => 99,
            'status' => 'On time',
        ]);

        Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'completed',
            'peak_passengers' => 35,
            'started_at' => Carbon::today()->setTime(8, 0),
            'ended_at' => Carbon::today()->setTime(9, 0),
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => Carbon::today()->toDateString(),
            'end' => Carbon::today()->toDateString()
        ]));

        $response->assertStatus(200);
        $tripRow = $response->json('tripPaxTable.0');
        $this->assertNotNull($tripRow);
        $this->assertEquals('Completed', $tripRow['status']);
        $this->assertEquals(35, $tripRow['peakLoad']);
        $this->assertEquals(0, $tripRow['recordedBoarded']);
        $this->assertEquals(0, $tripRow['recordedAlighted']);
        $this->assertArrayNotHasKey('boarded', $tripRow);
        $this->assertArrayNotHasKey('alighted', $tripRow);
        $this->assertArrayNotHasKey('capacity', $tripRow);
        $this->assertNotEquals(99, $tripRow['peakLoad']);
    }

    /**
     * Test weekly passenger totals use boarded events instead of DemandHistory and schedules.
     */
    public function test_weekly_passenger_totals_are_zero_without_events_and_not_mixed_sources()
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
        $this->assertEquals(0, $response->json('kpis.pax_this_week'));
        $this->assertEquals('Recorded boarded events in selected period', $response->json('kpis.pax_change_last_week'));
    }

    /**
     * Test driver performance metrics are calculated dynamically from actual trips.
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

        Trip::create([
            'route_id' => $this->route->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'status' => 'completed',
            'gps_session' => 'CLOSED',
            'started_at' => Carbon::today()->setTime(8, 0),
            'ended_at' => Carbon::today()->setTime(9, 0),
            'peak_passengers' => 28,
        ]);

        Trip::create([
            'route_id' => $this->route->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => Carbon::today()->setTime(10, 0),
            'ended_at' => null,
            'peak_passengers' => 34,
        ]);

        $response = $this->getJson(route('admin.api.analytics'));
        $response->assertStatus(200);

        $driverPerf = collect($response->json('driverPerformance'))->firstWhere('name', 'John Doe');
        $this->assertNotNull($driverPerf);
        $this->assertEquals(2, $driverPerf['tripsRun']);
        $this->assertEquals(1, $driverPerf['completedTrips']);
        $this->assertEquals(1, $driverPerf['ongoingTrips']);
        $this->assertEquals(34, $driverPerf['peakLoad']);
        $this->assertEquals(0, $driverPerf['pax']);
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
