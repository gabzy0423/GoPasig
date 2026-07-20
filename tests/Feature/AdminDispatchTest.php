<?php
 
namespace Tests\Feature;
 
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
 
class AdminDispatchTest extends TestCase
{
    use RefreshDatabase;
 
    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        return $user;
    }
 
    public function test_admin_can_access_dispatch_dashboard(): void
    {
        $this->actingAsAdmin();
 
        $response = $this->get('/admin/dashboard');
 
        $response->assertStatus(200);
    }
 
    public function test_livewire_dispatch_builder_loads(): void
    {
        $this->actingAsAdmin();
 
        $route = Route::create([
            'name' => 'Route A',
            'description' => 'Test Route A Description',
            'polyline_coordinates' => [[14.5690, 121.0680]],
            'status' => 'Active',
        ]);
 
        $bus = Bus::create([
            'plate_number' => 'PAS-123',
            'status' => 'inactive',
            'capacity' => 40,
            'lat' => 14.5690,
            'lng' => 121.0680,
            'speed' => 0,
            'passengers' => 0,
        ]);
 
        $driver = Driver::create([
            'emp_id' => 'EMP-0021',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'license_number' => 'N01-23-456789',
            'license_expiry' => '2027-12-12',
            'status' => 'inactive',
        ]);
 
        Livewire::test('admin.dispatch-builder')
            ->assertSee('PAS-123')
            ->assertSee('Juan Dela Cruz');
    }
 
    public function test_admin_can_dispatch_bus_via_livewire(): void
    {
        $this->actingAsAdmin();
 
        $route = Route::create([
            'name' => 'Route A',
            'description' => 'Test Route A Description',
            'polyline_coordinates' => [[14.5690, 121.0680]],
            'status' => 'Active',
        ]);
 
        $bus = Bus::create([
            'plate_number' => 'PAS-123',
            'status' => 'available',
            'capacity' => 40,
            'lat' => 14.5690,
            'lng' => 121.0680,
            'speed' => 0,
            'passengers' => 0,
        ]);
 
        $driver = Driver::create([
            'emp_id' => 'EMP-0021',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'license_number' => 'N01-23-456789',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'operational_status' => 'available',
        ]);
 
        Livewire::test('admin.dispatch-builder')
            ->set('selectedRoute', $route->id)
            ->set('selectedBusId', $bus->id)
            ->set('selectedDriverId', $driver->id)
            ->set('departureTime', '08:00')
            ->set('confirmDispatch', true)
            ->call('createDispatch')
            ->assertDispatched('dispatchSuccessful');
 
        // Assert Bus is updated
        $bus->refresh();
        $this->assertSame('ready', $bus->status);
        $this->assertSame($route->id, $bus->route_id);
        $this->assertSame('Juan Dela Cruz', $bus->driver_name);
 
        // Assert Driver is updated
        $driver->refresh();
        $this->assertSame('active', $driver->status);
        $this->assertSame('assigned', $driver->operational_status);
        $this->assertSame('PAS-123', $driver->assigned_bus);
        $this->assertSame((string)$route->id, (string)$driver->assigned_route);
 
        // Assert Trip record is created in database
        $this->assertDatabaseHas('trips', [
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'dispatched',
        ]);
 
        // Assert Dispatch Log is created
        $trip = Trip::where('bus_id', $bus->id)->first();
        $this->assertDatabaseHas('dispatch_logs', [
            'trip_id' => $trip->id,
        ]);
 
        // Assert audit log entry was written by BusStateService
        $this->assertDatabaseHas('bus_status_audit_log', [
            'bus_id'     => $bus->id,
            'old_status' => 'available',
            'new_status' => 'ready',
            'reason'     => 'Central Dispatch',
        ]);
    }
 
    public function test_bus_transitions_to_inactive_when_trip_is_completed_by_driver(): void
    {
        $user = User::factory()->create(['role' => 'driver']);
        $this->actingAs($user);
 
        $route = Route::create([
            'name' => 'Route A',
            'description' => 'Test Route A Description',
            'polyline_coordinates' => [[14.5690, 121.0680]],
            'status' => 'Active',
        ]);
 
        $driver = Driver::create([
            'user_id' => $user->id,
            'emp_id' => 'EMP-0021',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'license_number' => 'N01-23-456789',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'assigned_bus' => 'PAS-123',
            'assigned_route' => (string) $route->id,
        ]);
 
        $bus = Bus::create([
            'plate_number' => 'PAS-123',
            'status' => 'operating',
            'capacity' => 40,
            'lat' => 14.5690,
            'lng' => 121.0680,
            'speed' => 0,
            'passengers' => 0,
        ]);
 
        $trip = Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now(),
        ]);
 
        $response = $this->postJson('/driver/trip/toggle', [
            'status' => 'inactive',
        ]);
 
        $response->assertStatus(200);
 
        $bus->refresh();
        $this->assertSame('available', $bus->status);
 
        $trip->refresh();
        $this->assertSame('completed', $trip->status);
        $this->assertNotNull($trip->ended_at);
 
        // Assert audit log entry was written by BusStateService
        $this->assertDatabaseHas('bus_status_audit_log', [
            'bus_id'     => $bus->id,
            'new_status' => 'available',
            'reason'     => 'Driver completed trip',
        ]);
    }
 
    public function test_bus_transitions_to_breakdown_and_trip_is_cancelled_on_breakdown_report(): void
    {
        $user = User::factory()->create(['role' => 'driver']);
        $this->actingAs($user);
 
        $route = Route::create([
            'name' => 'Route A',
            'description' => 'Test Route A Description',
            'polyline_coordinates' => [[14.5690, 121.0680]],
            'status' => 'Active',
        ]);
 
        $driver = Driver::create([
            'user_id' => $user->id,
            'emp_id' => 'EMP-0021',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'license_number' => 'N01-23-456789',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'assigned_bus' => 'PAS-123',
            'assigned_route' => (string) $route->id,
        ]);
 
        $bus = Bus::create([
            'plate_number' => 'PAS-123',
            'status' => 'operating',
            'capacity' => 40,
            'lat' => 14.5690,
            'lng' => 121.0680,
            'speed' => 0,
            'passengers' => 0,
        ]);
 
        $trip = Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now(),
        ]);
 
        // Mock breakdown setting
        \App\Models\SystemSetting::where('key', 'incident_breakdown_type')->update(['value' => 'Breakdown']);
 
        $response = $this->postJson('/driver/trip/incident', [
            'type' => 'Breakdown',
            'description' => 'Engine failure on Shaw Blvd.',
        ]);
 
        $response->assertStatus(200);
 
        $bus->refresh();
        $this->assertSame('breakdown', $bus->status);
 
        $trip->refresh();
        $this->assertSame('cancelled', $trip->status);
        $this->assertNotNull($trip->ended_at);
 
        $driver->refresh();
        $this->assertSame('active', $driver->status);
        $this->assertSame('unavailable', $driver->operational_status);
        $this->assertSame('PAS-123', $driver->assigned_bus);
        $this->assertSame((string) $route->id, $driver->assigned_route);
 
        // Assert audit log entry was written by BusStateService
        $this->assertDatabaseHas('bus_status_audit_log', [
            'bus_id'     => $bus->id,
            'old_status' => 'operating',
            'new_status' => 'breakdown',
            'reason'     => 'Incident report: breakdown',
        ]);
    }
 
    public function test_defensive_label_logic_shows_needs_review_on_inconsistent_bus(): void
    {
        $this->actingAsAdmin();
 
        $bus = Bus::create([
            'plate_number' => 'PAS-999',
            'status' => 'active', // Inconsistent: active but no ongoing trip!
            'capacity' => 40,
            'lat' => 14.5690,
            'lng' => 121.0680,
            'speed' => 0,
            'passengers' => 0,
        ]);
 
        Livewire::test('admin.dispatch-builder')
            ->assertSee('PAS-999')
            ->assertSee('Needs Review');
    }
 
    public function test_confirmation_checkbox_is_enforced(): void
    {
        $this->actingAsAdmin();
        $route = Route::create(['name' => 'Route A', 'polyline_coordinates' => [[14.5690, 121.0680]], 'status' => 'Active']);
        $bus = Bus::create(['plate_number' => 'PAS-123', 'status' => 'available', 'capacity' => 40, 'lat' => 14.5690, 'lng' => 121.0680, 'speed' => 0, 'passengers' => 0]);
        $driver = Driver::create(['emp_id' => 'EMP-0021', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'license_number' => 'N01-23-456789', 'license_expiry' => '2027-12-12', 'status' => 'active', 'operational_status' => 'available']);
 
        Livewire::test('admin.dispatch-builder')
            ->set('selectedRoute', $route->id)
            ->set('selectedBusId', $bus->id)
            ->set('selectedDriverId', $driver->id)
            ->set('departureTime', '08:00')
            ->set('confirmDispatch', false) // checkbox not checked
            ->call('createDispatch')
            ->assertHasErrors(['confirmDispatch'])
            ->assertNotDispatched('dispatchSuccessful');
    }
 
    public function test_search_and_filters_filter_resources_correctly(): void
    {
        $this->actingAsAdmin();
        Bus::create(['plate_number' => 'PAS-111', 'status' => 'available', 'capacity' => 40, 'lat' => 14.5690, 'lng' => 121.0680]);
        Bus::create(['plate_number' => 'PAS-222', 'status' => 'available', 'capacity' => 40, 'lat' => 14.5690, 'lng' => 121.0680]);
 
        Driver::create(['emp_id' => 'EMP-001', 'first_name' => 'Juan', 'last_name' => 'Cruz', 'license_number' => 'N01-23-456789', 'license_expiry' => '2027-12-12', 'status' => 'active', 'operational_status' => 'available']);
        Driver::create(['emp_id' => 'EMP-002', 'first_name' => 'Pedro', 'last_name' => 'Santos', 'license_number' => 'N01-23-456780', 'license_expiry' => '2027-12-12', 'status' => 'active', 'operational_status' => 'available']);
 
        $component = Livewire::test('admin.dispatch-builder')
            ->set('busSearch', '111');
 
        $buses = $component->get('availableBuses');
        $this->assertCount(1, $buses);
        $this->assertSame('PAS-111', $buses[0]['plate']);
 
        $component->set('driverSearch', 'Pedro');
        $drivers = $component->get('availableDrivers');
        $this->assertCount(1, $drivers);
        $this->assertSame('Pedro Santos', $drivers[0]['name']);
    }
 
    public function test_empty_states_render_when_no_resources_available(): void
    {
        $this->actingAsAdmin();
        Bus::query()->delete();
        Driver::query()->delete();
 
        Livewire::test('admin.dispatch-builder')
            ->assertSee('No standby buses available')
            ->assertSee('No standby drivers available');
    }
 
    public function test_auto_refresh_invalidates_stale_selections(): void
    {
        $this->actingAsAdmin();
        $route = Route::create(['name' => 'Route A', 'polyline_coordinates' => [[14.5690, 121.0680]], 'status' => 'Active']);
        $bus = Bus::create(['plate_number' => 'PAS-123', 'status' => 'available', 'capacity' => 40, 'lat' => 14.5690, 'lng' => 121.0680]);
        $driver = Driver::create(['emp_id' => 'EMP-0021', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'license_number' => 'N01-23-456789', 'license_expiry' => '2027-12-12', 'status' => 'active', 'operational_status' => 'available']);
 
        $component = Livewire::test('admin.dispatch-builder')
            ->set('selectedRoute', $route->id)
            ->set('selectedBusId', $bus->id)
            ->set('selectedDriverId', $driver->id);
 
        // Put bus on maintenance, making it unavailable
        $bus->update(['status' => 'maintenance']);
 
        // Refresh data should invalidate selectedBusId
        $component->call('refreshDispatchData')
            ->assertSet('selectedBusId', '')
            ->assertHasErrors(['selectedBusId']);
 
        // Suspend driver
        $driver->update(['status' => 'suspended']);
        $component->set('selectedDriverId', $driver->id)
            ->call('refreshDispatchData')
            ->assertSet('selectedDriverId', '')
            ->assertHasErrors(['selectedDriverId']);
    }
 
    public function test_keyboard_accessibility_and_focus_management_markup(): void
    {
        $this->actingAsAdmin();
        Livewire::test('admin.dispatch-builder')
            ->assertSeeHtml('tabindex="1"')
            ->assertSeeHtml('tabindex="2"')
            ->assertSeeHtml('tabindex="3"')
            ->assertSeeHtml('tabindex="4"')
            ->assertSeeHtml('tabindex="5"')
            ->assertSeeHtml('tabindex="6"')
            ->assertSeeHtml('aria-required="true"');
    }
 
    public function test_loading_state_prevents_duplicate_submission_markup(): void
    {
        $this->actingAsAdmin();
        Livewire::test('admin.dispatch-builder')
            ->assertSeeHtml('wire:loading.attr="disabled"')
            ->assertSeeHtml('wire:loading');
    }
 
    public function test_success_flow_resets_the_form(): void
    {
        $this->actingAsAdmin();
        $route = Route::create(['name' => 'Route A', 'polyline_coordinates' => [[14.5690, 121.0680]], 'status' => 'Active']);
        $bus = Bus::create(['plate_number' => 'PAS-123', 'status' => 'available', 'capacity' => 40, 'lat' => 14.5690, 'lng' => 121.0680, 'speed' => 0, 'passengers' => 0]);
        $driver = Driver::create(['emp_id' => 'EMP-0021', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'license_number' => 'N01-23-456789', 'license_expiry' => '2027-12-12', 'status' => 'active', 'operational_status' => 'available']);
 
        Livewire::test('admin.dispatch-builder')
            ->set('selectedRoute', $route->id)
            ->set('selectedBusId', $bus->id)
            ->set('selectedDriverId', $driver->id)
            ->set('departureTime', '08:00')
            ->set('confirmDispatch', true)
            ->call('createDispatch')
            ->assertDispatched('dispatchSuccessful')
            ->assertSet('selectedRoute', '')
            ->assertSet('selectedBusId', '')
            ->assertSet('selectedDriverId', '')
            ->assertSet('departureTime', '')
            ->assertSet('confirmDispatch', false);
    }
 
    public function test_user_friendly_exception_messages_caught(): void
    {
        $this->actingAsAdmin();
        $route = Route::create(['name' => 'Route A', 'polyline_coordinates' => [[14.5690, 121.0680]], 'status' => 'Active']);
        $bus = Bus::create(['plate_number' => 'PAS-123', 'status' => 'operating', 'capacity' => 40, 'lat' => 14.5690, 'lng' => 121.0680, 'speed' => 0, 'passengers' => 0]);
        $driver = Driver::create(['emp_id' => 'EMP-0021', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'license_number' => 'N01-23-456789', 'license_expiry' => '2027-12-12', 'status' => 'active', 'operational_status' => 'available']);
 
        // Bus is already active, so SimulationDispatchService will throw a DispatchException
        Livewire::test('admin.dispatch-builder')
            ->set('selectedRoute', $route->id)
            ->set('selectedBusId', $bus->id)
            ->set('selectedDriverId', $driver->id)
            ->set('departureTime', '08:00')
            ->set('confirmDispatch', true)
            ->call('createDispatch')
            ->assertHasErrors(['dispatchError']);
    }
}
