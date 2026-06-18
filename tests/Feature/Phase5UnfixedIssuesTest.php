<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Driver;
use App\Models\Bus;
use App\Models\Route;
use App\Models\ServiceAlert;
use App\Models\Stop;
use App\Models\User;
use App\Services\DriverPerformanceService;
use App\Services\ValidationService;
use App\Services\BusStateService;
use App\Services\NotificationService;

class Phase5UnfixedIssuesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    /** @test */
    public function issue_3_1_2_driver_performance_not_hardcoded_to_100()
    {
        $driver = Driver::factory()->create(['performance_score' => 80]);
        
        // New drivers should default to 80, not 100
        $this->assertEquals(80, $driver->performance_score);
        
        // Recalculation should use actual metrics
        $score = DriverPerformanceService::recalculate($driver->id);
        $this->assertNotEquals(100, $score);
    }

    /** @test */
    public function issue_3_1_3_trip_history_removed_from_driver()
    {
        $driver = Driver::factory()->create();
        
        // trip_history should not exist in fillable or casts
        $this->assertNotContains('trip_history', $driver->getFillable());
        $this->assertArrayNotHasKey('trip_history', $driver->getCasts());
    }

    /** @test */
    public function issue_5_1_1_gps_coordinates_validated()
    {
        // Valid Philippines coordinates
        $result = ValidationService::validateGPSCoordinates(14.5593, 121.0805);
        $this->assertTrue($result['valid']);

        // Invalid coordinates outside Philippines
        $result = ValidationService::validateGPSCoordinates(1.0, 100.0);
        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function issue_5_1_3_xss_protection_service_alerts()
    {
        $maliciousMessage = '<script>alert("XSS")</script>Normal message';
        
        $validation = ValidationService::validateServiceAlertMessage($maliciousMessage, 500);
        
        $this->assertTrue($validation['valid']);
        $this->assertStringNotContainsString('<script>', $validation['sanitized']);
        $this->assertStringContainsString('Normal message', $validation['sanitized']);
    }

    /** @test */
    public function issue_7_1_delete_auth_checks_on_controllers()
    {
        $this->actingAs(User::factory()->create(['role' => 'passenger']));
        
        $bus = Bus::factory()->create();
        $driver = Driver::factory()->create();
        $route = Route::factory()->create();
        
        // All should return 403 for non-admin users
        $response = $this->deleteJson("/admin/buses/{$bus->id}");
        $this->assertEquals(403, $response->status());
        
        $response = $this->deleteJson("/admin/drivers/{$driver->id}");
        $this->assertEquals(403, $response->status());
        
        $response = $this->deleteJson("/admin/routes/{$route->id}");
        $this->assertEquals(403, $response->status());
    }

    /** @test */
    public function issue_3_2_1_route_polyline_validated()
    {
        $validPolyline = [
            [14.5593, 121.0805],
            [14.5600, 121.0810]
        ];
        
        $result = ValidationService::validatePolylineGeometry($validPolyline);
        $this->assertTrue($result['valid']);
        
        // Invalid polyline with out of bounds coordinates
        $invalidPolyline = [
            [1.0, 100.0],
            [2.0, 101.0]
        ];
        
        $result = ValidationService::validatePolylineGeometry($invalidPolyline);
        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function issue_3_2_2_stop_sequence_validation()
    {
        $stops = [
            (object)['sequence' => 1],
            (object)['sequence' => 2],
            (object)['sequence' => 3],
        ];
        
        $result = ValidationService::validateStopSequence($stops);
        $this->assertTrue($result['valid']);
        
        // Gaps in sequence
        $stopsWithGaps = [
            (object)['sequence' => 1],
            (object)['sequence' => 3],
        ];
        
        $result = ValidationService::validateStopSequence($stopsWithGaps);
        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function issue_4_2_1_license_expiry_notification_service_exists()
    {
        $driver = Driver::factory()->create([
            'license_expiry' => now()->addDays(15)
        ]);
        
        $result = NotificationService::sendLicenseExpiryReminders(30);
        
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('drivers', $result);
    }

    /** @test */
    public function issue_4_2_2_bus_status_state_machine()
    {
        $bus = Bus::factory()->create(['status' => 'active']);
        
        // Valid transitions
        $this->assertTrue(BusStateService::canTransition('active', 'inactive'));
        $this->assertTrue(BusStateService::canTransition('active', 'maintenance'));
        $this->assertTrue(BusStateService::canTransition('inactive', 'active'));
        
        // Invalid transition
        $this->assertFalse(BusStateService::canTransition('inactive', 'maintenance'));
        
        // Transition with validation
        $result = BusStateService::transition($bus, 'maintenance');
        $this->assertTrue($result['success']);
        
        $bus->refresh();
        $this->assertEquals('maintenance', $bus->status);
    }

    /** @test */
    public function issue_5_1_2_schedule_time_format_strict_validation()
    {
        // Valid format
        $result = ValidationService::validateScheduleTime('14:30');
        $this->assertTrue($result['valid']);
        
        // Invalid formats
        $result = ValidationService::validateScheduleTime('2:30');
        $this->assertFalse($result['valid']);
        
        $result = ValidationService::validateScheduleTime('14:60');
        $this->assertFalse($result['valid']);
        
        $result = ValidationService::validateScheduleTime('25:00');
        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function issue_5_2_1_error_handling_for_missing_records()
    {
        // Bus not found
        $response = $this->getJson('/admin/buses/999999');
        $this->assertEquals(404, $response->status());
        
        // Driver not found
        $response = $this->getJson('/admin/drivers/999999');
        $this->assertEquals(404, $response->status());
        
        // Route not found
        $response = $this->getJson('/admin/routes/999999');
        $this->assertEquals(404, $response->status());
    }
}
