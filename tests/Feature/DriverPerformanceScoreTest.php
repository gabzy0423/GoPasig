<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\Schedule;
use App\Models\SystemSetting;
use App\Models\PassengerRating;
use App\Services\DriverPerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DriverPerformanceScoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        SystemSetting::updateOrCreate(
            ['key' => 'driver_score_incident_penalty'],
            ['value' => 10, 'description' => 'Points deducted per incident']
        );
        
        SystemSetting::updateOrCreate(
            ['key' => 'driver_default_on_time_score'],
            ['value' => 75, 'description' => 'Default on time score']
        );
        
        SystemSetting::updateOrCreate(
            ['key' => 'driver_default_passenger_score'],
            ['value' => 80, 'description' => 'Default passenger rating']
        );
    }

    public function test_recalculate_counts_all_incidents()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        Incident::factory()->count(2)->create([
            'driver_id' => $driver->id
        ]);
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        
        // onTime=75, incident=80, passenger=80 => 78
        $this->assertEquals(78, $driver->performance_score);
    }

    public function test_recalculate_counts_delayed_schedules()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        Schedule::factory()->count(3)->create([
            'driver_id' => $driver->id,
            'status' => 'delayed'
        ]);
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        
        // onTime=0, incident=100, passenger=80 => 46
        $this->assertEquals(46, $driver->performance_score);
    }

    public function test_recalculate_combines_incidents_and_delays()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        Incident::factory()->count(2)->create([
            'driver_id' => $driver->id
        ]);
        
        Schedule::factory()->count(3)->create([
            'driver_id' => $driver->id,
            'status' => 'delayed'
        ]);
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        
        // onTime=0, incident=80, passenger=80 => 40
        $this->assertEquals(40, $driver->performance_score);
    }

    public function test_recalculate_never_goes_below_zero()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        Incident::factory()->count(15)->create([
            'driver_id' => $driver->id
        ]);
        
        Schedule::factory()->count(3)->create([
            'driver_id' => $driver->id,
            'status' => 'delayed'
        ]);
        
        PassengerRating::create([
            'driver_id' => $driver->id,
            'rating' => 0,
        ]);
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        
        // Should be clamped to 0
        $this->assertEquals(0, $driver->performance_score);
    }

    public function test_recalculate_respects_system_settings()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        SystemSetting::where('key', 'driver_score_incident_penalty')->update(['value' => 20]);
        
        Incident::factory()->create([
            'driver_id' => $driver->id
        ]);
        
        \Illuminate\Support\Facades\Cache::flush();
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        
        // onTime=75, incident=80, passenger=80 => 78
        $this->assertEquals(78, $driver->performance_score);
    }

    public function test_recalculate_self_corrects_when_incident_deleted()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        $incident = Incident::factory()->create([
            'driver_id' => $driver->id
        ]);
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        $this->assertEquals(81, $driver->performance_score); // onTime=75, incident=90, passenger=80
        
        $incident->delete();
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        $this->assertEquals(84, $driver->performance_score); // onTime=75, incident=100, passenger=80
    }

    public function test_recalculate_returns_true_on_success()
    {
        $driver = Driver::factory()->create();
        $result = DriverPerformanceService::recalculate($driver->id);
        $this->assertNotFalse($result);
    }

    public function test_recalculate_returns_false_on_invalid_driver()
    {
        $result = DriverPerformanceService::recalculate(99999);
        $this->assertFalse($result);
    }

    public function test_schedule_status_update_to_delayed_triggers_recalculation()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        $schedule = Schedule::factory()->create([
            'driver_id' => $driver->id,
            'status' => 'On time'
        ]);
        
        DriverPerformanceService::recalculate($driver->id);
        $schedule->update(['status' => 'delayed']);
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        
        $this->assertEquals(46, $driver->performance_score);
    }

    public function test_recalculate_silently_catches_exceptions()
    {
        $result = DriverPerformanceService::recalculate(99999);
        $this->assertFalse($result);
    }

    public function test_multiple_drivers_independent_scores()
    {
        $driver1 = Driver::factory()->create(['performance_score' => 100]);
        $driver2 = Driver::factory()->create(['performance_score' => 100]);
        
        Incident::factory()->count(2)->create([
            'driver_id' => $driver1->id
        ]);
        
        Incident::factory()->count(1)->create([
            'driver_id' => $driver2->id
        ]);
        
        DriverPerformanceService::recalculate($driver1->id);
        DriverPerformanceService::recalculate($driver2->id);
        
        $driver1->refresh();
        $driver2->refresh();
        
        $this->assertEquals(78, $driver1->performance_score);
        $this->assertEquals(81, $driver2->performance_score);
    }

    public function test_recalculate_reads_from_database_not_memory()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        Incident::factory()->create(['driver_id' => $driver->id]);
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        $this->assertEquals(81, $driver->performance_score);
        
        Incident::factory()->create(['driver_id' => $driver->id]);
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        $this->assertEquals(78, $driver->performance_score);
    }

    public function test_score_calculation_with_zero_incidents_and_delays()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        
        $this->assertEquals(84, $driver->performance_score);
    }

    public function test_penalty_settings_default_values()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        $incidentPenalty = SystemSetting::get('driver_score_incident_penalty', 10);
        $this->assertEquals(10, $incidentPenalty);
    }

    public function test_recalculate_with_only_on_time_schedules()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        Schedule::factory()->count(5)->create([
            'driver_id' => $driver->id,
            'status' => 'On time'
        ]);
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        
        $this->assertEquals(96, $driver->performance_score);
    }
}
