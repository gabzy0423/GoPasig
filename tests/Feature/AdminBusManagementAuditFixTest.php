<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\SystemSetting;
use App\Models\Trip;
use App\Models\User;
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
    public function bus_creation_defaults_are_read_from_system_settings(): void
    {
        $this->actingAsAdmin();

        SystemSetting::updateOrCreate(['key' => 'bus_default_driver_name'], ['value' => 'No Driver']);
        SystemSetting::updateOrCreate(['key' => 'bus_default_next_stop'], ['value' => 'Terminal Pending']);
        SystemSetting::updateOrCreate(['key' => 'bus_initial_speed'], ['value' => '3']);
        SystemSetting::updateOrCreate(['key' => 'bus_initial_passengers'], ['value' => '4']);
        SystemSetting::updateOrCreate(['key' => 'bus_initial_eta'], ['value' => '5']);

        $response = $this->postJson(route('admin.api.buses.store'), [
            'plate_number' => 'PAS-201',
            'driver_name' => null,
            'capacity' => 45,
            'status' => 'inactive',
            'route_id' => null,
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
            'status' => 'inactive',
            'capacity' => 45,
        ]);

        $response = $this->putJson(route('admin.api.buses.update', $bus), [
            'plate_number' => 'PAS-202',
            'driver_name' => 'New Driver',
            'capacity' => 55,
            'route_id' => null,
            'status' => 'maintenance',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('buses', [
            'id' => $bus->id,
            'driver_name' => 'New Driver',
            'capacity' => 55,
            'status' => 'maintenance',
            'previous_status' => 'inactive',
        ]);
    }

    #[Test]
    public function bus_with_ongoing_trip_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();

        $route = Route::factory()->create();
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

        $route = Route::factory()->create();
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
}
