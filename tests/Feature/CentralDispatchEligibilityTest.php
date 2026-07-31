<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Trip;
use App\Models\User;
use App\Services\BusStateService;
use App\Services\CentralDispatchEligibilityService;
use App\Services\SimulationDispatchService;
use App\Services\TripLifecycleService;
use App\Validators\BusDispatchValidator;
use App\Validators\DriverDispatchValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CentralDispatchEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ready_bus_with_retained_assignment_is_not_in_central_dispatch_pool(): void
    {
        $this->actingAsAdmin();
        [$bus, $driver, $route] = $this->retainedAssignment('ready', 'assigned');

        $component = Livewire::test('admin.dispatch-builder');

        $this->assertNotContains($bus->id, collect($component->get('availableBuses'))->pluck('id')->all());
        $this->assertFalse(collect($component->get('allBuses'))->firstWhere('id', $bus->id)['selectable']);
        $this->assertNotContains($driver->id, collect($component->get('availableDrivers'))->pluck('id')->all());
        $this->assertFalse(collect($component->get('allDrivers'))->firstWhere('id', $driver->id)['selectable']);
    }

    public function test_operating_bus_is_not_selectable(): void
    {
        $this->actingAsAdmin();
        [$bus] = $this->retainedAssignment('operating', 'driving');

        $component = Livewire::test('admin.dispatch-builder');

        $this->assertFalse(collect($component->get('allBuses'))->firstWhere('id', $bus->id)['selectable']);
    }

    public function test_legacy_available_bus_is_not_selectable_even_without_assignment(): void
    {
        $this->actingAsAdmin();
        $bus = Bus::factory()->create(['status' => 'available', 'route_id' => null, 'driver_name' => Bus::DEFAULT_DRIVER_NAME]);

        $component = Livewire::test('admin.dispatch-builder');

        $this->assertNotContains($bus->id, collect($component->get('availableBuses'))->pluck('id')->all());
        $this->assertFalse(collect($component->get('allBuses'))->firstWhere('id', $bus->id)['selectable']);
    }

    public function test_assigned_or_retained_assignment_drivers_are_not_selectable(): void
    {
        $this->actingAsAdmin();
        $route = Route::factory()->create();
        $assigned = Driver::factory()->create(['status' => 'active', 'operational_status' => 'assigned']);
        $retained = Driver::factory()->create([
            'status' => 'active',
            'operational_status' => 'available',
            'assigned_bus' => 'PAS-RETAINED',
            'assigned_route' => (string) $route->id,
        ]);
        $inactive = Driver::factory()->create(['status' => 'inactive', 'operational_status' => 'available']);

        $component = Livewire::test('admin.dispatch-builder');
        $allDrivers = collect($component->get('allDrivers'));

        $this->assertFalse($allDrivers->firstWhere('id', $assigned->id)['selectable']);
        $this->assertFalse($allDrivers->firstWhere('id', $retained->id)['selectable']);
        $this->assertFalse($allDrivers->firstWhere('id', $inactive->id)['selectable']);
        $this->assertNotContains($assigned->id, collect($component->get('availableDrivers'))->pluck('id')->all());
        $this->assertNotContains($retained->id, collect($component->get('availableDrivers'))->pluck('id')->all());
        $this->assertNotContains($inactive->id, collect($component->get('availableDrivers'))->pluck('id')->all());
    }

    public function test_truly_free_bus_and_driver_are_selectable(): void
    {
        $this->actingAsAdmin();
        $bus = Bus::factory()->create(['status' => 'inactive', 'route_id' => null, 'driver_name' => Bus::DEFAULT_DRIVER_NAME]);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available', 'assigned_bus' => null, 'assigned_route' => null]);

        $component = Livewire::test('admin.dispatch-builder');

        $this->assertContains($bus->id, collect($component->get('availableBuses'))->pluck('id')->all());
        $this->assertContains($driver->id, collect($component->get('availableDrivers'))->pluck('id')->all());
        $this->assertTrue(collect($component->get('allBuses'))->firstWhere('id', $bus->id)['selectable']);
        $this->assertTrue(collect($component->get('allDrivers'))->firstWhere('id', $driver->id)['selectable']);
    }

    public function test_ui_eligibility_and_backend_validators_agree(): void
    {
        $this->actingAsAdmin();
        [$readyBus, $assignedDriver] = $this->retainedAssignment('ready', 'assigned');
        $freeBus = Bus::factory()->create(['status' => 'inactive', 'route_id' => null, 'driver_name' => Bus::DEFAULT_DRIVER_NAME]);
        $freeDriver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available', 'assigned_bus' => null, 'assigned_route' => null]);

        $component = Livewire::test('admin.dispatch-builder');
        $allBuses = collect($component->get('allBuses'));
        $allDrivers = collect($component->get('allDrivers'));

        foreach ([$readyBus, $freeBus] as $bus) {
            $uiEligible = $allBuses->firstWhere('id', $bus->id)['selectable'];
            $validatorEligible = $this->validatorAllows(fn () => BusDispatchValidator::validate($bus));
            $this->assertSame($validatorEligible, $uiEligible);
            $this->assertSame(CentralDispatchEligibilityService::busIsEligible($bus), $uiEligible);
        }

        foreach ([$assignedDriver, $freeDriver] as $driver) {
            $uiEligible = $allDrivers->firstWhere('id', $driver->id)['selectable'];
            $validatorEligible = $this->validatorAllows(fn () => DriverDispatchValidator::validate($driver));
            $this->assertSame($validatorEligible, $uiEligible);
            $this->assertSame(CentralDispatchEligibilityService::driverIsEligible($driver), $uiEligible);
        }
    }

    public function test_completed_point_to_point_trip_does_not_return_assets_to_dispatch_pool(): void
    {
        $this->actingAsAdmin();
        $bus = Bus::factory()->create(['status' => 'inactive', 'route_id' => null, 'driver_name' => Bus::DEFAULT_DRIVER_NAME]);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available', 'assigned_bus' => null, 'assigned_route' => null]);
        $route = Route::factory()->create();
        $trip = SimulationDispatchService::dispatch($bus, $driver, $route);

        app(TripLifecycleService::class)->startTrip($trip);
        app(TripLifecycleService::class)->completeTrip($trip->fresh());

        $bus->refresh();
        $driver->refresh();
        $this->assertSame('ready', $bus->status);
        $this->assertSame('assigned', $driver->operational_status);

        $component = Livewire::test('admin.dispatch-builder');
        $this->assertNotContains($bus->id, collect($component->get('availableBuses'))->pluck('id')->all());
        $this->assertNotContains($driver->id, collect($component->get('availableDrivers'))->pluck('id')->all());
    }

    public function test_maintenance_releases_driver_but_keeps_bus_unavailable(): void
    {
        $this->actingAsAdmin();
        [$bus, $driver] = $this->retainedAssignment('ready', 'assigned');

        BusStateService::transition($bus, 'maintenance', 'Maintenance regression test');

        $bus->refresh();
        $driver->refresh();
        $this->assertSame('maintenance', $bus->status);
        $this->assertSame('available', $driver->operational_status);
        $this->assertNull($driver->assigned_bus);
        $this->assertNull($driver->assigned_route);
        $this->assertFalse(CentralDispatchEligibilityService::busIsEligible($bus));
        $this->assertTrue(CentralDispatchEligibilityService::driverIsEligible($driver));
    }

    public function test_breakdown_retains_assignment_and_keeps_driver_unavailable(): void
    {
        $this->actingAsAdmin();
        [$bus, $driver, $route] = $this->retainedAssignment('ready', 'assigned');

        BusStateService::transition($bus, 'breakdown', 'Breakdown regression test');

        $bus->refresh();
        $driver->refresh();
        $this->assertSame('breakdown', $bus->status);
        $this->assertSame($route->id, $bus->route_id);
        $this->assertSame($bus->plate_number, $driver->assigned_bus);
        $this->assertSame((string) $route->id, (string) $driver->assigned_route);
        $this->assertSame('unavailable', $driver->operational_status);
        $this->assertFalse(CentralDispatchEligibilityService::busIsEligible($bus));
        $this->assertFalse(CentralDispatchEligibilityService::driverIsEligible($driver));
    }

    public function test_inactive_bus_persists_and_bus_management_uses_standby_inactive_label(): void
    {
        $bus = Bus::factory()->create(['status' => 'inactive', 'route_id' => null, 'driver_name' => Bus::DEFAULT_DRIVER_NAME]);


        $this->assertSame('inactive', $bus->fresh()->status);

        $index = file_get_contents(resource_path('views/admin/bus/index.blade.php'));
        $script = file_get_contents(public_path('js/admin-dashboard/buses.js'));

        $this->assertStringContainsString('Standby / Dispatchable', $index);
        $this->assertStringContainsString('Standby (Inactive)', $script);
        $this->assertStringContainsString('Operating', $script);
        $this->assertStringContainsString('Ready', $script);
        $this->assertStringNotContainsString('Available for Dispatch', $index);
        $this->assertStringNotContainsString('Available for Dispatch = Inactive', $script);
    }

    public function test_blank_driver_assignment_fields_are_treated_as_unassigned(): void
    {
        $driver = Driver::factory()->create([
            'status' => 'active',
            'operational_status' => 'available',
            'assigned_bus' => '',
            'assigned_route' => '',
        ]);

        $this->assertTrue(CentralDispatchEligibilityService::driverIsEligible($driver));
        $this->assertSame('Available for Central Dispatch', CentralDispatchEligibilityService::driver($driver)['reason']);
    }

    public function test_admin_resource_apis_expose_dispatch_eligibility_for_schedule_dropdowns(): void
    {
        $this->actingAsAdmin();
        $bus = Bus::factory()->create(['status' => 'inactive', 'route_id' => null, 'driver_name' => Bus::DEFAULT_DRIVER_NAME]);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available', 'assigned_bus' => null, 'assigned_route' => null]);

        $this->getJson(route('admin.api.fleet-data'))
            ->assertOk()
            ->assertJsonPath('buses.0.dispatch_eligible', true);

        $this->getJson(route('admin.api.drivers.index'))
            ->assertOk()
            ->assertJsonPath('drivers.0.dispatch_eligible', true);
    }
    private function retainedAssignment(string $busStatus, string $driverOperationalStatus): array
    {
        $route = Route::factory()->create();
        $bus = Bus::factory()->create([
            'status' => $busStatus,
            'route_id' => $route->id,
            'driver_name' => 'Juan Dela Cruz',
        ]);
        $driver = Driver::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'status' => 'active',
            'operational_status' => $driverOperationalStatus,
            'assigned_bus' => $bus->plate_number,
            'assigned_route' => (string) $route->id,
        ]);

        return [$bus, $driver, $route];
    }

    private function validatorAllows(callable $callback): bool
    {
        try {
            $callback();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        return $user;
    }
}