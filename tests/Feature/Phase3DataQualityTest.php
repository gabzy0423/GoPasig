<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Bus;
use App\Models\BusStatusAuditLog;
use App\Models\OrphanedRecordsLog;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\ServiceAlert;
use App\Services\ValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class Phase3DataQualityTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // GPS Coordinate Validation Tests
    // ============================================================

    /** @test */
    public function test_valid_philippines_gps_coordinates()
    {
        // Manila coordinates
        $result = ValidationService::validateGPSCoordinates(14.5995, 120.9842);
        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function test_invalid_gps_latitude_too_low()
    {
        $result = ValidationService::validateGPSCoordinates(3.0, 120.0);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Latitude', $result['message']);
    }

    /** @test */
    public function test_invalid_gps_latitude_too_high()
    {
        $result = ValidationService::validateGPSCoordinates(21.5, 120.0);
        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function test_invalid_gps_longitude_too_low()
    {
        $result = ValidationService::validateGPSCoordinates(14.5, 115.0);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Longitude', $result['message']);
    }

    /** @test */
    public function test_invalid_gps_longitude_too_high()
    {
        $result = ValidationService::validateGPSCoordinates(14.5, 128.0);
        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function test_invalid_gps_nan_coordinates()
    {
        $result = ValidationService::validateGPSCoordinates(NAN, 120.0);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('out of bounds', strtolower($result['message']));
    }

    /** @test */
    public function test_invalid_gps_infinity_coordinates()
    {
        $result = ValidationService::validateGPSCoordinates(14.5, INF);
        $this->assertFalse($result['valid']);
    }

    // ============================================================
    // Polyline Geometry Validation Tests
    // ============================================================

    /** @test */
    public function test_valid_polyline_geometry()
    {
        $coordinates = [
            [14.5995, 120.9842],
            [14.6063, 120.9869],
            [14.6123, 120.9905],
        ];

        $result = ValidationService::validatePolylineGeometry($coordinates);
        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function test_empty_polyline_coordinates()
    {
        $result = ValidationService::validatePolylineGeometry([]);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('empty', strtolower($result['message']));
    }

    /** @test */
    public function test_polyline_with_single_coordinate()
    {
        $coordinates = [[14.5995, 120.9842]];

        $result = ValidationService::validatePolylineGeometry($coordinates);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('at least 2', $result['message']);
    }

    /** @test */
    public function test_polyline_with_invalid_coordinate_format()
    {
        $coordinates = [
            [14.5995, 120.9842],
            [14.6063], // Missing longitude
        ];

        $result = ValidationService::validatePolylineGeometry($coordinates);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['invalid_coords']);
    }

    /** @test */
    public function test_polyline_with_out_of_bounds_coordinates()
    {
        $coordinates = [
            [14.5995, 120.9842],
            [3.0, 115.0], // Out of bounds
        ];

        $result = ValidationService::validatePolylineGeometry($coordinates);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['invalid_coords']);
    }

    /** @test */
    public function test_polyline_with_excessive_distance_jump()
    {
        // Two coordinates 1000km apart (unrealistic for a bus route)
        $coordinates = [
            [14.5995, 120.9842], // Manila
            [18.0, 124.0], // Far away within Philippines but unrealistic
        ];

        $result = ValidationService::validatePolylineGeometry($coordinates);
        $this->assertFalse($result['valid']);
    }

    // ============================================================
    // Schedule Time Validation Tests
    // ============================================================

    /** @test */
    public function test_valid_schedule_time_format()
    {
        $result = ValidationService::validateScheduleTime('08:00');
        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function test_valid_schedule_time_edge_cases()
    {
        // Midnight
        $result = ValidationService::validateScheduleTime('00:00');
        $this->assertTrue($result['valid']);

        // 23:59
        $result = ValidationService::validateScheduleTime('23:59');
        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function test_invalid_schedule_time_format_missing_colon()
    {
        $result = ValidationService::validateScheduleTime('0800');
        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function test_invalid_schedule_time_invalid_hour()
    {
        $result = ValidationService::validateScheduleTime('25:00');
        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function test_invalid_schedule_time_invalid_minute()
    {
        $result = ValidationService::validateScheduleTime('08:60');
        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function test_valid_schedule_time_range()
    {
        $result = ValidationService::validateScheduleTimeRange('08:00', '09:30');
        $this->assertTrue($result['valid']);
        $this->assertEquals(90, $result['duration_minutes']);
    }

    /** @test */
    public function test_invalid_schedule_time_range_too_short()
    {
        $result = ValidationService::validateScheduleTimeRange('08:00', '08:02');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('too short', $result['message']);
    }

    /** @test */
    public function test_invalid_schedule_time_range_too_long()
    {
        $result = ValidationService::validateScheduleTimeRange('08:00', '21:00');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('too long', $result['message']);
    }

    /** @test */
    public function test_valid_overnight_schedule_time_range()
    {
        // Overnight trip: 23:00 to 02:00 (next day)
        $result = ValidationService::validateScheduleTimeRange('23:00', '02:00');
        $this->assertTrue($result['valid']);
    }

    // ============================================================
    // Service Alert XSS Protection Tests
    // ============================================================

    /** @test */
    public function test_valid_service_alert_message()
    {
        $message = 'Route 1 delayed due to traffic';

        $result = ValidationService::validateServiceAlertMessage($message);
        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function test_empty_service_alert_message()
    {
        $result = ValidationService::validateServiceAlertMessage('   ');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('empty', strtolower($result['message']));
    }

    /** @test */
    public function test_service_alert_message_exceeds_max_length()
    {
        $message = str_repeat('a', 501);

        $result = ValidationService::validateServiceAlertMessage($message);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('exceeds', $result['message']);
    }

    /** @test */
    public function test_service_alert_xss_script_injection()
    {
        $message = 'Route 1 delayed <script>alert("xss")</script>';

        $result = ValidationService::validateServiceAlertMessage($message);
        $this->assertTrue($result['valid']);
        $this->assertStringNotContainsString('<script>', $result['sanitized']);
    }

    /** @test */
    public function test_service_alert_xss_onclick_injection()
    {
        $message = 'Click here <span onclick="alert(\'xss\')">now</span>';

        $result = ValidationService::validateServiceAlertMessage($message);
        $this->assertTrue($result['valid']);
        $this->assertStringNotContainsString('onclick', $result['sanitized']);
    }

    /** @test */
    public function test_service_alert_xss_iframe_injection()
    {
        $message = 'Check this: <iframe src="malicious.com"></iframe>';

        $result = ValidationService::validateServiceAlertMessage($message);
        $this->assertTrue($result['valid']);
        $this->assertStringNotContainsString('<iframe>', $result['sanitized']);
    }

    /** @test */
    public function test_service_alert_xss_javascript_protocol()
    {
        $message = '<a href="javascript:alert(\'xss\')">Click</a>';

        $result = ValidationService::validateServiceAlertMessage($message);
        $this->assertTrue($result['valid']);
        $this->assertStringNotContainsString('javascript:', $result['sanitized']);
    }

    // ============================================================
    // Data Consistency Tests
    // ============================================================

    /** @test */
    public function test_bus_status_audit_log_created()
    {
        $bus = Bus::factory()->create(['status' => 'active']);
        $user = \App\Models\User::factory()->create();

        BusStatusAuditLog::logStatusChange(
            $bus->id,
            'maintenance',
            'active',
            userId: $user->id,
            reason: 'Scheduled maintenance'
        );

        $this->assertDatabaseHas('bus_status_audit_log', [
            'bus_id' => $bus->id,
            'old_status' => 'active',
            'new_status' => 'maintenance',
        ]);
    }

    /** @test */
    public function test_bus_status_audit_log_history()
    {
        $bus = Bus::factory()->create(['status' => 'active']);

        BusStatusAuditLog::logStatusChange($bus->id, 'maintenance', 'active');
        BusStatusAuditLog::logStatusChange($bus->id, 'active', 'maintenance');

        $history = BusStatusAuditLog::getHistoryForBus($bus->id);
        $this->assertEquals(2, $history->count());
    }

    /** @test */
    public function test_orphaned_records_detection()
    {
        OrphanedRecordsLog::logOrphanedRecord(
            'schedules',
            1,
            'bus_id',
            999,
            'Bus ID 999 does not exist'
        );

        $this->assertDatabaseHas('orphaned_records_log', [
            'table_name' => 'schedules',
            'record_id' => 1,
            'foreign_key_name' => 'bus_id',
            'missing_foreign_id' => 999,
            'resolution_status' => 'pending',
        ]);
    }

    /** @test */
    public function test_orphaned_records_resolution()
    {
        $log = OrphanedRecordsLog::logOrphanedRecord(
            'schedules',
            1,
            'bus_id',
            999
        );

        $log->markResolved('deleted', 'Orphaned schedule deleted');

        $this->assertDatabaseHas('orphaned_records_log', [
            'id' => $log->id,
            'resolution_status' => 'deleted',
            'resolution_notes' => 'Orphaned schedule deleted',
        ]);
    }

    // ============================================================
    // Plate Number Validation Tests
    // ============================================================

    /** @test */
    public function test_valid_plate_number_format()
    {
        $result = ValidationService::validatePlateNumber('PAS-825');
        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function test_valid_plate_number_four_digits()
    {
        $result = ValidationService::validatePlateNumber('MAN-1234');
        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function test_invalid_plate_number_format()
    {
        $result = ValidationService::validatePlateNumber('PASS-825');
        $this->assertFalse($result['valid']);
    }

    // ============================================================
    // Passenger Count Validation Tests
    // ============================================================

    /** @test */
    public function test_valid_passenger_count()
    {
        $result = ValidationService::validatePassengerCount(30, 50);
        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function test_invalid_passenger_count_negative()
    {
        $result = ValidationService::validatePassengerCount(-5, 50);
        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function test_invalid_passenger_count_exceeds_capacity()
    {
        $result = ValidationService::validatePassengerCount(100, 50);
        $this->assertFalse($result['valid']);
    }

    // ============================================================
    // Stop Sequence Validation Tests
    // ============================================================

    /** @test */
    public function test_valid_stop_sequence()
    {
        $stops = [
            (object)['sequence' => 1],
            (object)['sequence' => 2],
            (object)['sequence' => 3],
        ];

        $result = ValidationService::validateStopSequence($stops);
        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function test_invalid_stop_sequence_with_gaps()
    {
        $stops = [
            (object)['sequence' => 1],
            (object)['sequence' => 3], // Gap at 2
            (object)['sequence' => 4],
        ];

        $result = ValidationService::validateStopSequence($stops);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['gaps']);
    }
}
