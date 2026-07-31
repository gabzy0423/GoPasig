<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DispatcherProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $dispatcherUser;
    protected $adminUser;
    protected $driverUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcherUser = User::factory()->create([
            'name' => 'Dispatcher User',
            'email' => 'dispatcher.profile@gopasig.gov.ph',
            'role' => 'fleet_manager',
            'password' => Hash::make('DispatcherPass123!'),
        ]);

        $this->adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin.profile@gopasig.gov.ph',
            'role' => 'admin',
            'password' => Hash::make('AdminPass123!'),
        ]);

        $this->driverUser = User::factory()->create([
            'name' => 'Driver User',
            'email' => 'driver.profile@gopasig.gov.ph',
            'role' => 'driver',
            'password' => Hash::make('DriverPass123!'),
        ]);
    }

    /** @test */
    public function test_guest_cannot_access_dispatcher_profile_endpoint()
    {
        $response = $this->getJson('/fleet/api/profile');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_guest_cannot_update_dispatcher_profile_endpoint()
    {
        $response = $this->putJson('/fleet/api/profile', [
            'name' => 'Hacker Name',
            'email' => 'hacker@gopasig.gov.ph',
        ]);
        $response->assertStatus(401);
    }

    /** @test */
    public function test_dispatcher_cannot_access_admin_profile_endpoint()
    {
        $response = $this->actingAs($this->dispatcherUser)->getJson('/admin/api/profile');
        $response->assertStatus(403);
    }

    /** @test */
    public function test_admin_cannot_access_dispatcher_profile_endpoint()
    {
        $response = $this->actingAs($this->adminUser)->getJson('/fleet/api/profile');
        $response->assertStatus(403);
    }

    /** @test */
    public function test_dispatcher_can_read_own_safe_profile()
    {
        $response = $this->actingAs($this->dispatcherUser)->getJson('/fleet/api/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'id' => $this->dispatcherUser->id,
                    'name' => 'Dispatcher User',
                    'email' => 'dispatcher.profile@gopasig.gov.ph',
                    'role' => 'fleet_manager',
                ],
            ]);
    }

    /** @test */
    public function test_dispatcher_profile_response_does_not_expose_password_or_remember_token()
    {
        $response = $this->actingAs($this->dispatcherUser)->getJson('/fleet/api/profile');

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringNotContainsString($this->dispatcherUser->password, $content);
        $this->assertStringNotContainsString('remember_token', $content);
    }

    /** @test */
    public function test_dispatcher_can_update_own_profile_details()
    {
        $response = $this->actingAs($this->dispatcherUser)->putJson('/fleet/api/profile', [
            'name' => 'Updated Dispatcher Name',
            'email' => 'dispatcher.updated@gopasig.gov.ph',
            'contact_number' => '09172223333',
            'address' => 'Pasig Transport Command Center',
            'emergency_contact' => 'John Doe - 09182223333',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile updated successfully.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->dispatcherUser->id,
            'name' => 'Updated Dispatcher Name',
            'email' => 'dispatcher.updated@gopasig.gov.ph',
        ]);

        $this->assertDatabaseHas('staff_profiles', [
            'user_id' => $this->dispatcherUser->id,
            'contact_number' => '09172223333',
            'address' => 'Pasig Transport Command Center',
            'emergency_contact' => 'John Doe - 09182223333',
        ]);
    }

    /** @test */
    public function test_email_must_be_unique_when_updating_dispatcher_profile()
    {
        $response = $this->actingAs($this->dispatcherUser)->putJson('/fleet/api/profile', [
            'name' => 'Dispatcher User',
            'email' => $this->adminUser->email,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function test_activity_log_recorded_on_dispatcher_profile_update()
    {
        $this->actingAs($this->dispatcherUser)->putJson('/fleet/api/profile', [
            'name' => 'Logged Dispatcher',
            'email' => 'dispatcher.logged@gopasig.gov.ph',
        ]);

        $log = ActivityLog::where('user_id', $this->dispatcherUser->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals('Profile', $log->type);
        $this->assertEquals('Profile updated', $log->description);
    }
}
