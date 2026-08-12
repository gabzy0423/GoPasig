<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\User;
use App\Models\Route;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\TripLog;
use App\Models\Schedule;
use App\Models\SystemSetting;
use App\Services\BusStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminBusManagementAuditFixTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    #[Test]
    public function test_bus_store_respects_settings_defaults(): void
    {
        $this->actingAsAdmin();

        SystemSetting::updateOrCreate(['key' => 'bus_default_driver_name'], ['value' => 'No Driver']);
        SystemSetting::updateOrCreate(['key' => 'bus_default_next_stop'], ['value' => 'Terminal Pending']);
        SystemSetting::updateOrCreate(['key' => 'bus_initial_speed'], ['value' => '3']);
        SystemSetting::updateOrCreate(['key' => 'bus_initial_passengers'], ['value' => '4']);
        SystemSetting::updateOrCreate(['key' => 'bus_initial_eta'], ['value' => '5']);

        $response = $this->postJson(route('admin.api.buses.store'), [
            'plate_number'          => 'PAS-201',
            'fleet_number'          => 'BUS-201',
            'vin'                   => '1234567890ABCDEF1',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.00,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'driver_name'           => null,
            'capacity'              => 45,
            'status'                => 'inactive',
            'route_id'              => null,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('buses', [
            'plate_number' => 'PAS-201',
            'driver_name' => 'No Driver',
            'next_stop' => 'Terminal Pending',
            'speed' => 3,
            'passengers' => 4,
            'eta' => 5,
        ]);
    }

    #[Test]
    public function inactive_bus_can_transition_directly_to_maintenance(): void
    {
        $bus = Bus::factory()->create(['status' => 'inactive']);

        $this->assertTrue(BusStateService::canTransition('inactive', 'maintenance'));

        BusStateService::transition($bus, 'maintenance', 'Audit regression test');

        $this->assertSame('maintenance', $bus->fresh()->status);
        $this->assertSame('inactive', $bus->fresh()->previous_status);
    }

    #[Test]
    public function bus_update_persists_status_and_profile_fields_together(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::factory()->create([
            'plate_number' => 'PAS-202',
            'driver_name' => 'Old Driver',
            'status' => 'breakdown',
            'capacity' => 45,
        ]);

        $response = $this->putJson(route('admin.api.buses.update', $bus), [
            'fleet_number'          => 'BUS-202',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.00,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'driver_name'           => 'Old Driver',
            'capacity'              => 55,
            'route_id'              => null,
            'status'                => 'maintenance',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('buses', [
            'id' => $bus->id,
            'capacity' => 55,
            'status' => 'maintenance',
            'previous_status' => 'breakdown',
        ]);
    }

    #[Test]
    public function bus_with_ongoing_trip_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();

        $route = Route::factory()->create(['name' => 'Route 2', 'status' => 'Active']);
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create(['status' => 'active']);

        Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
        ]);

        $response = $this->deleteJson(route('admin.api.buses.destroy', $bus));

        $response->assertStatus(422);
        $this->assertDatabaseHas('buses', ['id' => $bus->id]);
    }

    #[Test]
    public function bus_with_active_schedule_assignment_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();

        $route = Route::factory()->create(['name' => 'Route 2', 'status' => 'Active']);
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create(['status' => 'active']);

        Schedule::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $response = $this->deleteJson(route('admin.api.buses.destroy', $bus));

        $response->assertStatus(422);
        $this->assertDatabaseHas('buses', ['id' => $bus->id]);
    }

    #[Test]
    public function test_valid_manual_transitions_via_controller(): void
    {
        $this->actingAsAdmin();

        // 1. Inactive -> Active is prohibited manually (should return 422)
        $bus = Bus::factory()->create(['status' => Bus::STATUS_INACTIVE]);
        $driver = Driver::factory()->create(['status' => 'inactive']);
        $route = Route::factory()->create(['name' => 'Route 2', 'status' => 'Active']);

        $response = $this->putJson(route('admin.api.buses.update', $bus), [
            'fleet_number'          => $bus->fleet_number,
            'manufacturer'          => $bus->manufacturer,
            'model'                 => $bus->model,
            'year_model'            => $bus->year_model,
            'battery_capacity_kwh'  => $bus->battery_capacity_kwh,
            'charging_port_type'    => $bus->charging_port_type,
            'max_charging_power_kw' => $bus->max_charging_power_kw,
            'capacity'              => $bus->capacity,
            'driver_name'           => $driver->first_name . ' ' . $driver->last_name,
            'route_id'              => $route->id,
            'status'                => Bus::STATUS_ACTIVE,
        ]);

        $response->assertStatus(422);

        // 2. Active -> Breakdown (Valid manual transition)
        $activeBus = Bus::factory()->create([
            'status' => Bus::STATUS_ACTIVE,
            'driver_name' => $driver->first_name . ' ' . $driver->last_name,
            'route_id' => $route->id,
        ]);
        $driver->update([
            'status' => 'active',
            'assigned_bus' => $activeBus->plate_number,
            'assigned_route' => $route->id,
        ]);

        $response = $this->putJson(route('admin.api.buses.update', $activeBus), [
            'fleet_number'          => $activeBus->fleet_number,
            'manufacturer'          => $activeBus->manufacturer,
            'model'                 => $activeBus->model,
            'year_model'            => $activeBus->year_model,
            'battery_capacity_kwh'  => $activeBus->battery_capacity_kwh,
            'charging_port_type'    => $activeBus->charging_port_type,
            'max_charging_power_kw' => $activeBus->max_charging_power_kw,
            'capacity'              => $activeBus->capacity,
            'driver_name'           => $driver->first_name . ' ' . $driver->last_name,
            'route_id'              => $route->id,
            'status'                => Bus::STATUS_BREAKDOWN,
        ]);

        $response->assertOk();
        $this->assertSame(Bus::STATUS_BREAKDOWN, $activeBus->fresh()->status);
        $this->assertSame('active', $driver->fresh()->status); // Account status remains active
        $this->assertSame('unavailable', $driver->fresh()->operational_status); // Operationally deactivated but assignment preserved

        // 3. Breakdown -> Maintenance
        $response = $this->putJson(route('admin.api.buses.update', $activeBus), [
            'fleet_number'          => $activeBus->fleet_number,
            'manufacturer'          => $activeBus->manufacturer,
            'model'                 => $activeBus->model,
            'year_model'            => $activeBus->year_model,
            'battery_capacity_kwh'  => $activeBus->battery_capacity_kwh,
            'charging_port_type'    => $activeBus->charging_port_type,
            'max_charging_power_kw' => $activeBus->max_charging_power_kw,
            'capacity'              => $activeBus->capacity,
            'driver_name'           => $driver->first_name . ' ' . $driver->last_name,
            'route_id'              => $route->id,
            'status'                => Bus::STATUS_MAINTENANCE,
        ]);

        $response->assertOk();
        $this->assertSame(Bus::STATUS_MAINTENANCE, $activeBus->fresh()->status);

        // 4. Maintenance -> Inactive
        $response = $this->putJson(route('admin.api.buses.update', $activeBus), [
            'fleet_number'          => $activeBus->fleet_number,
            'manufacturer'          => $activeBus->manufacturer,
            'model'                 => $activeBus->model,
            'year_model'            => $activeBus->year_model,
            'battery_capacity_kwh'  => $activeBus->battery_capacity_kwh,
            'charging_port_type'    => $activeBus->charging_port_type,
            'max_charging_power_kw' => $activeBus->max_charging_power_kw,
            'capacity'              => $activeBus->capacity,
            'driver_name'           => Bus::DEFAULT_DRIVER_NAME,
            'route_id'              => null,
            'status'                => Bus::STATUS_INACTIVE,
        ]);

        $response->assertOk();
        $this->assertSame(Bus::STATUS_INACTIVE, $activeBus->fresh()->status);
    }

    #[Test]
    public function test_invalid_transitions_are_rejected(): void
    {
        $this->actingAsAdmin();

        // Breakdown -> Active (Invalid transition, should fail)
        $bus = Bus::factory()->create(['status' => Bus::STATUS_BREAKDOWN]);
        $driver = Driver::factory()->create();
        $route = Route::factory()->create(['name' => 'Route 2', 'status' => 'Active']);

        $response = $this->putJson(route('admin.api.buses.update', $bus), [
            'fleet_number'          => $bus->fleet_number,
            'manufacturer'          => $bus->manufacturer,
            'model'                 => $bus->model,
            'year_model'            => $bus->year_model,
            'battery_capacity_kwh'  => $bus->battery_capacity_kwh,
            'charging_port_type'    => $bus->charging_port_type,
            'max_charging_power_kw' => $bus->max_charging_power_kw,
            'capacity'              => $bus->capacity,
            'driver_name'           => $driver->first_name . ' ' . $driver->last_name,
            'route_id'              => $route->id,
            'status'                => Bus::STATUS_ACTIVE,
        ]);
        $response->assertStatus(422);

        // Active -> Maintenance (Rejected manually, should fail)
        $bus2 = Bus::factory()->create(['status' => Bus::STATUS_ACTIVE, 'driver_name' => $driver->first_name . ' ' . $driver->last_name, 'route_id' => $route->id]);
        $response2 = $this->putJson(route('admin.api.buses.update', $bus2), [
            'fleet_number'          => $bus2->fleet_number,
            'manufacturer'          => $bus2->manufacturer,
            'model'                 => $bus2->model,
            'year_model'            => $bus2->year_model,
            'battery_capacity_kwh'  => $bus2->battery_capacity_kwh,
            'charging_port_type'    => $bus2->charging_port_type,
            'max_charging_power_kw' => $bus2->max_charging_power_kw,
            'capacity'              => $bus2->capacity,
            'driver_name'           => $driver->first_name . ' ' . $driver->last_name,
            'route_id'              => $route->id,
            'status'                => Bus::STATUS_MAINTENANCE,
        ]);
        $response2->assertStatus(422);
    }

    #[Test]
    public function test_active_to_active_reassignment(): void
    {
        $this->actingAsAdmin();

        $route1 = Route::factory()->create(['name' => 'Route 2', 'status' => 'Active']);
        $route2 = Route::factory()->create(['name' => 'Route 3', 'status' => 'Active']);
        $driver1 = Driver::factory()->create(['status' => 'inactive']);
        $driver2 = Driver::factory()->create(['status' => 'inactive']);

        $bus = Bus::factory()->create([
            'status' => Bus::STATUS_ACTIVE,
            'driver_name' => $driver1->first_name . ' ' . $driver1->last_name,
            'route_id' => $route1->id,
        ]);
        $driver1->update(['status' => 'active', 'assigned_bus' => $bus->plate_number, 'assigned_route' => $route1->id]);

        // Reassign driver and route keeping active status
        $response = $this->putJson(route('admin.api.buses.update', $bus), [
            'fleet_number'          => $bus->fleet_number,
            'manufacturer'          => $bus->manufacturer,
            'model'                 => $bus->model,
            'year_model'            => $bus->year_model,
            'battery_capacity_kwh'  => $bus->battery_capacity_kwh,
            'charging_port_type'    => $bus->charging_port_type,
            'max_charging_power_kw' => $bus->max_charging_power_kw,
            'capacity'              => $bus->capacity,
            'driver_name'           => $driver2->first_name . ' ' . $driver2->last_name,
            'route_id'              => $route2->id,
            'status'                => Bus::STATUS_ACTIVE,
        ]);

        $response->assertOk();
        $bus->refresh();
        $this->assertSame($driver2->first_name . ' ' . $driver2->last_name, $bus->driver_name);
        $this->assertEquals($route2->id, $bus->route_id);

        $driver1->refresh();
        $this->assertSame('active', $driver1->status); // Account status remains active
        $this->assertSame('available', $driver1->operational_status); // Operationally freed
        $this->assertNull($driver1->assigned_bus);

        $driver2->refresh();
        $this->assertSame('active', $driver2->status);
        $this->assertSame($bus->plate_number, $driver2->assigned_bus);
    }

    #[Test]
    public function test_transaction_rollback_safety(): void
    {
        $this->actingAsAdmin();

        $route = Route::factory()->create(['name' => 'Route 2', 'status' => 'Active']);
        $driver = Driver::factory()->create(['status' => 'active']);
        $bus = Bus::factory()->create([
            'status' => Bus::STATUS_ACTIVE,
            'plate_number' => 'PLATE111',
            'driver_name' => $driver->first_name . ' ' . $driver->last_name,
            'route_id' => $route->id,
        ]);

        $trip = Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
        ]);

        $schedule = Schedule::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'scheduled',
        ]);

        // Mock Log facade to throw exception inside transaction to force rollback
        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->andThrow(new \RuntimeException('Forced rollback'));

        try {
            BusStateService::transition($bus, Bus::STATUS_INACTIVE, 'Transition error test');
            $this->fail('Transaction should have thrown RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('Forced rollback', $e->getMessage());
        }

        // Assert: database is rolled back and no partial updates remain
        $bus->refresh();
        $driver->refresh();
        $trip->refresh();
        $schedule->refresh();

        $this->assertSame(Bus::STATUS_ACTIVE, $bus->status);
        $this->assertSame($driver->first_name . ' ' . $driver->last_name, $bus->driver_name);
        $this->assertSame('active', $driver->status);
        $this->assertSame('ongoing', $trip->status);
        $this->assertSame('scheduled', $schedule->status);
    }

    #[Test]
    public function bus_in_maintenance_can_transition_to_breakdown(): void
    {
        $bus = Bus::factory()->create(['status' => 'maintenance', 'previous_status' => 'breakdown']);

        $this->assertTrue(BusStateService::canTransition('maintenance', 'breakdown'));

        BusStateService::transition($bus, 'breakdown', 'Test transition from maintenance to breakdown');

        $this->assertSame('breakdown', $bus->fresh()->status);
    }

    #[Test]
    public function status_transition_finalizes_ongoing_and_dispatched_trips_in_trip_logs(): void
    {
        $bus = Bus::factory()->create(['status' => 'operating']);
        $driver = Driver::factory()->create([
            'status' => 'active',
            'operational_status' => 'driving',
            'assigned_bus' => $bus->plate_number,
        ]);
        $route = Route::factory()->create(['name' => 'Route 2', 'status' => 'Active']);

        $ongoingTrip = Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'started_at' => now()->subMinutes(20),
        ]);
        $dispatchedTrip = Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'dispatched',
            'started_at' => null,
            'dispatched_at' => now()->subMinutes(5),
            'gps_session' => 'OFF',
        ]);

        BusStateService::transition($bus, Bus::STATUS_BREAKDOWN, 'Finalize active trip logs');

        $ongoingTrip->refresh();
        $dispatchedTrip->refresh();

        $this->assertSame('cancelled', $ongoingTrip->status);
        $this->assertSame('cancelled', $dispatchedTrip->status);
        $this->assertNotNull($ongoingTrip->ended_at);
        $this->assertNotNull($dispatchedTrip->ended_at);
        $this->assertSame(1, TripLog::where('trip_id', $ongoingTrip->id)->count());
        $this->assertSame(1, TripLog::where('trip_id', $dispatchedTrip->id)->count());
        $this->assertDatabaseHas('trip_logs', [
            'trip_id' => $ongoingTrip->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('trip_logs', [
            'trip_id' => $dispatchedTrip->id,
            'status' => 'cancelled',
        ]);
    }

    #[Test]
    public function bus_management_writes_synchronize_fleet_data_and_paginated_listing(): void
    {
        $busScript = file_get_contents(public_path('js/admin-dashboard/buses.js'));
        $dashboardScript = file_get_contents(public_path('js/admin-dashboard/dashboard-data.js'));
        $busView = file_get_contents(resource_path('views/admin/bus/index.blade.php'));

        $this->assertStringContainsString('async function loadDatabaseFleetData(options = {})', $dashboardScript);
        $this->assertStringContainsString('if (options.renderBusTable !== false)', $dashboardScript);
        $this->assertStringContainsString('async function refreshBusManagementState()', $busScript);
        $this->assertStringContainsString('Promise.all([', $busScript);
        $this->assertStringContainsString('loadDatabaseFleetData({ renderBusTable: false })', $busScript);
        $this->assertStringContainsString('fetchBuses()', $busScript);
        $this->assertStringContainsString('onclick="refreshBusManagementState(); return false;"', $busView);

        $submitStart = strpos($busScript, 'async function handleBusSubmit(event)');
        $submitEnd = strpos($busScript, '// AJAX Bus Delete', $submitStart);
        $submitBody = substr($busScript, $submitStart, $submitEnd - $submitStart);

        $this->assertStringContainsString('await refreshBusManagementState();', $submitBody);
        $this->assertStringNotContainsString('await loadDatabaseFleetData();', $submitBody);

        $deleteStart = strpos($busScript, 'async function deleteBus(busId)');
        $deleteEnd = strpos($busScript, '// Automatically render on DOM load', $deleteStart);
        $deleteBody = substr($busScript, $deleteStart, $deleteEnd - $deleteStart);

        $this->assertStringContainsString('await refreshBusManagementState();', $deleteBody);
        $this->assertStringNotContainsString('await loadDatabaseFleetData();', $deleteBody);
    }

    #[Test]
    public function bus_management_edit_lookup_prefers_paginated_bus_records_and_guards_stale_responses(): void
    {
        $busScript = file_get_contents(public_path('js/admin-dashboard/buses.js'));

        $this->assertStringContainsString('let latestBusListRequestSeq = 0;', $busScript);
        $this->assertStringContainsString('const requestSeq = ++latestBusListRequestSeq;', $busScript);
        $this->assertStringContainsString('if (requestSeq !== latestBusListRequestSeq) return;', $busScript);
        $this->assertStringContainsString('function findCurrentBusRecord(busId)', $busScript);
        $this->assertStringContainsString('const listRecord = globalBusesRecords.find', $busScript);
        $this->assertStringContainsString('const fleetRecord = fleetData.find', $busScript);
        $this->assertStringContainsString('normalizeBusRecordForForm(listRecord)', $busScript);
        $this->assertStringContainsString('normalizeBusRecordForForm(fleetRecord)', $busScript);
        $this->assertStringContainsString('const bus = findCurrentBusRecord(busId);', $busScript);

        $lookupStart = strpos($busScript, 'function findCurrentBusRecord(busId)');
        $lookupEnd = strpos($busScript, 'async function refreshBusManagementState()', $lookupStart);
        $lookupBody = substr($busScript, $lookupStart, $lookupEnd - $lookupStart);

        $this->assertLessThan(
            strpos($lookupBody, 'const fleetRecord = fleetData.find'),
            strpos($lookupBody, 'const listRecord = globalBusesRecords.find')
        );
    }
}
