<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AdminProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $nonAdminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'name' => 'Original Admin Name',
            'email' => 'admin.profile@gopasig.gov.ph',
            'role' => 'admin',
            'password' => Hash::make('SecretAdminPass123!'),
        ]);

        $this->nonAdminUser = User::factory()->create([
            'name' => 'Driver User',
            'email' => 'driver.user@gopasig.gov.ph',
            'role' => 'driver',
        ]);
    }

    /** @test */
    public function test_guest_cannot_access_profile_endpoint()
    {
        $response = $this->getJson('/admin/api/profile');

        $response->assertStatus(401);
    }

    /** @test */
    public function test_guest_cannot_update_profile_endpoint()
    {
        $response = $this->putJson('/admin/api/profile', [
            'name' => 'Attempted Name',
            'email' => 'attempted@gopasig.gov.ph',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function test_non_admin_cannot_access_admin_profile_endpoints()
    {
        $getResponse = $this->actingAs($this->nonAdminUser)->getJson('/admin/api/profile');
        $getResponse->assertStatus(403);

        $putResponse = $this->actingAs($this->nonAdminUser)->putJson('/admin/api/profile', [
            'name' => 'Attempted Name',
            'email' => 'attempted@gopasig.gov.ph',
        ]);
        $putResponse->assertStatus(403);
    }

    /** @test */
    public function test_admin_can_read_own_safe_profile()
    {
        $response = $this->actingAs($this->adminUser)->getJson('/admin/api/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'id' => $this->adminUser->id,
                    'name' => 'Original Admin Name',
                    'email' => 'admin.profile@gopasig.gov.ph',
                    'role' => 'admin',
                ],
            ]);
    }

    /** @test */
    public function test_profile_response_does_not_expose_password_or_remember_token()
    {
        $response = $this->actingAs($this->adminUser)->getJson('/admin/api/profile');

        $response->assertStatus(200);
        $userData = $response->json('user');

        $this->assertArrayNotHasKey('password', $userData);
        $this->assertArrayNotHasKey('remember_token', $userData);
    }

    /** @test */
    public function test_admin_can_update_own_name()
    {
        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile', [
            'name' => 'Updated Admin Name',
            'email' => 'admin.profile@gopasig.gov.ph',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'user' => [
                    'id' => $this->adminUser->id,
                    'name' => 'Updated Admin Name',
                    'email' => 'admin.profile@gopasig.gov.ph',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->adminUser->id,
            'name' => 'Updated Admin Name',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'type' => 'Profile',
            'user_id' => $this->adminUser->id,
        ]);
    }

    /** @test */
    public function test_admin_can_update_own_email()
    {
        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile', [
            'name' => 'Original Admin Name',
            'email' => 'new.admin.email@gopasig.gov.ph',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'email' => 'new.admin.email@gopasig.gov.ph',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->adminUser->id,
            'email' => 'new.admin.email@gopasig.gov.ph',
        ]);
    }

    /** @test */
    public function test_existing_email_can_be_submitted_unchanged()
    {
        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile', [
            'name' => 'Same Email New Name',
            'email' => 'admin.profile@gopasig.gov.ph',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'name' => 'Same Email New Name',
                    'email' => 'admin.profile@gopasig.gov.ph',
                ],
            ]);
    }

    /** @test */
    public function test_duplicate_email_belonging_to_another_user_returns_validation_error()
    {
        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile', [
            'name' => 'Original Admin Name',
            'email' => 'driver.user@gopasig.gov.ph', // Owned by nonAdminUser
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function test_invalid_email_returns_validation_error()
    {
        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile', [
            'name' => 'Original Admin Name',
            'email' => 'invalid-email-string',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function test_empty_name_returns_validation_error()
    {
        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile', [
            'name' => '',
            'email' => 'admin.profile@gopasig.gov.ph',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function test_role_in_payload_cannot_change_admin_role()
    {
        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile', [
            'name' => 'Hacker Admin',
            'email' => 'admin.profile@gopasig.gov.ph',
            'role' => 'driver', // Attempt to demote/escalate role
        ]);

        $response->assertStatus(200);

        $this->adminUser->refresh();
        $this->assertEquals('admin', $this->adminUser->role);
    }

    /** @test */
    public function test_arbitrary_user_id_in_payload_cannot_update_another_user()
    {
        $otherUser = User::factory()->create([
            'name' => 'Victim User',
            'email' => 'victim@gopasig.gov.ph',
            'role' => 'admin',
        ]);

        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile', [
            'id' => $otherUser->id,
            'name' => 'Malicious Admin',
            'email' => 'admin.profile@gopasig.gov.ph',
        ]);

        $response->assertStatus(200);

        // Verify victim user was not modified
        $otherUser->refresh();
        $this->assertEquals('Victim User', $otherUser->name);

        // Verify logged in admin was modified instead
        $this->adminUser->refresh();
        $this->assertEquals('Malicious Admin', $this->adminUser->name);
    }

    /** @test */
    public function test_password_in_payload_is_ignored_and_remains_unchanged()
    {
        $originalHash = $this->adminUser->password;

        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile', [
            'name' => 'Admin Name',
            'email' => 'admin.profile@gopasig.gov.ph',
            'password' => 'NewHackedPassword123!',
        ]);

        $response->assertStatus(200);

        $this->adminUser->refresh();
        $this->assertEquals($originalHash, $this->adminUser->password);
        $this->assertTrue(Hash::check('SecretAdminPass123!', $this->adminUser->password));
    }

    /** @test */
    public function test_existing_authentication_session_remains_valid_after_name_email_update()
    {
        $this->actingAs($this->adminUser);

        $response = $this->putJson('/admin/api/profile', [
            'name' => 'Session Check Admin',
            'email' => 'session.admin@gopasig.gov.ph',
        ]);

        $response->assertStatus(200);

        // Check subsequent call in same session stays authenticated
        $getResponse = $this->getJson('/admin/api/profile');
        $getResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'name' => 'Session Check Admin',
                    'email' => 'session.admin@gopasig.gov.ph',
                ],
            ]);
    }
}
