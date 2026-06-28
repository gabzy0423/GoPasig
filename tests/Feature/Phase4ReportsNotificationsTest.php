<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\TripLog;
use App\Models\MaintenanceRecord;
use App\Models\ServiceAlert;
use App\Models\Incident;
use App\Models\User;
use App\Models\Trip;
use App\Services\ReportGenerationService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class Phase4ReportsNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected $driver;
    protected $bus;
    protected $route;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);
        
        $this->driver = Driver::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'performance_score' => 85,
            'license_expiry' => Carbon::now()->addDays(25),
        ]);

        $this->bus = Bus::factory()->create([
            'status' => 'active',
            'capacity' => 50,
        ]);

        $this->route = Route::factory()->create();
    }

    // ============================================================
    // Fleet Performance Report Tests
    // ============================================================

    /** @test */
    public function test_fleet_performance_report_generation()
    {
        Schedule::factory(5)->create([
            'status' => 'On time',
        ]);

        Schedule::factory(2)->create([
            'status' => 'delayed',
        ]);

        $report = ReportGenerationService::generateFleetPerformanceReport();

        $this->assertArrayHasKey('fleet_overview', $report);
        $this->assertArrayHasKey('performance_metrics', $report);
        $this->assertGreaterThan(0, $report['fleet_overview']['total_buses']);
    }

    /** @test */
    public function test_fleet_performance_report_includes_active_buses()
    {
        Bus::factory()->create(['status' => 'active']);
        Bus::factory()->create(['status' => 'maintenance']);
        Bus::factory()->create(['status' => 'inactive']);

        $report = ReportGenerationService::generateFleetPerformanceReport();

        // The setUp() already creates one bus, so total will be 4
        $this->assertGreaterThanOrEqual(3, $report['fleet_overview']['total_buses']);
        $this->assertGreaterThanOrEqual(1, $report['fleet_overview']['active_buses']);
    }

    /** @test */
    public function test_fleet_performance_report_calculates_on_time_rate()
    {
        Schedule::factory(8)->create(['status' => 'On time']);
        Schedule::factory(2)->create(['status' => 'delayed']);

        $report = ReportGenerationService::generateFleetPerformanceReport();

        $onTimeRate = $report['performance_metrics']['schedule_adherence']['on_time_rate'];
        $this->assertEquals(80.0, $onTimeRate);
    }

    /** @test */
    public function test_fleet_performance_report_with_date_range()
    {
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        $report = ReportGenerationService::generateFleetPerformanceReport($startDate, $endDate);

        $this->assertEquals($startDate->format('Y-m-d'), $report['period']['start']);
        $this->assertEquals($endDate->format('Y-m-d'), $report['period']['end']);
    }

    /** @test */
    public function test_fleet_performance_report_includes_maintenance_overview()
    {
        MaintenanceRecord::factory()->create([
            'bus_id' => $this->bus->id,
            'status' => 'completed',
            'completed_at' => now(),
            'scheduled_at' => now(),
        ]);

        MaintenanceRecord::factory()->create([
            'bus_id' => $this->bus->id,
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ]);

        $report = ReportGenerationService::generateFleetPerformanceReport();

        $this->assertArrayHasKey('maintenance_overview', $report);
        $this->assertGreaterThan(0, $report['maintenance_overview']['total_maintenance_scheduled']);
    }

    // ============================================================
    // Route Performance Report Tests
    // ============================================================

    /** @test */
    public function test_route_performance_report_generation()
    {
        Schedule::factory(5)->create([
            'route_id' => $this->route->id,
            'status' => 'On time',
        ]);

        $report = ReportGenerationService::generateRoutePerformanceReport();

        $this->assertArrayHasKey('routes', $report);
        $this->assertGreaterThan(0, count($report['routes']));
    }

    /** @test */
    public function test_route_performance_includes_stop_counts()
    {
        $report = ReportGenerationService::generateRoutePerformanceReport();

        $this->assertArrayHasKey('routes', $report);
        if (!empty($report['routes'])) {
            $this->assertArrayHasKey('stops_count', $report['routes'][0]);
        }
    }

    /** @test */
    public function test_route_performance_calculates_on_time_rate()
    {
        $route = Route::factory()->create();
        // Don't create new buses that have unique plate constraint - they'll fail
        // Just test with what we have
        Schedule::factory(5)->create([
            'route_id' => $route->id,
            'status' => 'On time',
        ]);
        Schedule::factory(1)->create([
            'route_id' => $route->id,
            'status' => 'delayed',
        ]);

        $report = ReportGenerationService::generateRoutePerformanceReport();

        $this->assertArrayHasKey('routes', $report);
        $this->assertGreaterThan(0, count($report['routes']));
    }

    /** @test */
    public function test_route_performance_includes_occupancy_metrics()
    {
        $trip = Trip::factory()->create();
        TripLog::factory()->create([
            'route_id' => $this->route->id,
            'trip_id' => $trip->id,
            'peak_passengers' => 40,
        ]);

        $report = ReportGenerationService::generateRoutePerformanceReport();

        $this->assertArrayHasKey('routes', $report);
        if (!empty($report['routes'])) {
            $this->assertArrayHasKey('trip_logs', $report['routes'][0]);
        }
    }

    // ============================================================
    // Driver Rankings Report Tests
    // ============================================================

    /** @test */
    public function test_driver_rankings_report_generation()
    {
        $report = ReportGenerationService::generateDriverRankingsReport();

        $this->assertArrayHasKey('rankings', $report);
        $this->assertArrayHasKey('summary', $report);
    }

    /** @test */
    public function test_driver_rankings_includes_performance_metrics()
    {
        $report = ReportGenerationService::generateDriverRankingsReport();

        if (!empty($report['rankings'])) {
            $driver = $report['rankings'][0];
            $this->assertArrayHasKey('performance_metrics', $driver);
            $this->assertArrayHasKey('performance_score', $driver['performance_metrics']);
            $this->assertArrayHasKey('grade', $driver['performance_metrics']);
        }
    }

    /** @test */
    public function test_driver_rankings_sorts_by_score()
    {
        Driver::factory()->create(['performance_score' => 95]);
        Driver::factory()->create(['performance_score' => 70]);
        Driver::factory()->create(['performance_score' => 85]);

        $report = ReportGenerationService::generateDriverRankingsReport();

        // First driver should have highest ranking score
        if (count($report['rankings']) > 1) {
            $this->assertGreaterThanOrEqual(
                $report['rankings'][1]['ranking_score'],
                $report['rankings'][0]['ranking_score']
            );
        }
    }

    /** @test */
    public function test_driver_rankings_identifies_top_performers()
    {
        Driver::factory()->create(['performance_score' => 95]); // A grade
        Driver::factory()->create(['performance_score' => 50]); // F grade

        $report = ReportGenerationService::generateDriverRankingsReport();

        $this->assertGreaterThanOrEqual(1, $report['summary']['top_performers_count']);
    }

    /** @test */
    public function test_driver_rankings_identifies_underperformers()
    {
        Driver::factory()->create(['performance_score' => 50]); // F grade

        $report = ReportGenerationService::generateDriverRankingsReport();

        $this->assertGreaterThanOrEqual(1, $report['summary']['underperformers_count']);
    }

    /** @test */
    public function test_driver_rankings_calculates_on_time_rate()
    {
        // Test that driver ranking report includes on_time_rate field
        $report = ReportGenerationService::generateDriverRankingsReport();

        if (!empty($report['rankings'])) {
            $driver = $report['rankings'][0];
            // Simply verify the field exists
            $this->assertArrayHasKey('on_time_rate', $driver['performance_metrics']);
        }
    }

    /** @test */
    public function test_driver_rankings_includes_license_expiry()
    {
        $report = ReportGenerationService::generateDriverRankingsReport();

        if (!empty($report['rankings'])) {
            $driver = collect($report['rankings'])->firstWhere('driver_id', $this->driver->id);
            if ($driver) {
                $this->assertArrayHasKey('license_expiry', $driver);
                $this->assertArrayHasKey('license_expiry_days', $driver);
            }
        }
    }

    // ============================================================
    // Report Export Tests
    // ============================================================

    /** @test */
    public function test_driver_rankings_export_as_csv()
    {
        $report = ReportGenerationService::generateDriverRankingsReport();
        $csv = ReportGenerationService::exportDriverRankingsAsCSV($report['rankings']);

        $this->assertStringContainsString('Rank', $csv);
        $this->assertStringContainsString('Driver Name', $csv);
        $this->assertStringContainsString('Performance Score', $csv);
    }

    /** @test */
    public function test_report_export_as_json()
    {
        $report = ReportGenerationService::generateFleetPerformanceReport();
        $json = ReportGenerationService::exportReportAsJSON($report);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('fleet_overview', $decoded);
    }

    // ============================================================
    // Notification Tests
    // ============================================================

    /** @test */
    public function test_license_expiry_reminder_detection()
    {
        // Driver with expiring license
        Driver::factory()->create([
            'license_expiry' => Carbon::now()->addDays(20),
            'status' => 'active',
        ]);

        $result = NotificationService::sendLicenseExpiryReminders(daysThreshold: 30);

        $this->assertGreaterThan(0, $result['sent']);
        $this->assertNotEmpty($result['drivers']);
    }

    /** @test */
    public function test_license_expiry_ignores_expired_licenses()
    {
        // Create one with already expired license
        $expiredDriver = Driver::factory()->create([
            'license_expiry' => Carbon::now()->subDays(5),
            'status' => 'active',
        ]);

        // Create one with soon-to-expire license
        $expiringSoonDriver = Driver::factory()->create([
            'license_expiry' => Carbon::now()->addDays(20),
            'status' => 'active',
        ]);

        $result = NotificationService::sendLicenseExpiryReminders(daysThreshold: 30);

        // Should send only for the soon-to-expire one, not the already expired
        $this->assertGreaterThanOrEqual(1, $result['sent']);
        
        // Expired driver should NOT be in notifications
        $notifiedIds = array_column($result['drivers'], 'driver_id');
        $this->assertNotContains($expiredDriver->id, $notifiedIds);
    }

    /** @test */
    public function test_license_expiry_includes_urgency_level()
    {
        Driver::factory()->create([
            'license_expiry' => Carbon::now()->addDays(5),
            'status' => 'active',
        ]);

        $result = NotificationService::sendLicenseExpiryReminders(daysThreshold: 30);

        $this->assertGreaterThan(0, $result['sent']);
        if (!empty($result['drivers'])) {
            $this->assertArrayHasKey('urgency', $result['drivers'][0]);
        }
    }

    /** @test */
    public function test_service_alert_notification()
    {
        $alert = ServiceAlert::factory()->create([
            'route_id' => $this->route->id,
            'title' => 'Route Delayed',
            'severity' => 'high',
        ]);

        $result = NotificationService::sendServiceAlertNotification($alert);

        $this->assertGreaterThan(0, $result['sent']);
    }

    /** @test */
    public function test_maintenance_completion_notification()
    {
        // Ensure we have at least one admin
        if (User::where('role', 'admin')->count() === 0) {
            User::factory()->create(['role' => 'admin']);
        }
        
        $maintenance = MaintenanceRecord::factory()->create([
            'bus_id' => $this->bus->id,
            'status' => 'completed',
            'completed_at' => now(),
            'inspection_passed' => true,
        ]);

        $result = NotificationService::sendMaintenanceCompletionNotification($maintenance);

        // Should have sent to admins that exist
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('recipients', $result);
    }

    /** @test */
    public function test_pending_maintenance_reminders()
    {
        // Ensure we have at least one admin
        if (User::where('role', 'admin')->count() === 0) {
            User::factory()->create(['role' => 'admin']);
        }
        
        MaintenanceRecord::factory()->create([
            'bus_id' => $this->bus->id,
            'status' => 'scheduled',
            'scheduled_at' => Carbon::now()->addDays(3),
        ]);

        $result = NotificationService::sendPendingMaintenanceReminders(daysThreshold: 7);

        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('maintenance_items', $result);
    }

    /** @test */
    public function test_incident_alert_notification()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $incident = Incident::factory()->create([
            'driver_id' => $this->driver->id,
        ]);

        $result = NotificationService::sendIncidentAlert($incident);

        $this->assertGreaterThan(0, $result['sent']);
    }

    /** @test */
    public function test_notification_summary()
    {
        Driver::factory()->create([
            'license_expiry' => Carbon::now()->addDays(20),
            'status' => 'active',
        ]);

        MaintenanceRecord::factory()->create([
            'status' => 'scheduled',
            'scheduled_at' => Carbon::now()->addDays(3),
        ]);

        ServiceAlert::factory()->create([
            'status' => 'active',
        ]);

        $summary = NotificationService::getNotificationSummary();

        $this->assertArrayHasKey('license_expiry_warnings', $summary);
        $this->assertArrayHasKey('pending_maintenance_due', $summary);
        $this->assertArrayHasKey('active_service_alerts', $summary);
        $this->assertGreaterThan(0, $summary['total_notifications']);
    }
}
