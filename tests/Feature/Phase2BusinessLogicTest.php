<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Driver;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Trip;
use App\Models\TripLog;
use App\Services\ScheduleConflictService;
use App\Services\DriverPerformanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class Phase2BusinessLogicTest extends TestCase
{
    use RefreshDatabase;

    protected $driver;
    protected $bus;
    protected $route;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 6, 26, 12, 0, 0, 'UTC'));

        $this->driver = Driver::factory()->create([
            'status' => 'active',
            'performance_score' => 100,
        ]);

        $this->bus = Bus::factory()->create([
            'status' => 'inactive',
            'capacity' => 50,
        ]);

        $this->route = Route::factory()->create([
            'travel_time_minutes' => 30,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ============================================================
    // ScheduleConflictService Tests
    // ============================================================

    /** @test */
    public function test_schedule_conflict_service_validates_available_slot()
    {
        $result = ScheduleConflictService::validateSchedule(
            $this->route->id,
            $this->bus->id,
            $this->driver->id,
            '08:00',
            30
        );

        $this->assertTrue($result['valid']);
        $this->assertStringContainsString('valid', strtolower($result['message']));
    }

    /** @test */
    public function test_schedule_conflict_service_detects_bus_double_booking()
    {
        Schedule::factory()->create([
            'bus_id' => $this->bus->id,
            'departure_time' => '08:00',
            'arrival_time' => '08:30',
            'status' => 'On time',
        ]);

        $result = ScheduleConflictService::validateSchedule(
            $this->route->id,
            $this->bus->id,
            $this->driver->id,
            '08:15',
            30
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('already scheduled', strtolower($result['message']));
    }

    /** @test */
    public function test_schedule_conflict_service_detects_driver_double_booking()
    {
        Schedule::factory()->create([
            'driver_id' => $this->driver->id,
            'departure_time' => '08:00',
            'arrival_time' => '08:30',
            'status' => 'On time',
        ]);

        $result = ScheduleConflictService::validateSchedule(
            $this->route->id,
            $this->bus->id,
            $this->driver->id,
            '08:15',
            30
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('already scheduled', strtolower($result['message']));
    }

    /** @test */
    public function test_schedule_conflict_service_enforces_rest_period()
    {
        Schedule::factory()->create([
            'driver_id' => $this->driver->id,
            'departure_time' => '08:00',
            'arrival_time' => '08:30',
            'status' => 'On time',
        ]);

        $result = ScheduleConflictService::validateSchedule(
            $this->route->id,
            $this->bus->id,
            $this->driver->id,
            '09:00',
            30
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('rest', strtolower($result['message']));
    }

    /** @test */
    public function test_schedule_conflict_service_detects_maintenance_bus()
    {
        $inactiveBus = Bus::factory()->create(['status' => 'maintenance']);

        $result = ScheduleConflictService::validateSchedule(
            $this->route->id,
            $inactiveBus->id,
            $this->driver->id,
            '08:00',
            30
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('maintenance', strtolower($result['message']));
    }

    /** @test */
    public function test_schedule_conflict_service_detects_suspended_driver()
    {
        $suspendedDriver = Driver::factory()->create(['status' => 'suspended']);

        $result = ScheduleConflictService::validateSchedule(
            $this->route->id,
            $this->bus->id,
            $suspendedDriver->id,
            '08:00',
            30
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('suspended', strtolower($result['message']));
    }

    /** @test */
    public function test_schedule_conflict_service_detects_expired_license()
    {
        $expiredDriver = Driver::factory()->create([
            'status' => 'active',
            'license_expiry' => Carbon::yesterday(),
        ]);

        $result = ScheduleConflictService::validateSchedule(
            $this->route->id,
            $this->bus->id,
            $expiredDriver->id,
            '08:00',
            30
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('expired', strtolower($result['message']));
    }

    /** @test */
    public function test_schedule_conflict_service_allows_next_day_trips()
    {
        Schedule::factory()->create([
            'driver_id' => $this->driver->id,
            'departure_time' => '08:00',
            'arrival_time' => '08:30',
            'status' => 'On time',
        ]);

        $bus2 = Bus::factory()->create(['status' => 'inactive', 'capacity' => 50]);
        $result = ScheduleConflictService::validateSchedule(
            $this->route->id,
            $bus2->id,
            $this->driver->id,
            '17:00',
            30
        );

        $this->assertTrue($result['valid'], "Expected valid schedule but got: {$result['message']}");
    }

    // ============================================================
    // DriverPerformanceService Tests
    // ============================================================

    /** @test */
    public function test_driver_performance_recalculates_score()
    {
        for ($i = 0; $i < 8; $i++) {
            Schedule::factory()->create([
                'driver_id' => $this->driver->id,
                'status' => 'On time',
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            Schedule::factory()->create([
                'driver_id' => $this->driver->id,
                'status' => 'delayed',
            ]);
        }

        $score = DriverPerformanceService::recalculate($this->driver->id);

        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
        $this->assertGreaterThan(50, $score);

        $this->driver->refresh();
        $this->assertEquals($score, $this->driver->performance_score);
    }

    /** @test */
    public function test_driver_performance_gets_summary()
    {
        Schedule::factory(5)->create([
            'driver_id' => $this->driver->id,
            'status' => 'On time',
        ]);

        $summary = DriverPerformanceService::getSummary($this->driver->id);

        $this->assertArrayHasKey('driver_id', $summary);
        $this->assertArrayHasKey('performance_score', $summary);
        $this->assertArrayHasKey('performance_grade', $summary);
        $this->assertArrayHasKey('last_30_days', $summary);
        $this->assertEquals(5, $summary['last_30_days']['total_schedules']);
    }

    /** @test */
    public function test_driver_performance_grades()
    {
        $this->assertEquals('A', DriverPerformanceService::getPerformanceGrade(95));
        $this->assertEquals('B', DriverPerformanceService::getPerformanceGrade(85));
        $this->assertEquals('C', DriverPerformanceService::getPerformanceGrade(75));
        $this->assertEquals('D', DriverPerformanceService::getPerformanceGrade(65));
        $this->assertEquals('F', DriverPerformanceService::getPerformanceGrade(50));
    }

    /** @test */
    public function test_driver_performance_registers_incident()
    {
        $this->driver->update(['incidents_30' => 0]);

        DriverPerformanceService::registerIncident($this->driver->id, 'accident', 'Minor collision');

        $this->driver->refresh();
        $this->assertEquals(1, $this->driver->incidents_30);
    }

    /** @test */
    public function test_driver_performance_gets_top_performers()
    {
        $highScoreDriver = Driver::factory()->create(['performance_score' => 95]);
        $lowScoreDriver = Driver::factory()->create(['performance_score' => 50]);

        $topPerformers = DriverPerformanceService::getTopPerformers(5);

        $this->assertGreaterThan(0, count($topPerformers));
        $this->assertGreaterThanOrEqual($topPerformers[1]['score'] ?? 0, $topPerformers[0]['score']);
    }

    // ============================================================
    // TripLog Model Tests
    // ============================================================

    /** @test */
    public function test_trip_log_model_creates_record()
    {
        $trip = Trip::factory()->create();
        $tripLog = TripLog::create([
            'driver_id' => $this->driver->id,
            'trip_id' => $trip->id,
            'bus_id' => $this->bus->id,
            'route_id' => $this->route->id,
            'started_at' => Carbon::now()->subHours(1),
            'completed_at' => Carbon::now(),
            'passengers' => 45,
            'peak_passengers' => 45,
            'status' => 'completed',
            'is_on_time' => true,
        ]);

        $this->assertDatabaseHas('trip_logs', [
            'driver_id' => $this->driver->id,
            'bus_id' => $this->bus->id,
        ]);
    }

    /** @test */
    public function test_trip_log_calculates_duration()
    {
        $trip = Trip::factory()->create();
        $started = Carbon::now();
        $completed = $started->copy()->addMinutes(30);
        
        $tripLog = TripLog::create([
            'driver_id' => $this->driver->id,
            'trip_id' => $trip->id,
            'bus_id' => $this->bus->id,
            'route_id' => $this->route->id,
            'started_at' => $started,
            'completed_at' => $completed,
            'passengers' => 30,
            'peak_passengers' => 30,
        ]);

        $this->assertEquals(30, $tripLog->trip_duration_minutes);
    }

    /** @test */
    public function test_trip_log_calculates_occupancy_rate()
    {
        $trip = Trip::factory()->create();
        $tripLog = TripLog::create([
            'driver_id' => $this->driver->id,
            'trip_id' => $trip->id,
            'bus_id' => $this->bus->id,
            'route_id' => $this->route->id,
            'started_at' => Carbon::now()->subHours(1),
            'completed_at' => Carbon::now(),
            'peak_passengers' => 25,
        ]);

        $this->assertEquals(50.0, $tripLog->occupancy_rate);
    }

    /** @test */
    public function test_trip_log_scopes_query()
    {
        $trip1 = Trip::factory()->create();
        TripLog::create([
            'driver_id' => $this->driver->id,
            'trip_id' => $trip1->id,
            'bus_id' => $this->bus->id,
            'route_id' => $this->route->id,
            'started_at' => Carbon::now()->subHours(1),
            'completed_at' => Carbon::now(),
            'peak_passengers' => 30,
        ]);

        $otherDriver = Driver::factory()->create();
        $trip2 = Trip::factory()->create();
        TripLog::create([
            'driver_id' => $otherDriver->id,
            'trip_id' => $trip2->id,
            'bus_id' => $this->bus->id,
            'route_id' => $this->route->id,
            'started_at' => Carbon::now()->subHours(2),
            'completed_at' => Carbon::now()->subHours(1),
            'peak_passengers' => 30,
        ]);

        $driverTrips = TripLog::forDriver($this->driver->id)->get();
        $this->assertEquals(1, $driverTrips->count());

        $routeTrips = TripLog::forRoute($this->route->id)->get();
        $this->assertEquals(2, $routeTrips->count());
    }

    /** @test */
    public function test_trip_log_gets_driver_stats()
    {
        for ($i = 0; $i < 5; $i++) {
            $trip = Trip::factory()->create();
            TripLog::create([
                'driver_id' => $this->driver->id,
                'trip_id' => $trip->id,
                'bus_id' => $this->bus->id,
                'route_id' => $this->route->id,
                'started_at' => Carbon::now()->subHours($i + 1),
                'completed_at' => Carbon::now()->subHours($i),
                'peak_passengers' => 30 + $i,
                'is_on_time' => true,
            ]);
        }

        $stats = TripLog::getDriverStats($this->driver->id);

        $this->assertEquals(5, $stats['total_trips']);
        $this->assertGreaterThan(0, $stats['total_passengers']);
    }
}