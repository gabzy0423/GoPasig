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
            ->set('selectedRoute', $route->id)
            ->set('selectedBus', 'PAS-123')
            ->set('selectedDriver', 'Juan Dela Cruz')
            ->set('departureTime', '08:00')
            ->call('createDispatch')
            ->assertDispatched('dispatchSuccessful');

        // Assert Bus is updated
        $bus->refresh();
        $this->assertSame('active', $bus->status);
        $this->assertSame($route->id, $bus->route_id);
        $this->assertSame('Juan Dela Cruz', $bus->driver_name);

        // Assert Driver is updated
        $driver->refresh();
        $this->assertSame('active', $driver->status);
        $this->assertSame('PAS-123', $driver->assigned_bus);
        $this->assertSame((string)$route->id, (string)$driver->assigned_route);

        // Assert Trip record is created in database
        $this->assertDatabaseHas('trips', [
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
        ]);

        // Assert Dispatch Log is created
        $trip = Trip::where('bus_id', $bus->id)->first();
        $this->assertDatabaseHas('dispatch_logs', [
            'trip_id' => $trip->id,
        ]);
    }
}
