<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\Route;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalIncidentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $fleetUser;

    private User $driverUser;

    private Route $route;

    private Driver $driver;

    private Bus $bus;

    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fleetUser = User::factory()->create(['role' => 'fleet_manager']);
        $this->driverUser = User::factory()->create(['role' => 'driver']);
        $this->route = $this->createRoute('Route 2');

        $this->driver = Driver::create([
            'user_id' => $this->driverUser->id,
            'emp_id' => 'INC-DRIVER-01',
            'first_name' => 'Incident',
            'last_name' => 'Driver',
            'license_number' => 'N01-99-100001',
            'license_expiry' => '2028-12-12',
            'status' => 'active',
            'operational_status' => 'on_duty',
            'assigned_bus' => 'PAS-INC-01',
            'assigned_route' => (string) $this->route->id,
        ]);

        $this->bus = Bus::create([
            'plate_number' => 'PAS-INC-01',
            'status' => 'operating',
            'route_id' => $this->route->id,
            'driver_name' => 'Incident Driver',
            'capacity' => 45,
            'lat' => 14.5602934,
            'lng' => 121.0797616,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $this->trip = Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now(),
        ]);
    }

    public function test_incident_report_module_uses_canonical_type_and_always_starts_open(): void
    {
        $response = $this->actingAs($this->fleetUser)->postJson('/fleet/api/incidents-store', [
            'trip_id' => $this->trip->id,
            'type' => Incident::getTrafficDelayType(),
            'description' => 'Heavy traffic beside the current official stop.',
            'status' => 'resolved',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('incidents', [
            'trip_id' => $this->trip->id,
            'type' => Incident::getTrafficDelayType(),
            'status' => 'reported',
        ]);
        $this->assertDatabaseHas('bus_status_audit_log', [
            'bus_id' => $this->bus->id,
            'old_status' => 'operating',
            'new_status' => 'operating',
            'changed_by' => $this->fleetUser->id,
        ]);
        $this->assertSame('operating', $this->bus->fresh()->status);
        $this->assertSame('ongoing', $this->trip->fresh()->status);
    }

    public function test_fleet_overview_uses_the_same_explicit_trip_contract(): void
    {
        $response = $this->actingAs($this->fleetUser)->postJson('/fleet/api/incidents', [
            'trip_id' => $this->trip->id,
            'type' => Incident::getPassengerConcernType(),
            'description' => 'Passenger concern reported from Fleet Overview.',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('incidents', [
            'trip_id' => $this->trip->id,
            'driver_id' => $this->driver->id,
            'type' => Incident::getPassengerConcernType(),
            'status' => 'reported',
        ]);
        $this->assertSame('operating', $this->bus->fresh()->status);
        $this->assertSame('ongoing', $this->trip->fresh()->status);
    }

    public function test_driver_major_incident_atomically_breaks_bus_and_finalizes_trip(): void
    {
        $response = $this->actingAs($this->driverUser)->postJson('/driver/trip/incident', [
            'type' => Incident::getAccidentType(),
            'description' => 'Minor collision while the official trip was operating.',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('incidents', [
            'trip_id' => $this->trip->id,
            'driver_id' => $this->driver->id,
            'type' => Incident::getAccidentType(),
            'status' => 'reported',
        ]);
        $this->assertDatabaseHas('trip_logs', [
            'trip_id' => $this->trip->id,
            'status' => 'cancelled',
        ]);
        $this->assertSame('breakdown', $this->bus->fresh()->status);
        $this->assertSame('cancelled', $this->trip->fresh()->status);
        $this->assertSame('unavailable', $this->driver->fresh()->operational_status);
        $this->assertSame('PAS-INC-01', $this->driver->assigned_bus);
        $this->assertSame($this->route->id, $this->bus->route_id);
    }

    public function test_completed_and_non_official_trips_are_rejected(): void
    {
        $this->trip->update(['status' => 'completed', 'ended_at' => now()]);

        $this->actingAs($this->fleetUser)
            ->postJson('/fleet/api/incidents-store', [
                'trip_id' => $this->trip->id,
                'type' => Incident::getPassengerConcernType(),
                'description' => 'This completed trip must not accept a report.',
            ])
            ->assertStatus(422);

        $legacyRoute = $this->createRoute('Route A');
        $legacyTrip = Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $legacyRoute->id,
            'status' => 'ongoing',
            'started_at' => now(),
        ]);

        $this->postJson('/fleet/api/incidents-store', [
            'trip_id' => $legacyTrip->id,
            'type' => Incident::getPassengerConcernType(),
            'description' => 'A legacy route must fail the official route guard.',
        ])->assertStatus(422);

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_failed_breakdown_transition_rolls_back_incident_creation(): void
    {
        $this->bus->update(['status' => 'breakdown']);

        $this->actingAs($this->driverUser)
            ->postJson('/driver/trip/incident', [
                'type' => Incident::getBreakdownType(),
                'description' => 'Inconsistent repeated breakdown transition.',
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('incidents', 0);
        $this->assertSame('ongoing', $this->trip->fresh()->status);
    }

    public function test_resolving_incident_does_not_restore_breakdown_bus(): void
    {
        $this->actingAs($this->driverUser)->postJson('/driver/trip/incident', [
            'type' => Incident::getBreakdownType(),
            'description' => 'Engine failure requiring maintenance review.',
        ])->assertOk();

        $incident = Incident::query()->firstOrFail();

        $this->actingAs($this->fleetUser)
            ->postJson("/fleet/api/incidents/{$incident->id}/resolve")
            ->assertOk();

        $this->assertSame('resolved', $incident->fresh()->status);
        $this->assertSame('breakdown', $this->bus->fresh()->status);
        $this->assertSame('PAS-INC-01', $this->driver->fresh()->assigned_bus);
    }

    public function test_incident_records_cannot_be_permanently_deleted_through_fleet_api(): void
    {
        $incident = Incident::create([
            'trip_id' => $this->trip->id,
            'driver_id' => $this->driver->id,
            'type' => Incident::getPassengerConcernType(),
            'description' => 'Historical incident must be retained.',
            'status' => 'reported',
            'reported_at' => now(),
        ]);

        $this->actingAs($this->fleetUser)
            ->deleteJson("/fleet/api/incidents-delete/{$incident->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('incidents', ['id' => $incident->id]);
    }

    public function test_incident_payload_lists_only_official_ongoing_trips(): void
    {
        $legacyRoute = $this->createRoute('Route A');
        Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $legacyRoute->id,
            'status' => 'ongoing',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->fleetUser)->getJson('/fleet/api/incidents-data');

        $response->assertOk()
            ->assertJsonCount(1, 'ongoingTrips')
            ->assertJsonPath('ongoingTrips.0.id', $this->trip->id)
            ->assertJsonPath('ongoingTrips.0.route_name', 'Route 2');
    }

    public function test_incident_report_filters_and_metrics_use_official_selected_period_contract(): void
    {
        $this->recordIncident($this->trip, Incident::getBreakdownType(), 'reported', '2026-08-14 09:00:00');
        $this->recordIncident($this->trip, Incident::getAccidentType(), 'resolved', '2026-08-14 10:00:00', '2026-08-14 10:30:00');
        $this->recordIncident($this->trip, Incident::getPassengerConcernType(), 'reported', '2026-08-13 09:00:00');

        $route3 = $this->createRoute('Route 3');
        $route3Trip = $this->createTripForRoute($route3);
        $this->recordIncident($route3Trip, Incident::getBreakdownType(), 'reported', '2026-08-14 09:15:00');

        $legacyRoute = $this->createRoute('Route A');
        $legacyTrip = $this->createTripForRoute($legacyRoute);
        $this->recordIncident($legacyTrip, Incident::getBreakdownType(), 'reported', '2026-08-14 09:30:00');

        $response = $this->actingAs($this->fleetUser)->getJson(
            "/fleet/api/incidents-data?date_start=2026-08-14&date_end=2026-08-14&route_filter={$this->route->id}&type_filter=all&status_filter=all"
        );

        $response->assertOk()
            ->assertJsonCount(1, 'activeIncidents')
            ->assertJsonCount(1, 'resolvedIncidents')
            ->assertJsonPath('incidentMetrics.total_today', 2)
            ->assertJsonPath('incidentMetrics.open', 1)
            ->assertJsonPath('incidentMetrics.under_investigation', 0)
            ->assertJsonPath('incidentMetrics.resolved_today', 1)
            ->assertJsonPath('incidentMetrics.avg_resolution_minutes', 30)
            ->assertJsonPath('activeIncidents.0.route_name', 'Route 2')
            ->assertJsonPath('resolvedIncidents.0.route_name', 'Route 2');

        $resolvedOnly = $this->actingAs($this->fleetUser)->getJson(
            "/fleet/api/incidents-data?date_start=2026-08-14&date_end=2026-08-14&route_filter={$this->route->id}&type_filter=all&status_filter=Resolved"
        );

        $resolvedOnly->assertOk()
            ->assertJsonCount(0, 'activeIncidents')
            ->assertJsonCount(1, 'resolvedIncidents')
            ->assertJsonPath('incidentMetrics.total_today', 1)
            ->assertJsonPath('incidentMetrics.open', 0)
            ->assertJsonPath('incidentMetrics.resolved_today', 1);
    }

    public function test_incident_report_export_uses_same_filters_and_official_route_guard(): void
    {
        $this->recordIncident($this->trip, Incident::getBreakdownType(), 'reported', '2026-08-14 09:00:00', null, 'Official Route 2 incident, exported.');
        $this->recordIncident($this->trip, Incident::getAccidentType(), 'resolved', '2026-08-14 10:00:00', '2026-08-14 10:05:00', 'Resolved Route 2 incident.');

        $legacyRoute = $this->createRoute('Route A');
        $legacyTrip = $this->createTripForRoute($legacyRoute);
        $this->recordIncident($legacyTrip, Incident::getBreakdownType(), 'reported', '2026-08-14 09:30:00', null, 'Legacy route incident must not export.');

        $response = $this->actingAs($this->fleetUser)->get(
            "/fleet/api/incidents-export?date_start=2026-08-14&date_end=2026-08-14&route_filter={$this->route->id}&type_filter=all&status_filter=Open"
        );

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Official Route 2 incident, exported.', $csv);
        $this->assertStringContainsString('Route 2', $csv);
        $this->assertStringContainsString('reported', $csv);
        $this->assertStringNotContainsString('Resolved Route 2 incident.', $csv);
        $this->assertStringNotContainsString('Legacy route incident must not export.', $csv);
        $this->assertStringNotContainsString('Route A', $csv);
    }

    public function test_incident_ui_uses_canonical_types_escaping_and_no_delete_contract(): void
    {
        $view = file_get_contents(resource_path('views/fleet/incidents/index.blade.php'));
        $script = file_get_contents(public_path('js/fleet-dashboard/incidents.js'));
        $overviewScript = file_get_contents(public_path('js/fleet-dashboard/overview.js'));

        $this->assertStringContainsString('Incident::getTypes()', $view);
        $this->assertStringNotContainsString('id="newStatus"', $view);
        $this->assertStringContainsString('btn-export-incidents-report', $view);
        $this->assertStringContainsString('Total incidents in period', $view);
        $this->assertStringContainsString('Resolved in period', $view);
        $this->assertStringNotContainsString('Confirm delete record', $view);
        $this->assertStringNotContainsString('Route Deviation', $view);
        $this->assertStringNotContainsString('Passenger Disturbance', $view);
        $this->assertStringContainsString('escapeIncidentHtml(incident.title)', $script);
        $this->assertStringContainsString('escapeIncidentHtml(incident.description)', $script);
        $this->assertStringNotContainsString('incidents-delete', $script);
        $this->assertStringContainsString('escapeOverviewHtml(incident.title)', $overviewScript);
        $this->assertStringContainsString('escapeOverviewHtml(activity.description)', $overviewScript);
    }

    private function createRoute(string $name): Route
    {
        return Route::create([
            'name' => $name,
            'description' => $name.' incident workflow fixture',
            'polyline_coordinates' => [[14.5602934, 121.0797616]],
            'status' => 'Active',
            'min_buses_required' => 1,
        ]);
    }

    private function createTripForRoute(Route $route): Trip
    {
        return Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'started_at' => now(),
        ]);
    }

    private function recordIncident(
        Trip $trip,
        string $type,
        string $status,
        string $reportedAt,
        ?string $updatedAt = null,
        string $description = 'Operational incident fixture.'
    ): Incident {
        $reportedAtUtc = \Carbon\Carbon::parse($reportedAt, 'Asia/Manila')->utc();
        $updatedAtUtc = $updatedAt
            ? \Carbon\Carbon::parse($updatedAt, 'Asia/Manila')->utc()
            : $reportedAtUtc->copy();

        $incident = Incident::create([
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'type' => $type,
            'description' => $description,
            'status' => $status,
            'reported_at' => $reportedAtUtc,
        ]);

        $incident->forceFill([
            'created_at' => $reportedAtUtc,
            'updated_at' => $updatedAtUtc,
        ])->save();

        return $incident;
    }
}
