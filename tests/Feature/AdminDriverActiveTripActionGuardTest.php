<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminDriverActiveTripActionGuardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_cannot_suspend_a_driver_with_a_dispatched_or_ongoing_trip(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach (['dispatched', 'ongoing'] as $tripStatus) {
            $driver = Driver::factory()->create([
                'status' => 'active',
                'previous_status' => null,
            ]);
            $trip = Trip::factory()->create([
                'driver_id' => $driver->id,
                'status' => $tripStatus,
                'dispatched_at' => now(),
                'started_at' => $tripStatus === 'ongoing' ? now() : null,
                'ended_at' => null,
            ]);

            $this->postJson(route('admin.api.drivers.suspend', $driver))
                ->assertUnprocessable()
                ->assertJsonPath('message', 'Cannot suspend or reinstate a driver while a dispatched or ongoing trip is active. End or cancel the trip first.');

            $this->assertDatabaseHas('drivers', [
                'id' => $driver->id,
                'status' => 'active',
                'previous_status' => null,
            ]);
            $this->assertDatabaseHas('trips', [
                'id' => $trip->id,
                'status' => $tripStatus,
            ]);
        }
    }

    #[Test]
    public function admin_can_edit_profile_fields_but_not_employment_status_during_an_active_trip(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $driver = Driver::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'status' => 'active',
            'license_expiry' => now()->addYear(),
        ]);
        Trip::factory()->create([
            'driver_id' => $driver->id,
            'status' => 'ongoing',
            'started_at' => now(),
            'ended_at' => null,
        ]);

        $this->putJson(route('admin.api.drivers.update', $driver), $this->updatePayload($driver, [
            'first_name' => 'Juan Updated',
            'status' => 'active',
        ]))->assertOk();

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'first_name' => 'Juan Updated',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $driver->user_id,
            'name' => 'Juan Updated Dela Cruz',
        ]);

        $this->putJson(route('admin.api.drivers.update', $driver), $this->updatePayload($driver->fresh(), [
            'first_name' => 'Must Not Persist',
            'status' => 'inactive',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot change driver employment status while a dispatched or ongoing trip is active. End or cancel the trip first.');

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'first_name' => 'Juan Updated',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $driver->user_id,
            'name' => 'Juan Updated Dela Cruz',
        ]);
    }

    #[Test]
    public function completed_or_cancelled_trips_do_not_block_status_actions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach (['completed', 'cancelled'] as $tripStatus) {
            $driver = Driver::factory()->create(['status' => 'active']);
            Trip::factory()->create([
                'driver_id' => $driver->id,
                'status' => $tripStatus,
                'started_at' => now()->subHour(),
                'ended_at' => now(),
            ]);

            $this->postJson(route('admin.api.drivers.suspend', $driver))
                ->assertOk()
                ->assertJsonPath('driver.status', 'suspended');
        }
    }

    #[Test]
    public function driver_management_ui_locks_status_and_suspend_actions_during_active_trips(): void
    {
        $script = file_get_contents(public_path('js/admin-dashboard/drivers.js'));
        $editView = file_get_contents(resource_path('views/admin/drivers/edit.blade.php'));

        $this->assertStringContainsString('statusSelect.disabled = driver.hasActiveTrip', $script);
        $this->assertStringContainsString('Suspension Locked During Active Trip', $script);
        $this->assertStringContainsString('if (driver.hasActiveTrip)', $script);
        $this->assertStringContainsString('df-edit-active-trip-guard', $editView);
    }

    /** @param array<string, mixed> $overrides */
    private function updatePayload(Driver $driver, array $overrides = []): array
    {
        return array_merge([
            'first_name' => $driver->first_name,
            'last_name' => $driver->last_name,
            'license_number' => $driver->license_number,
            'license_expiry' => $driver->license_expiry->toDateString(),
            'status' => $driver->status,
            'contact_number' => $driver->contact_number,
            'address' => $driver->address,
            'emergency_contact' => $driver->emergency_contact,
        ], $overrides);
    }
}
