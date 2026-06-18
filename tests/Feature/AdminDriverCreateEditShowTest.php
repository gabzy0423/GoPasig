<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDriverCreateEditShowTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    public function test_admin_can_access_driver_create_page(): void
    {
        $this->actingAsAdmin();

        $response = $this->get('/admin/drivers/create');

        $response->assertStatus(200);
        $response->assertViewHas('licenseWarningDays', 30);
    }

    public function test_unauthorized_users_cannot_access_driver_create_page(): void
    {
        $response = $this->get('/admin/drivers/create');
        $response->assertRedirect('/login');

        $driver = User::factory()->create(['role' => 'driver']);
        $response = $this->actingAs($driver)->get('/admin/drivers/create');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_driver_show_page(): void
    {
        $this->actingAsAdmin();

        $driver = Driver::create([
            'first_name' => 'Jose',
            'last_name' => 'Rizal',
            'emp_id' => 'EMP-1896',
            'license_number' => 'N01-99-123456',
            'license_expiry' => '2030-06-19',
            'status' => 'inactive',
            'contact_number' => '09179998888',
            'address' => 'Calamba, Laguna',
            'emergency_contact' => 'Teodora Alonso - 09171112222',
            'trips_today' => 2,
            'pax_today' => 90,
            'performance_score' => 98,
            'incidents_30' => 0,
        ]);

        $response = $this->get("/admin/drivers/{$driver->id}");

        $response->assertRedirect('/admin/dashboard#drivers-show-' . $driver->id);
    }

    public function test_admin_can_access_driver_edit_page(): void
    {
        $this->actingAsAdmin();

        $driver = Driver::create([
            'first_name' => 'Jose',
            'last_name' => 'Rizal',
            'emp_id' => 'EMP-1896',
            'license_number' => 'N01-99-123456',
            'license_expiry' => '2030-06-19',
            'status' => 'inactive',
            'contact_number' => '09179998888',
            'address' => 'Calamba, Laguna',
            'emergency_contact' => 'Teodora Alonso - 09171112222',
            'trips_today' => 0,
            'pax_today' => 0,
            'performance_score' => 100,
            'incidents_30' => 0,
        ]);

        $response = $this->get("/admin/drivers/{$driver->id}/edit");

        $response->assertRedirect('/admin/dashboard#drivers-edit-' . $driver->id);
    }

    public function test_admin_can_store_driver_via_api(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/admin/api/drivers', [
            'first_name' => 'Andres',
            'last_name' => 'Bonifacio',
            'emp_id' => 'EMP-1897',
            'license_number' => 'N02-97-654321',
            'license_expiry' => '2032-11-30',
            'status' => 'inactive',
            'contact_number' => '09178889999',
            'address' => 'Tondo, Manila',
            'emergency_contact' => 'Gregoria de Jesus - 09173334444',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('drivers', [
            'emp_id' => 'EMP-1897',
            'first_name' => 'Andres',
            'last_name' => 'Bonifacio',
        ]);
    }

    public function test_admin_can_update_driver_via_api(): void
    {
        $this->actingAsAdmin();

        $driver = Driver::create([
            'first_name' => 'Jose',
            'last_name' => 'Rizal',
            'emp_id' => 'EMP-1896',
            'license_number' => 'N01-99-123456',
            'license_expiry' => '2030-06-19',
            'status' => 'inactive',
            'contact_number' => '09179998888',
            'address' => 'Calamba, Laguna',
            'emergency_contact' => 'Teodora Alonso - 09171112222',
            'trips_today' => 0,
            'pax_today' => 0,
            'performance_score' => 100,
            'incidents_30' => 0,
        ]);

        $response = $this->putJson("/admin/api/drivers/{$driver->id}", [
            'first_name' => 'Jose Protacio',
            'last_name' => 'Rizal Mercado',
            'license_number' => 'N01-99-123456',
            'license_expiry' => '2030-06-19',
            'status' => 'active',
            'contact_number' => '09179998888',
            'address' => 'Calamba, Laguna',
            'emergency_contact' => 'Teodora Alonso - 09171112222',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'first_name' => 'Jose Protacio',
            'last_name' => 'Rizal Mercado',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_toggle_suspend_driver_via_api(): void
    {
        $this->actingAsAdmin();

        $driver = Driver::create([
            'first_name' => 'Jose',
            'last_name' => 'Rizal',
            'emp_id' => 'EMP-1896',
            'license_number' => 'N01-99-123456',
            'license_expiry' => '2030-06-19',
            'status' => 'inactive',
            'contact_number' => '09179998888',
            'address' => 'Calamba, Laguna',
            'emergency_contact' => 'Teodora Alonso - 09171112222',
            'trips_today' => 0,
            'pax_today' => 0,
            'performance_score' => 100,
            'incidents_30' => 0,
        ]);

        // Suspend
        $response = $this->postJson("/admin/api/drivers/{$driver->id}/suspend");
        $response->assertStatus(200);
        $response->assertJsonPath('driver.status', 'suspended');

        // Unsuspend (toggles back to inactive)
        $response = $this->postJson("/admin/api/drivers/{$driver->id}/suspend");
        $response->assertStatus(200);
        $response->assertJsonPath('driver.status', 'inactive');
    }
}
