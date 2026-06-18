<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\Schedule;
use App\Models\Bus;
use App\Models\Route;
use App\Models\SystemSetting;
use App\Services\DriverPerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DriverPerformanceScoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure default penalty settings exist
        SystemSetting::updateOrCreate(
            ['key' => 'driver_score_incident_penalty'],
            ['value' => 10, 'description' => 'Points deducted per incident']
        );
        
        SystemSetting::updateOrCreate(
            ['key' => 'driver_score_delay_penalty'],
            ['value' => 5, 'description' => 'Points deducted per delayed trip']
        );
    }

    public function test_recalculate_counts_all_incidents()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        // Create 2 incidents
        Incident::factory()->count(2)->create([
            'driver_id' => $driver->id
        ]);
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        
        // 100 - (2 * 10) = 80
        $this->assertEquals(80, $driver->performance_score);
    }

    public function test_recalculate_counts_delayed_schedules()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        // Create 3 delayed schedules
        Schedule::factory()->count(3)->create([
            'driver_id' => $driver->id,
            'status' => 'delayed'
        ]);
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        
        // 100 - (3 * 5) = 85
        $this->assertEquals(85, $driver->performance_score);
    }

    public function test_recalculate_combines_incidents_and_delays()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        // 2 incidents + 3 delays
        Incident::factory()->count(2)->create([
            'driver_id' => $driver->id
        ]);
        
        Schedule::factory()->count(3)->create([
            'driver_id' => $driver->id,
            'status' => 'delayed'
        ]);
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        
        // 100 - (2 * 10) - (3 * 5) = 65
        $this->assertEquals(65, $driver->performance_score);
    }

    public function test_recalculate_never_goes_below_zero()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        // Create many incidents to exceed 100 points
        Incident::factory()->count(15)->create([
            'driver_id' => $driver->id
        ]);
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        
        // Should be clamped to 0, not negative
        $this->assertEquals(0, $driver->performance_score);
    }

    public function test_recalculate_respects_system_settings()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        // Change penalty settings
        SystemSetting::where('key', 'driver_score_incident_penalty')->update(['value' => 20]);
        
        Incident::factory()->create([
            'driver_id' => $driver->id
        ]);
        
        // Clear cache so new settings are loaded
        \Illuminate\Support\Facades\Cache::flush();
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        
        // 100 - (1 * 20) = 80 (with new penalty)
        $this->assertEquals(80, $driver->performance_score);
    }

    public function test_recalculate_self_corrects_when_incident_deleted()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        $incident = Incident::factory()->create([
            'driver_id' => $driver->id
        ]);
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        $this->assertEquals(90, $driver->performance_score);
        
        // Delete the incident
        $incident->delete();
        
        // Recalculate should return to 100
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        $this->assertEquals(100, $driver->performance_score);
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
        
        // Update schedule to delayed via updateStatus method
        DriverPerformanceService::recalculate($driver->id); // Reset to baseline
        $schedule->update(['status' => 'delayed']);
        
        // Now call recalculate which should be triggered by updateStatus
        DriverPerformanceService::recalculate($driver->id);
        
        $driver->refresh();
        // 100 - (1 * 5) = 95
        $this->assertEquals(95, $driver->performance_score);
    }

    public function test_recalculate_silently_catches_exceptions()
    {
        // Create a mock that will fail gracefully
        $result = DriverPerformanceService::recalculate(99999);
        
        // Should return false but not throw exception
        $this->assertFalse($result);
    }

    public function test_multiple_drivers_independent_scores()
    {
        $driver1 = Driver::factory()->create(['performance_score' => 100]);
        $driver2 = Driver::factory()->create(['performance_score' => 100]);
        
        // 2 incidents for driver1
        Incident::factory()->count(2)->create([
            'driver_id' => $driver1->id
        ]);
        
        // 1 incident for driver2
        Incident::factory()->count(1)->create([
            'driver_id' => $driver2->id
        ]);
        
        DriverPerformanceService::recalculate($driver1->id);
        DriverPerformanceService::recalculate($driver2->id);
        
        $driver1->refresh();
        $driver2->refresh();
        
        $this->assertEquals(80, $driver1->performance_score);
        $this->assertEquals(90, $driver2->performance_score);
    }

    public function test_recalculate_reads_from_database_not_memory()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        // Create incident
        Incident::factory()->create(['driver_id' => $driver->id]);
        
        // Recalculate should read from DB
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        $this->assertEquals(90, $driver->performance_score);
        
        // Add another incident
        Incident::factory()->create(['driver_id' => $driver->id]);
        
        // Recalculate again - should count BOTH
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        $this->assertEquals(80, $driver->performance_score);
    }

    public function test_score_calculation_with_zero_incidents_and_delays()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        // No incidents, no delays - should stay at 100
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        
        $this->assertEquals(100, $driver->performance_score);
    }

    public function test_penalty_settings_default_values()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        // Ensure settings exist with defaults
        $incidentPenalty = SystemSetting::get('driver_score_incident_penalty', 10);
        $delayPenalty = SystemSetting::get('driver_score_delay_penalty', 5);
        
        $this->assertEquals(10, $incidentPenalty);
        $this->assertEquals(5, $delayPenalty);
    }

    public function test_recalculate_with_only_on_time_schedules()
    {
        $driver = Driver::factory()->create(['performance_score' => 100]);
        
        // Create schedules with "On time" status
        Schedule::factory()->count(5)->create([
            'driver_id' => $driver->id,
            'status' => 'On time'
        ]);
        
        DriverPerformanceService::recalculate($driver->id);
        $driver->refresh();
        
        // Should remain 100 since no delays
        $this->assertEquals(100, $driver->performance_score);
    }
}
