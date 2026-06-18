<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Bus;
use App\Models\Route;
use App\Models\TripLog;
use App\Services\TripLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class TripLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_trip_log_table_exists()
    {
        $this->assertTrue(
            \Schema::hasTable('trip_logs'),
            'trip_logs table does not exist'
        );
    }

    public function test_can_create_trip_log()
    {
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create();
        $route = Route::factory()->create();
        $trip = Trip::factory()->create([
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
        ]);

        $tripLog = TripLog::create([
            'driver_id' => $driver->id,
            'trip_id' => $trip->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
            'passengers' => 45,
            'peak_passengers' => 50,
            'status' => 'completed',
            'is_on_time' => true,
        ]);

        $this->assertNotNull($tripLog->id);
        $this->assertEquals($driver->id, $tripLog->driver_id);
        $this->assertEquals(45, $tripLog->passengers);
    }

    public function test_trip_log_service_logs_trip()
    {
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create();
        $route = Route::factory()->create();
        $trip = Trip::factory()->create([
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
            'peak_passengers' => 55,
        ]);

        $tripLog = TripLogService::logTrip($trip, [
            'passengers' => 50,
            'is_on_time' => true,
        ]);

        $this->assertNotNull($tripLog);
        $this->assertEquals($trip->driver_id, $tripLog->driver_id);
        $this->assertEquals(50, $tripLog->passengers);
        $this->assertTrue($tripLog->is_on_time);
    }

    public function test_trip_log_service_logs_delayed_trip()
    {
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create();
        $route = Route::factory()->create();
        $trip = Trip::factory()->create([
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
        ]);

        $tripLog = TripLogService::logTrip($trip, [
            'is_on_time' => false,
            'delay_minutes' => 15,
            'passengers' => 40,
        ]);

        $this->assertFalse($tripLog->is_on_time);
        $this->assertEquals(15, $tripLog->delay_minutes);
    }

    public function test_get_driver_trip_history()
    {
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create();
        $route = Route::factory()->create();

        // Create multiple trips
        for ($i = 0; $i < 5; $i++) {
            $trip = Trip::factory()->create([
                'driver_id' => $driver->id,
                'bus_id' => $bus->id,
                'route_id' => $route->id,
            ]);

            TripLogService::logTrip($trip, [
                'passengers' => 30 + $i,
                'is_on_time' => $i % 2 === 0,
                'delay_minutes' => $i % 2 === 0 ? 0 : 5,
            ]);
        }

        $history = TripLogService::getDriverTripHistory($driver, 30);

        $this->assertEquals(5, $history['total_trips']);
        $this->assertEquals(3, $history['on_time_trips']);
        $this->assertEquals(2, $history['delayed_trips']);
        $this->assertGreaterThan(0, $history['total_passengers']);
        $this->assertGreaterThan(0, $history['avg_passengers_per_trip']);
        $this->assertEquals(60, $history['on_time_rate']);
    }

    public function test_get_on_time_stats()
    {
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create();
        $route = Route::factory()->create();

        // Create 10 trips: 7 on-time, 3 delayed
        for ($i = 0; $i < 10; $i++) {
            $trip = Trip::factory()->create([
                'driver_id' => $driver->id,
                'bus_id' => $bus->id,
                'route_id' => $route->id,
            ]);

            TripLogService::logTrip($trip, [
                'is_on_time' => $i < 7,
                'delay_minutes' => $i < 7 ? 0 : 10,
            ]);
        }

        $stats = TripLogService::getOnTimeStats($driver, 30);

        $this->assertEquals(70, $stats['on_time_rate']);
        $this->assertEquals(10, $stats['total_trips']);
        $this->assertEquals(7, $stats['on_time_trips']);
        $this->assertEquals(3, $stats['delayed_trips']);
        $this->assertEquals(10, $stats['avg_delay_minutes']);
    }

    public function test_get_passenger_stats()
    {
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create();
        $route = Route::factory()->create();

        // Create trips with varying passengers
        $passengers = [30, 40, 50, 45, 55];
        foreach ($passengers as $pax) {
            $trip = Trip::factory()->create([
                'driver_id' => $driver->id,
                'bus_id' => $bus->id,
                'route_id' => $route->id,
            ]);

            TripLogService::logTrip($trip, [
                'passengers' => $pax,
                'peak_passengers' => $pax,
            ]);
        }

        $stats = TripLogService::getPassengerStats($driver, 30);

        $this->assertEquals(220, $stats['total_passengers']); // 30+40+50+45+55
        $this->assertEquals(44, $stats['avg_passengers_per_trip']); // 220/5
        $this->assertEquals(5, $stats['total_trips']);
        $this->assertEquals(55, $stats['peak_passengers']);
    }

    public function test_get_daily_performance()
    {
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create();
        $route = Route::factory()->create();

        // Create trips for multiple days
        for ($day = 6; $day >= 0; $day--) {
            for ($i = 0; $i < 3; $i++) {
                $trip = Trip::factory()->create([
                    'driver_id' => $driver->id,
                    'bus_id' => $bus->id,
                    'route_id' => $route->id,
                ]);

                $tripLog = TripLog::factory()->create([
                    'driver_id' => $driver->id,
                    'trip_id' => $trip->id,
                    'bus_id' => $bus->id,
                    'route_id' => $route->id,
                    'completed_at' => now()->subDays($day),
                    'is_on_time' => $i > 0,
                    'passengers' => 30,
                ]);
            }
        }

        $performance = TripLogService::getDailyPerformance($driver, 7);

        $this->assertCount(7, $performance);
        foreach ($performance as $day) {
            $this->assertEquals(3, $day['trips']);
            $this->assertEquals(2, $day['on_time']);
            $this->assertEquals(1, $day['delayed']);
            $this->assertEquals(90, $day['passengers']);
        }
    }

    public function test_trip_log_relationships()
    {
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create();
        $route = Route::factory()->create();
        $trip = Trip::factory()->create([
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
        ]);

        $tripLog = TripLog::create([
            'driver_id' => $driver->id,
            'trip_id' => $trip->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
            'passengers' => 40,
        ]);

        // Test relationships
        $this->assertEquals($driver->id, $tripLog->driver->id);
        $this->assertEquals($trip->id, $tripLog->trip->id);
        $this->assertEquals($bus->id, $tripLog->bus->id);
        $this->assertEquals($route->id, $tripLog->route->id);
    }

    public function test_driver_has_trip_logs()
    {
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create();
        $route = Route::factory()->create();

        // Create multiple trips
        for ($i = 0; $i < 5; $i++) {
            $trip = Trip::factory()->create([
                'driver_id' => $driver->id,
                'bus_id' => $bus->id,
                'route_id' => $route->id,
            ]);

            TripLog::create([
                'driver_id' => $driver->id,
                'trip_id' => $trip->id,
                'bus_id' => $bus->id,
                'route_id' => $route->id,
                'passengers' => 30,
            ]);
        }

        $tripLogs = $driver->tripLogs()->get();

        $this->assertCount(5, $tripLogs);
    }

    public function test_trip_log_scopes()
    {
        $driver = Driver::factory()->create();
        $otherDriver = Driver::factory()->create();
        $bus = Bus::factory()->create();
        $route = Route::factory()->create();

        // Create trips for both drivers
        for ($i = 0; $i < 3; $i++) {
            $trip = Trip::factory()->create([
                'driver_id' => $driver->id,
                'bus_id' => $bus->id,
                'route_id' => $route->id,
            ]);

            TripLog::create([
                'driver_id' => $driver->id,
                'trip_id' => $trip->id,
                'bus_id' => $bus->id,
                'route_id' => $route->id,
                'is_on_time' => $i < 2,
                'passengers' => 30,
            ]);
        }

        // Create trips for other driver
        for ($i = 0; $i < 2; $i++) {
            $trip = Trip::factory()->create([
                'driver_id' => $otherDriver->id,
                'bus_id' => $bus->id,
                'route_id' => $route->id,
            ]);

            TripLog::create([
                'driver_id' => $otherDriver->id,
                'trip_id' => $trip->id,
                'bus_id' => $bus->id,
                'route_id' => $route->id,
                'passengers' => 30,
            ]);
        }

        // Test forDriver scope
        $driverLogs = TripLog::forDriver($driver->id)->get();
        $this->assertCount(3, $driverLogs);

        // Test onTime scope
        $onTimeLogs = TripLog::forDriver($driver->id)->onTime()->get();
        $this->assertCount(2, $onTimeLogs);

        // Test delayed scope
        $delayedLogs = TripLog::forDriver($driver->id)->delayed()->get();
        $this->assertCount(1, $delayedLogs);
    }

    public function test_trip_log_handles_errors_gracefully()
    {
        // Create trip with missing relationships (should not break)
        $trip = new Trip();
        $trip->driver_id = 999;
        $trip->bus_id = 999;
        $trip->route_id = 999;
        $trip->peak_passengers = 50;

        // Service should handle missing relationships
        $result = TripLogService::logTrip($trip);

        // Should return null on error, not throw exception
        $this->assertNull($result);
    }

    public function test_trip_log_empty_history_stats()
    {
        $driver = Driver::factory()->create();

        // No trips created
        $history = TripLogService::getDriverTripHistory($driver, 30);
        $this->assertEquals(0, $history['total_trips']);

        $stats = TripLogService::getOnTimeStats($driver, 30);
        $this->assertEquals(0, $stats['on_time_rate']);
        $this->assertEquals(0, $stats['total_trips']);

        $passengerStats = TripLogService::getPassengerStats($driver, 30);
        $this->assertEquals(0, $passengerStats['total_passengers']);
    }
}
