<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Route;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Incident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FleetIncidentsTest extends TestCase
{
    use RefreshDatabase;

    private $dispatcher;
    private $route;
    private $bus;
    private $driver;
    private $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $this->route = Route::create([
            'id' => 1,
            'name' => 'Route 1',
            'description' => 'SPED to Pasig City Hall',
            'polyline_coordinates' => [[14.5593, 121.0805], [14.5838, 121.0620]],
            'status' => 'Active',
        ]);

        $this->bus = Bus::create([
            'plate_number' => 'PAS-555',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $this->driver = Driver::create([
            'emp_id' => 'EMP-5555',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456789',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
        ]);

        $this->trip = Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'ongoing',
            'started_at' => now(),
        ]);
    }

    public function test_dispatcher_can_access_fleet_incidents(): void
    {
        $response = $this->actingAs($this->dispatcher)->get('/fleet/incidents');
        $response->assertStatus(200);
        $response->assertSeeLivewire('fleet.incidents-management');
    }

    public function test_unauthorized_users_cannot_access_fleet_incidents(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get('/fleet/incidents');
        $response->assertStatus(403);

        $driver = User::factory()->create(['role' => 'driver']);
        $response = $this->actingAs($driver)->get('/fleet/incidents');
        $response->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/fleet/incidents');
        $response->assertRedirect('/login');
    }

    public function test_livewire_component_loads_successfully(): void
    {
        $this->actingAs($this->dispatcher);

        Livewire::test('fleet.incidents-management')
            ->assertSet('routeFilter', 'all')
            ->assertSet('typeFilter', 'all')
            ->assertSet('statusFilter', 'all')
            ->assertSee('Active incidents')
            ->assertSee('No active incidents');
    }

    public function test_livewire_can_log_new_incident(): void
    {
        $this->actingAs($this->dispatcher);

        Livewire::test('fleet.incidents-management')
            ->call('openCreateModal')
            ->set('newTripId', $this->trip->id)
            ->set('newType', 'Breakdown')
            ->set('newDescription', 'Engine overheating at Shaw Blvd crossing.')
            ->set('newStatus', 'reported')
            ->call('saveIncident')
            ->assertHasNoErrors()
            ->assertDispatched('incident-logged');

        $this->assertDatabaseHas('incidents', [
            'trip_id' => $this->trip->id,
            'driver_id' => $this->driver->id,
            'type' => 'Breakdown',
            'description' => 'Engine overheating at Shaw Blvd crossing.',
            'status' => 'reported',
        ]);

        // Assert that the bus status was updated to maintenance because of the breakdown
        $this->bus->refresh();
        $this->assertEquals('maintenance', $this->bus->status);
    }

    public function test_livewire_validates_new_incident_fields(): void
    {
        $this->actingAs($this->dispatcher);

        Livewire::test('fleet.incidents-management')
            ->call('openCreateModal')
            ->set('newTripId', '')
            ->set('newType', '')
            ->set('newDescription', 'abcd')
            ->call('saveIncident')
            ->assertHasErrors(['newTripId', 'newType', 'newDescription']);
    }

    public function test_livewire_can_update_incident_status_and_resolve_breakdown(): void
    {
        $this->actingAs($this->dispatcher);

        // Create a breakdown incident first
        $incident = Incident::create([
            'trip_id' => $this->trip->id,
            'driver_id' => $this->driver->id,
            'type' => 'Breakdown',
            'description' => 'Flat tire on Route 1',
            'status' => 'reported',
            'reported_at' => now(),
        ]);
        $this->bus->update(['status' => 'maintenance']);

        // Test updating status to under investigation
        Livewire::test('fleet.incidents-management')
            ->call('updateIncidentStatus', $incident->id, 'under_review')
            ->assertDispatched('incident-updated');

        $incident->refresh();
        $this->assertEquals('under_review', $incident->status);
        $this->assertEquals('maintenance', $this->bus->fresh()->status);

        // Test resolving the incident
        Livewire::test('fleet.incidents-management')
            ->call('resolveIncident', $incident->id)
            ->assertDispatched('incident-updated');

        $incident->refresh();
        $this->assertEquals('resolved', $incident->status);

        // Associated bus should be active again
        $this->assertEquals('active', $this->bus->fresh()->status);
    }

    public function test_livewire_can_delete_incident(): void
    {
        $this->actingAs($this->dispatcher);

        $incident = Incident::create([
            'trip_id' => $this->trip->id,
            'driver_id' => $this->driver->id,
            'type' => 'Other',
            'description' => 'AC issue reported.',
            'status' => 'reported',
            'reported_at' => now(),
        ]);

        Livewire::test('fleet.incidents-management')
            ->call('deleteIncident', $incident->id)
            ->assertDispatched('incident-deleted');

        $this->assertDatabaseMissing('incidents', [
            'id' => $incident->id,
        ]);
    }

    public function test_overview_livewire_can_log_new_incident(): void
    {
        $this->actingAs($this->dispatcher);

        Livewire::test('App\Livewire\Fleet\Overview')
            ->set('newIncidentTitle', 'AC breakdown')
            ->set('newIncidentLocation', 'Ortigas Station')
            ->set('newIncidentRoute', (string) $this->route->id)
            ->set('newIncidentSeverity', 'High')
            ->call('submitIncident')
            ->assertHasNoErrors()
            ->assertDispatched('incidentLogged');

        $this->assertDatabaseHas('incidents', [
            'trip_id' => $this->trip->id,
            'driver_id' => $this->driver->id,
            'type' => 'Breakdown',
            'description' => 'AC breakdown at Ortigas Station',
            'status' => 'reported',
        ]);

        $this->bus->refresh();
        $this->assertEquals('maintenance', $this->bus->status);
    }

    public function test_overview_livewire_fails_validation_when_no_active_trip(): void
    {
        $this->actingAs($this->dispatcher);

        $inactiveRoute = Route::create([
            'id' => 2,
            'name' => 'Route 2',
            'description' => 'Pasig City Hall to BGC',
            'polyline_coordinates' => [[14.5580, 121.0750], [14.5500, 121.0500]],
            'status' => 'Active',
        ]);

        Livewire::test('App\Livewire\Fleet\Overview')
            ->set('newIncidentTitle', 'AC breakdown')
            ->set('newIncidentLocation', 'BGC Stop')
            ->set('newIncidentRoute', (string) $inactiveRoute->id)
            ->call('submitIncident')
            ->assertHasErrors(['newIncidentRoute' => 'Walang aktibong biyahe (ongoing trip) sa rutang ito sa kasalukuyan.'])
            ->assertNotDispatched('incidentLogged');
    }
}
