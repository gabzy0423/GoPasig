<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Trip;
use App\Models\TripPassengerEvent;
use App\Models\User;
use App\Http\Controllers\Admin\DashboardController;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminDashboardAuditFixTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_dashboard_initial_route_preload_is_limited_to_official_routes(): void
    {
        foreach (['Route 2', 'Route 3', 'Route 4'] as $routeName) {
            Route::factory()->official($routeName)->create([
                'status' => 'Active',
                'description' => "{$routeName} official service",
            ]);
        }

        Route::factory()->create(['name' => 'Route A', 'status' => 'Active']);
        Route::factory()->create(['name' => 'Bridgetowne', 'status' => 'Active']);
        Route::factory()->official('Route 9')->create(['status' => 'Active']);

        $view = app(DashboardController::class)->index();
        $routeNames = $view->getData()['routes']->pluck('name')->values()->all();

        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], $routeNames);
    }

    #[Test]
    public function admin_dashboard_placeholder_copy_does_not_claim_mock_records(): void
    {
        $dashboardView = file_get_contents(resource_path('views/admin/dashboard.blade.php'));
        $placeholderView = file_get_contents(resource_path('views/admin/placeholder.blade.php'));
        $analyticsInteractions = file_get_contents(public_path('js/admin-dashboard/analytics-interactions.js'));

        foreach ([$dashboardView, $placeholderView, $analyticsInteractions] as $content) {
            $this->assertStringNotContainsString('local mock records', $content);
            $this->assertStringNotContainsString('mock generating', $content);
        }

        $this->assertStringContainsString(
            'Select an operational module from the sidebar to continue.',
            $dashboardView
        );
        $this->assertStringContainsString(
            'Select an operational module from the sidebar to continue.',
            $placeholderView
        );
    }

    #[Test]
    public function admin_report_builder_preview_uses_actual_operations_not_legacy_schedules(): void
    {
        $reportBuilder = file_get_contents(resource_path('views/components/admin/⚡report-builder.blade.php'));
        $analyticsInteractions = file_get_contents(public_path('js/admin-dashboard/analytics-interactions.js'));

        $this->assertStringContainsString('Recorded Boarded', $reportBuilder);
        $this->assertStringContainsString('analytics-period-summary', $reportBuilder);
        $this->assertStringContainsString('wire:ignore', $reportBuilder);
        $this->assertStringContainsString('preview-metric-a', $reportBuilder);
        $this->assertStringContainsString('preview-metric-b', $reportBuilder);
        $this->assertStringContainsString('preview-metric-c', $reportBuilder);
        $this->assertStringContainsString('updateReportLivePreview', $analyticsInteractions);
        $this->assertStringContainsString('window.analyticsReportingPeriod', $analyticsInteractions);
        $this->assertStringContainsString("Livewire.hook('morph.updated'", $analyticsInteractions);
        $this->assertStringContainsString('syncAnalyticsPeriodControls()', $analyticsInteractions);
        $this->assertStringContainsString('dataset.analyticsReportTypeBound', $analyticsInteractions);
        $this->assertStringContainsString('kpisData', $analyticsInteractions);
        $this->assertStringContainsString('tripData', $analyticsInteractions);

        $this->assertStringNotContainsString('Schedule::sum', $reportBuilder);
        $this->assertStringNotContainsString('Schedule::count', $reportBuilder);
        $this->assertStringNotContainsString("Schedule::where('status'", $reportBuilder);
        $this->assertStringNotContainsString('Select Date Range', $reportBuilder);
        $this->assertStringNotContainsString("wire:click=\"\$set('dateRange'", $reportBuilder);
        $this->assertStringNotContainsString('Total Pax:', $reportBuilder);
        $this->assertStringNotContainsString('On-Time:', $reportBuilder);
    }

    #[Test]
    public function admin_report_builder_does_not_render_fake_download_actions(): void
    {
        $reportBuilder = file_get_contents(resource_path('views/components/admin/⚡report-builder.blade.php'));
        $commuterNavbar = file_get_contents(resource_path('views/components/commuter/navbar.blade.php'));
        $commuterFooter = file_get_contents(resource_path('views/components/commuter/footer.blade.php'));
        $analyticsInteractions = file_get_contents(public_path('js/admin-dashboard/analytics-interactions.js'));

        $this->assertStringContainsString('data-preview-export-button', $reportBuilder);
        $this->assertStringContainsString('No data to export', $reportBuilder);
        $this->assertStringContainsString('Recent report records (last 30)', $reportBuilder);
        $this->assertStringContainsString('No report records generated yet.', $reportBuilder);
        $this->assertStringContainsString('Generate Report Record', $reportBuilder);
        $this->assertStringContainsString('function callReportBuilderGenerate', $analyticsInteractions);
        $this->assertStringContainsString('function getReportBuilderCsvPayload', $analyticsInteractions);
        $this->assertStringContainsString('function exportReportBuilderCsv', $analyticsInteractions);
        $this->assertStringContainsString('downloadAnalyticsCSV', $analyticsInteractions);
        $this->assertStringContainsString("component.call('generateReport'", $analyticsInteractions);
        $this->assertStringContainsString('Report preview saved to export history. Use Export CSV when this report has rows.', $analyticsInteractions);
        $this->assertStringContainsString("reportType === 'maintenance'", $analyticsInteractions);
        $this->assertStringContainsString('Maintenance Records', $analyticsInteractions);
        $this->assertStringContainsString('Scheduled/In Progress', $analyticsInteractions);
        $this->assertStringContainsString('maintenanceLogRecordsData', $analyticsInteractions);
        $this->assertStringContainsString('maintenanceSummaryData', $analyticsInteractions);
        $this->assertStringContainsString("headers: withPeriod(['Ticket', 'Bus', 'Type', 'Status', 'Scheduled', 'Completed', 'Technician', 'Inspector', 'Result', 'Roadworthy', 'Total Cost'])", $analyticsInteractions);
        $this->assertStringContainsString('CSV export generated from the current report preview.', $analyticsInteractions);
        $this->assertStringContainsString('Record only', $reportBuilder);
        $this->assertStringNotContainsString('PDF Report', $reportBuilder);
        $this->assertStringNotContainsString('Choose Format', $reportBuilder);
        $this->assertStringNotContainsString('PDF export deferred', $reportBuilder);
        $this->assertStringNotContainsString('CSV export deferred', $reportBuilder);
        $this->assertStringNotContainsString('PDF export deferred', $analyticsInteractions);
        $this->assertStringNotContainsString('CSV export deferred', $analyticsInteractions);
        $this->assertStringNotContainsString('Maintenance Log CSV export is not available until the maintenance source is aligned.', $analyticsInteractions);

        foreach ([$reportBuilder, $commuterNavbar, $commuterFooter, $analyticsInteractions] as $content) {
            $this->assertStringNotContainsString('Downloading PDF', $content);
            $this->assertStringNotContainsString('Downloading CSV', $content);
            $this->assertStringNotContainsString('Downloading Report', $content);
        }
    }

    #[Test]
    public function admin_topbar_export_is_disabled_until_trip_load_rows_are_available(): void
    {
        $adminHeader = file_get_contents(resource_path('views/components/admin/header.blade.php'));
        $fleetTopbar = file_get_contents(resource_path('views/components/fleet/topbar.blade.php'));
        $analyticsInteractions = file_get_contents(public_path('js/admin-dashboard/analytics-interactions.js'));

        $this->assertStringContainsString('id="layout-export-btn"', $adminHeader);
        $this->assertStringContainsString('disabled', $adminHeader);
        $this->assertStringContainsString('data-export-enabled="false"', $adminHeader);
        $this->assertStringContainsString('No exportable report data available yet.', $adminHeader);

        $this->assertStringContainsString('id="layout-export-btn"', $fleetTopbar);
        $this->assertStringContainsString('Use the report section export controls when data is available.', $fleetTopbar);

        $this->assertStringContainsString('function updateLayoutExportButton', $analyticsInteractions);
        $this->assertStringContainsString('rows.length > 0', $analyticsInteractions);
        $this->assertStringContainsString("activeScreenName === 'analytics-driver-performance'", $analyticsInteractions);
        $this->assertStringContainsString('btn.onclick = () => exportCSVDataMock();', $analyticsInteractions);
    }

    #[Test]
    public function fleet_overview_keeps_zero_completed_trips_for_a_real_zero_trip_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-18 09:00:00', 'Asia/Manila'));

        $route = Route::factory()->official('Route 2')->create();
        $bus = Bus::factory()->create(['status' => 'active', 'passengers' => 10, 'capacity' => 40]);
        $driver = Driver::factory()->create();

        Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'completed',
            'started_at' => Carbon::yesterday('Asia/Manila')->setTime(8, 0),
            'ended_at' => Carbon::yesterday('Asia/Manila')->setTime(9, 0),
        ]);

        $stats = app(DashboardService::class)->getFleetOverviewKpi();

        $this->assertSame(0, $stats['trips_completed']);
        $this->assertSame('-1 vs yesterday', $stats['deltas']->trips_completed_yesterday);

        Carbon::setTestNow();
    }

    #[Test]
    public function fleet_overview_active_now_uses_only_ongoing_official_trip_buses(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-18 09:00:00', 'Asia/Manila'));

        $route = Route::factory()->official('Route 2')->create();
        $legacyRoute = Route::factory()->create(['name' => 'Route A', 'status' => 'Active']);
        $driver = Driver::factory()->create();
        $activeNowBus = Bus::factory()->create(['status' => 'operating', 'passengers' => 10, 'capacity' => 40]);
        $activeWithoutTripBus = Bus::factory()->create(['status' => 'operating', 'passengers' => 20, 'capacity' => 40]);
        $completedBus = Bus::factory()->create(['status' => 'ready', 'passengers' => 30, 'capacity' => 40]);
        $legacyTripBus = Bus::factory()->create(['status' => 'operating', 'passengers' => 40, 'capacity' => 40]);

        $activeTrip = Trip::factory()->create([
            'bus_id' => $activeNowBus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'started_at' => Carbon::today('Asia/Manila')->setTime(6, 0),
            'ended_at' => null,
        ]);

        Trip::factory()->create([
            'bus_id' => $completedBus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'completed',
            'started_at' => Carbon::today('Asia/Manila')->setTime(7, 0),
            'ended_at' => Carbon::today('Asia/Manila')->setTime(8, 0),
        ]);

        $legacyTrip = Trip::factory()->create([
            'bus_id' => $legacyTripBus->id,
            'driver_id' => $driver->id,
            'route_id' => $legacyRoute->id,
            'status' => 'ongoing',
            'started_at' => Carbon::today('Asia/Manila')->setTime(6, 0),
            'ended_at' => null,
        ]);

        TripPassengerEvent::create([
            'trip_id' => $activeTrip->id,
            'driver_id' => $driver->id,
            'bus_id' => $activeNowBus->id,
            'route_id' => $route->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 6,
            'onboard_after' => 6,
            'recorded_at' => Carbon::parse('2026-06-18 08:05:00', 'Asia/Manila')->utc(),
        ]);

        TripPassengerEvent::create([
            'trip_id' => $legacyTrip->id,
            'driver_id' => $driver->id,
            'bus_id' => $activeWithoutTripBus->id,
            'route_id' => $legacyRoute->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 99,
            'onboard_after' => 99,
            'recorded_at' => Carbon::parse('2026-06-18 08:10:00', 'Asia/Manila')->utc(),
        ]);

        $stats = app(DashboardService::class)->getFleetOverviewKpi();

        $this->assertSame(1, $stats['active_buses']);
        $this->assertSame(6, $stats['total_passengers']);
        $this->assertSame(25, $stats['avg_utilization']);
        $this->assertSame(1, $stats['trips_completed']);

        Carbon::setTestNow();
    }

    #[Test]
    public function fleet_overview_route_health_uses_actual_trips_not_schedule_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-18 10:00:00', 'Asia/Manila'));

        $user = User::factory()->create(['role' => 'fleet_manager']);
        $route = Route::factory()->official('Route 2')->create(['description' => 'SPED to Ligaya']);
        $bus = Bus::factory()->create(['status' => 'operating']);
        $driver = Driver::factory()->create();

        Schedule::factory()->count(5)->create([
            'route_id' => $route->id,
            'departure_time' => '05:00:00',
            'service_date' => Carbon::today('Asia/Manila')->toDateString(),
        ]);

        Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'completed',
            'started_at' => Carbon::parse('2026-06-18 08:00:00', 'Asia/Manila')->utc(),
            'ended_at' => Carbon::parse('2026-06-18 08:20:00', 'Asia/Manila')->utc(),
        ]);

        Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'started_at' => Carbon::parse('2026-06-18 08:30:00', 'Asia/Manila')->utc(),
            'ended_at' => null,
        ]);

        Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'completed',
            'started_at' => Carbon::parse('2026-06-17 08:00:00', 'Asia/Manila')->utc(),
            'ended_at' => Carbon::parse('2026-06-17 08:20:00', 'Asia/Manila')->utc(),
        ]);

        $response = $this->actingAs($user)->getJson('/fleet/api/overview-data');
        $response->assertOk();

        $routeHealth = collect($response->json('routeHealth'))->firstWhere('route_id', $route->id);

        $this->assertNotNull($routeHealth);
        $this->assertSame(1, $routeHealth['buses_on_route']);
        $this->assertSame(1, $routeHealth['completed_trips']);
        $this->assertSame(2, $routeHealth['started_trips']);
        $this->assertSame(30, $routeHealth['avg_headway']);
        $this->assertSame('30m', $routeHealth['avg_headway_label']);
        $this->assertArrayNotHasKey('scheduled_trips', $routeHealth);

        Carbon::setTestNow();
    }
}
