<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminDriverApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_cannot_read_the_driver_management_payload(): void
    {
        $this->getJson(route('admin.api.drivers.index'))
            ->assertUnauthorized();
    }

    #[Test]
    public function authenticated_non_admin_cannot_read_or_mutate_driver_management_records(): void
    {
        $driver = Driver::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'status' => 'inactive',
        ]);
        $nonAdmin = User::factory()->create(['role' => 'driver']);

        $this->actingAs($nonAdmin)
            ->getJson(route('admin.api.drivers.index'))
            ->assertForbidden();

        $this->postJson(route('admin.api.drivers.store'), [
            'first_name' => 'Unauthorized',
            'last_name' => 'Driver',
            'emp_id' => 'UNAUTHORIZED-DRIVER',
            'license_number' => 'UNAUTHORIZED-LICENSE',
            'license_expiry' => now()->addYear()->toDateString(),
            'status' => 'inactive',
        ])->assertForbidden();

        $this->putJson(route('admin.api.drivers.update', $driver), [
            'first_name' => 'Changed',
            'last_name' => $driver->last_name,
            'license_number' => $driver->license_number,
            'license_expiry' => $driver->license_expiry->toDateString(),
            'status' => 'active',
        ])->assertForbidden();

        $this->postJson(route('admin.api.drivers.suspend', $driver))
            ->assertForbidden();

        $this->deleteJson(route('admin.api.drivers.destroy', $driver))
            ->assertForbidden();

        $this->assertDatabaseMissing('drivers', [
            'emp_id' => 'UNAUTHORIZED-DRIVER',
        ]);
        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'first_name' => 'Juan',
            'status' => 'inactive',
        ]);
    }

    #[Test]
    public function admin_can_still_read_and_suspend_a_driver(): void
    {
        $driver = Driver::factory()->create([
            'status' => 'inactive',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson(route('admin.api.drivers.index'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('drivers.0.id', $driver->id);

        $this->postJson(route('admin.api.drivers.suspend', $driver))
            ->assertOk()
            ->assertJsonPath('driver.status', 'suspended');

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'status' => 'suspended',
        ]);
    }
}
