<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Route;
use App\Models\Bus;
use App\Models\MaintenanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FleetMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private $dispatcher;
    private $route;
    private $bus;

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
            'route_id' => $this->route->id,
        ]);
    }

    public function test_dispatcher_can_access_fleet_maintenance(): void
    {
        $response = $this->actingAs($this->dispatcher)->get('/fleet/maintenance');
        $response->assertStatus(200);
        $response->assertSeeLivewire('fleet.maintenance-management');
    }

    public function test_unauthorized_users_cannot_access_fleet_maintenance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get('/fleet/maintenance');
        $response->assertStatus(403);

        $driver = User::factory()->create(['role' => 'driver']);
        $response = $this->actingAs($driver)->get('/fleet/maintenance');
        $response->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/fleet/maintenance');
        $response->assertRedirect('/login');
    }

    public function test_livewire_component_loads_successfully(): void
    {
        $this->actingAs($this->dispatcher);

        Livewire::test('fleet.maintenance-management')
            ->assertSet('logTypeFilter', 'all')
            ->assertSet('logStatusFilter', 'all')
            ->assertSee('Fleet health matrix')
            ->assertSee('Maintenance log');
    }

    public function test_livewire_can_schedule_maintenance(): void
    {
        $this->actingAs($this->dispatcher);

        $scheduleTime = now()->addDays(2)->format('Y-m-d\TH:i');

        Livewire::test('fleet.maintenance-management')
            ->call('openScheduleModal')
            ->set('selectedBusId', $this->bus->id)
            ->set('maintenanceType', 'Preventive')
            ->set('maintenanceDescription', 'Regular 10k KM Engine Checkup.')
            ->set('scheduledAt', $scheduleTime)
            ->set('technicianName', 'Dave Mechanic')
            ->set('costPhp', '2500.50')
            ->set('formStatus', 'scheduled')
            ->call('saveMaintenanceSchedule')
            ->assertHasNoErrors()
            ->assertDispatched('maintenance-saved');

        $this->assertDatabaseHas('maintenance_records', [
            'bus_id' => $this->bus->id,
            'type' => 'Preventive',
            'description' => 'Regular 10k KM Engine Checkup.',
            'technician_name' => 'Dave Mechanic',
            'cost_php' => 2500.50,
            'status' => 'scheduled',
        ]);

        $this->assertEquals('active', $this->bus->fresh()->status);
    }

    public function test_livewire_validates_required_fields(): void
    {
        $this->actingAs($this->dispatcher);

        Livewire::test('fleet.maintenance-management')
            ->call('openScheduleModal')
            ->set('selectedBusId', '')
            ->set('maintenanceDescription', 'shrt')
            ->set('scheduledAt', '')
            ->call('saveMaintenanceSchedule')
            ->assertHasErrors(['selectedBusId', 'maintenanceDescription', 'scheduledAt']);
    }

    public function test_livewire_status_transitions_trigger_bus_status_updates(): void
    {
        $this->actingAs($this->dispatcher);

        $record = MaintenanceRecord::create([
            'bus_id' => $this->bus->id,
            'type' => 'Corrective',
            'description' => 'Replace broken headlights.',
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        // 1. Move to In Progress -> Bus goes to maintenance status
        Livewire::test('fleet.maintenance-management')
            ->call('updateRecordStatus', $record->id, 'in_progress')
            ->assertDispatched('maintenance-updated');

        $this->assertEquals('in_progress', $record->fresh()->status);
        $this->assertEquals('maintenance', $this->bus->fresh()->status);

        // 2. Complete Service -> Bus goes back to active status
        Livewire::test('fleet.maintenance-management')
            ->call('updateRecordStatus', $record->id, 'completed')
            ->assertDispatched('maintenance-updated');

        $this->assertEquals('completed', $record->fresh()->status);
        $this->assertEquals('active', $this->bus->fresh()->status);
    }

    public function test_livewire_can_delete_maintenance_record(): void
    {
        $this->actingAs($this->dispatcher);

        $record = MaintenanceRecord::create([
            'bus_id' => $this->bus->id,
            'type' => 'Inspection',
            'description' => 'Annual emission check.',
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        Livewire::test('fleet.maintenance-management')
            ->call('deleteRecord', $record->id)
            ->assertDispatched('maintenance-deleted');

        $this->assertDatabaseMissing('maintenance_records', [
            'id' => $record->id,
        ]);
    }
}
