<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\Route;
use App\Models\SystemSetting;
use App\Models\Trip;
use App\Models\TripPassengerEvent;
use App\Models\User;
use Database\Seeders\UATBidirectionalRouteSeeder;
use Database\Seeders\UATSuspendRouteFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminDriverManagementOperationalDataTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function driver_registry_uses_actual_trip_assignment_and_passenger_event_data(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $route = Route::factory()->create([
            'name' => 'Route 2',
            'status' => 'Active',
        ]);
        $bus = Bus::factory()->create([
            'plate_number' => 'PAS-002',
            'route_id' => $route->id,
            'status' => 'operating',
            'passengers' => 7,
        ]);
        $driver = Driver::factory()->create([
            'status' => 'active',
            'operational_status' => 'driving',
            'assigned_bus' => $bus->plate_number,
            'assigned_route' => (string) $route->id,
            'trips_today' => 99,
            'pax_today' => 999,
        ]);

        $today = now('Asia/Manila')->utc();
        $completed = Trip::factory()->create([
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
            'status' => 'completed',
            'gps_session' => 'CLOSED',
            'started_at' => $today->copy()->subHour(),
            'ended_at' => $today->copy()->subMinutes(10),
        ]);
        $ongoing = Trip::factory()->create([
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => $today->copy()->subMinutes(5),
            'ended_at' => null,
        ]);
        Trip::factory()->create([
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
            'status' => 'dispatched',
            'gps_session' => 'OFF',
            'dispatched_at' => $today,
            'started_at' => null,
            'ended_at' => null,
        ]);
        Trip::factory()->create([
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
            'status' => 'cancelled',
            'gps_session' => 'CLOSED',
            'started_at' => $today->copy()->subHours(2),
            'ended_at' => $today->copy()->subHour(),
        ]);
        Trip::factory()->create([
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
            'status' => 'completed',
            'gps_session' => 'CLOSED',
            'started_at' => $today->copy()->subDay(),
            'ended_at' => $today->copy()->subDay(),
        ]);

        $this->passengerEvent($completed, TripPassengerEvent::TYPE_BOARDED, 4, $today->copy()->subMinutes(40));
        $this->passengerEvent($ongoing, TripPassengerEvent::TYPE_BOARDED, 6, $today->copy()->subMinutes(3));
        $this->passengerEvent($ongoing, TripPassengerEvent::TYPE_ALIGHTED, 2, $today->copy()->subMinute());
        $this->passengerEvent($completed, TripPassengerEvent::TYPE_BOARDED, 50, $today->copy()->subDay());

        $response = $this->getJson(route('admin.api.drivers.index'))->assertOk();
        $row = collect($response->json('drivers'))->firstWhere('id', $driver->id);

        $this->assertSame('PAS-002', $row['assigned_bus']);
        $this->assertSame('Route 2', $row['assigned_route_name']);
        $this->assertSame('driving', $row['operational_status']);
        $this->assertSame('Driving', $row['operational_label']);
        $this->assertSame(2, $row['trips_today']);
        $this->assertSame(1, $row['completed_trips_today']);
        $this->assertSame(10, $row['pax_today']);
        $this->assertSame($ongoing->id, $row['active_trip']['id']);
        $this->assertSame(7, $row['active_trip']['onboard_passengers']);
        $this->assertSame(5, $row['trip_history_total']);
        $this->assertCount(5, $row['trip_history']);
        $this->assertNotSame(99, $row['trips_today']);
        $this->assertNotSame(999, $row['pax_today']);
    }

    #[Test]
    public function orphaned_legacy_assignments_are_not_presented_as_real_bus_or_route_assignments(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $driver = Driver::factory()->create([
            'status' => 'active',
            'operational_status' => 'assigned',
            'assigned_bus' => 'PAS-UAT1',
            'assigned_route' => '99999',
        ]);

        $response = $this->getJson(route('admin.api.drivers.index'))->assertOk();
        $row = collect($response->json('drivers'))->firstWhere('id', $driver->id);

        $this->assertNull($row['assigned_bus']);
        $this->assertNull($row['assigned_route']);
        $this->assertNull($row['assigned_route_name']);
        $this->assertFalse($row['assignment_is_consistent']);
        $this->assertSame('unavailable', $row['operational_status']);
    }

    #[Test]
    public function available_and_inactive_employment_states_are_not_labeled_as_driving(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $available = Driver::factory()->create([
            'status' => 'active',
            'operational_status' => 'available',
            'assigned_bus' => null,
            'assigned_route' => null,
            'license_expiry' => now('Asia/Manila')->addYear(),
        ]);
        $inactive = Driver::factory()->create([
            'status' => 'inactive',
            'operational_status' => 'available',
            'assigned_bus' => null,
            'assigned_route' => null,
        ]);

        $rows = collect($this->getJson(route('admin.api.drivers.index'))->assertOk()->json('drivers'));

        $this->assertSame('Available', $rows->firstWhere('id', $available->id)['operational_label']);
        $this->assertSame('Off Duty', $rows->firstWhere('id', $inactive->id)['operational_label']);
    }

    #[Test]
    public function driver_details_operational_score_matches_today_driver_performance_analytics(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        SystemSetting::updateOrCreate(
            ['key' => 'driver_score_incident_penalty'],
            ['value' => '10'],
        );
        Cache::flush();

        $route = Route::factory()->create([
            'name' => 'Route 2',
            'status' => 'Active',
        ]);
        $driver = Driver::factory()->create([
            'first_name' => 'Aligned',
            'last_name' => 'Operator',
            'performance_score' => 12,
        ]);
        $noDataDriver = Driver::factory()->create([
            'first_name' => 'No Data',
            'last_name' => 'Operator',
            'performance_score' => 99,
        ]);
        $operationDay = now('Asia/Manila')->startOfDay();
        $trip = Trip::factory()->create([
            'route_id' => $route->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => $operationDay->copy()->addHours(6),
            'ended_at' => $operationDay->copy()->addHours(7),
        ]);
        Incident::create([
            'trip_id' => $trip->id,
            'driver_id' => $driver->id,
            'type' => 'Breakdown',
            'description' => 'Qualifying operational incident.',
            'status' => 'reported',
            'reported_at' => $operationDay->copy()->addHours(6)->addMinutes(20),
        ]);
        Incident::create([
            'trip_id' => $trip->id,
            'driver_id' => $driver->id,
            'type' => 'Heavy Traffic Delay',
            'description' => 'Non-qualifying operational incident.',
            'status' => 'reported',
            'reported_at' => $operationDay->copy()->addHours(6)->addMinutes(30),
        ]);

        $detailsRows = collect($this->getJson(route('admin.api.drivers.index'))->assertOk()->json('drivers'));
        $detailsRow = $detailsRows->firstWhere('id', $driver->id);
        $noDataRow = $detailsRows->firstWhere('id', $noDataDriver->id);
        $analyticsRow = collect($this->getJson(route('admin.api.analytics', [
            'start' => now('Asia/Manila')->toDateString(),
            'end' => now('Asia/Manila')->toDateString(),
        ]))->assertOk()->json('driverPerformance'))->firstWhere('name', 'Aligned Operator');

        $this->assertSame(90, $detailsRow['performance_score']);
        $this->assertSame('actual_operations_today', $detailsRow['performance_score_basis']);
        $this->assertSame(1, $detailsRow['performance_score_trips_run']);
        $this->assertSame(1, $detailsRow['performance_score_qualifying_incidents']);
        $this->assertSame($analyticsRow['operationalScore'], $detailsRow['performance_score']);
        $this->assertNotSame($driver->performance_score, $detailsRow['performance_score']);
        $this->assertNull($noDataRow['performance_score']);
    }

    #[Test]
    public function legacy_uat_cleanup_removes_orphaned_driver_and_bus_rows_even_without_the_uat_route(): void
    {
        Bus::factory()->create(['plate_number' => 'PAS-UAT1']);
        Bus::factory()->create(['plate_number' => 'PAS-UAT2']);
        Driver::factory()->create([
            'emp_id' => 'EMP-UAT1',
            'license_number' => 'UAT-LIC-001',
            'assigned_bus' => 'PAS-UAT1',
        ]);
        Driver::factory()->create([
            'emp_id' => 'EMP-UAT2',
            'license_number' => 'UAT-LIC-002',
            'assigned_bus' => 'PAS-UAT2',
        ]);

        UATBidirectionalRouteSeeder::cleanup();

        $this->assertDatabaseMissing('buses', ['plate_number' => 'PAS-UAT1']);
        $this->assertDatabaseMissing('buses', ['plate_number' => 'PAS-UAT2']);
        $this->assertDatabaseMissing('drivers', ['emp_id' => 'EMP-UAT1']);
        $this->assertDatabaseMissing('drivers', ['emp_id' => 'EMP-UAT2']);
    }

    #[Test]
    public function suspend_route_uat_cleanup_releases_a_real_driver_from_an_orphaned_uat_assignment(): void
    {
        $driver = Driver::factory()->create([
            'status' => 'active',
            'operational_status' => 'assigned',
            'assigned_bus' => UATSuspendRouteFixtureSeeder::INBOUND_BUS_PLATE,
            'assigned_route' => '99999',
        ]);

        UATSuspendRouteFixtureSeeder::cleanup(force: true, includeLegacy: false);

        $driver->refresh();
        $this->assertNull($driver->assigned_bus);
        $this->assertNull($driver->assigned_route);
        $this->assertSame('available', $driver->operational_status);
    }

    #[Test]
    public function driver_management_ui_contains_no_fabricated_operational_values(): void
    {
        $script = file_get_contents(public_path('js/admin-dashboard/drivers.js'));
        $navigationScript = file_get_contents(public_path('js/admin-dashboard/navigation.js'));
        $indexView = file_get_contents(resource_path('views/admin/drivers/index.blade.php'));
        $showView = file_get_contents(resource_path('views/admin/drivers/show.blade.php'));

        $this->assertStringNotContainsString('Morning Shift', $script);
        $this->assertStringNotContainsString('driver.id + 120', $script);
        $this->assertStringNotContainsString('24 km/h', $script);
        $this->assertStringNotContainsString('09:12 AM', $script);
        $this->assertStringNotContainsString("driver.bus || 'PAS-003'", $script);
        $this->assertStringNotContainsString('Schedule Adherence & Completion', $showView);
        $this->assertStringNotContainsString('Commuter Feedback Rating', $showView);
        $this->assertStringNotContainsString('nameDotColor', $script);
        $this->assertStringNotContainsString('ID: ${driver.empId}', $script);
        $this->assertStringNotContainsString('License: ${driver.license}', $script);
        $this->assertStringNotContainsString('h-1.5 w-1.5 rounded-full', $script);
        $this->assertStringContainsString('Current assignment', $indexView);
        $this->assertStringContainsString("Today's operations", $indexView);
        $this->assertStringContainsString('buildAssignmentCell(driver)', $script);
        $this->assertStringContainsString('buildTodayOperationsCell(driver)', $script);
        $this->assertStringContainsString('Operational Score Today', $showView);
        $this->assertStringContainsString("Today's Operational Performance", $showView);
        $this->assertStringContainsString('dp-show-incident-summary', $showView);
        $this->assertStringNotContainsString('Performance Breakdown & Trend', $showView);
        $this->assertStringNotContainsString('Attendance Score', $showView);
        $this->assertStringNotContainsString('No data available', $showView);
        $this->assertStringNotContainsString('No active incidents log', $showView);
        $this->assertStringNotContainsString('Internal Remarks & Notes', $showView);
        $this->assertStringNotContainsString('No custom coordinator remarks recorded', $showView);
        $this->assertStringContainsString('No incidents recorded in the last 30 days', $script);
        $this->assertStringContainsString('No Trip records available for this driver.', $script);
        $this->assertStringNotContainsString('No trip logs recorded in the system.', $script);
        $this->assertStringContainsString("driver.perfScore === null", $script);
        $this->assertStringContainsString("ratingBadge.textContent = 'NO DATA'", $script);
        $this->assertStringNotContainsString('License expiry</th>', $indexView);
        $this->assertStringNotContainsString('Assigned bus</th>', $indexView);
        $this->assertStringContainsString('DRIVERS_AUTO_SYNC_INTERVAL_MS = 5000', $script);
        $this->assertStringContainsString("cache: 'no-store'", $script);
        $this->assertStringContainsString('refreshOpenDriverDetails();', $script);
        $this->assertStringContainsString("window.addEventListener('focus', syncVisibleDriversData)", $script);
        $this->assertStringContainsString("document.addEventListener('visibilitychange'", $script);
        $this->assertStringContainsString("screen === 'drivers' || screen === 'drivers-show'", $script);
        $this->assertStringContainsString("new CustomEvent('request-driver-management-refresh'", $navigationScript);
    }

    private function passengerEvent(Trip $trip, string $type, int $delta, $recordedAt): TripPassengerEvent
    {
        return TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'bus_id' => $trip->bus_id,
            'route_id' => $trip->route_id,
            'event_type' => $type,
            'passenger_delta' => $delta,
            'onboard_after' => $delta,
            'recorded_at' => $recordedAt,
        ]);
    }
}
