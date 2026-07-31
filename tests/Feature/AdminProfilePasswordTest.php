<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProfilePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $nonAdminUser;
    protected $oldPassword = 'OldPassword123!';
    protected $newPassword = 'NewSecretPassword456!';

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'name' => 'Security Admin',
            'email' => 'admin.security@gopasig.gov.ph',
            'role' => 'admin',
            'password' => Hash::make($this->oldPassword),
        ]);

        $this->nonAdminUser = User::factory()->create([
            'name' => 'Driver User',
            'email' => 'driver.security@gopasig.gov.ph',
            'role' => 'driver',
            'password' => Hash::make('DriverPass123!'),
        ]);
    }

    /** @test */
    public function test_guest_cannot_update_password()
    {
        $response = $this->putJson('/admin/api/profile/password', [
            'current_password' => $this->oldPassword,
            'new_password' => $this->newPassword,
            'new_password_confirmation' => $this->newPassword,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function test_non_admin_cannot_access_password_endpoint()
    {
        $response = $this->actingAs($this->nonAdminUser)->putJson('/admin/api/profile/password', [
            'current_password' => 'DriverPass123!',
            'new_password' => $this->newPassword,
            'new_password_confirmation' => $this->newPassword,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_current_password_is_required()
    {
        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile/password', [
            'current_password' => '',
            'new_password' => $this->newPassword,
            'new_password_confirmation' => $this->newPassword,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    /** @test */
    public function test_incorrect_current_password_is_rejected()
    {
        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile/password', [
            'current_password' => 'WrongCurrentPassword123!',
            'new_password' => $this->newPassword,
            'new_password_confirmation' => $this->newPassword,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    /** @test */
    public function test_confirmation_mismatch_is_rejected()
    {
        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile/password', [
            'current_password' => $this->oldPassword,
            'new_password' => $this->newPassword,
            'new_password_confirmation' => 'MismatchedPassword789!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    /** @test */
    public function test_password_too_short_is_rejected()
    {
        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile/password', [
            'current_password' => $this->oldPassword,
            'new_password' => 'Short1!',
            'new_password_confirmation' => 'Short1!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    /** @test */
    public function test_new_password_equals_current_password_is_rejected()
    {
        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile/password', [
            'current_password' => $this->oldPassword,
            'new_password' => $this->oldPassword,
            'new_password_confirmation' => $this->oldPassword,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    /** @test */
    public function test_valid_password_update_succeeds_and_hashes_password_without_double_hashing()
    {
        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile/password', [
            'current_password' => $this->oldPassword,
            'new_password' => $this->newPassword,
            'new_password_confirmation' => $this->newPassword,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password updated successfully.',
            ]);

        // Confirm the existing User password hashed cast does not cause double hashing
        $freshUser = $this->adminUser->fresh();
        $this->assertTrue(Hash::check($this->newPassword, $freshUser->password));
        $this->assertFalse(Hash::check($this->oldPassword, $freshUser->password));
    }

    /** @test */
    public function test_session_remains_authenticated_after_password_update()
    {
        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile/password', [
            'current_password' => $this->oldPassword,
            'new_password' => $this->newPassword,
            'new_password_confirmation' => $this->newPassword,
        ]);

        $response->assertStatus(200);
        $this->assertAuthenticatedAs($this->adminUser);
    }

    /** @test */
    public function test_old_password_fails_and_new_password_authenticates_successfully()
    {
        // 1. Update password
        $this->actingAs($this->adminUser)->putJson('/admin/api/profile/password', [
            'current_password' => $this->oldPassword,
            'new_password' => $this->newPassword,
            'new_password_confirmation' => $this->newPassword,
        ]);

        // 2. Explicitly log out to reset session before testing credentials
        Auth::logout();
        $this->app['session']->flush();

        // 3. Login with old password fails
        $oldLoginResponse = $this->post('/login', [
            'email' => $this->adminUser->email,
            'password' => $this->oldPassword,
        ]);
        $this->assertGuest();

        // 4. Login with new password succeeds
        $newLoginResponse = $this->post('/login', [
            'email' => $this->adminUser->email,
            'password' => $this->newPassword,
        ]);
        $this->assertAuthenticatedAs($this->adminUser);
    }

    /** @test */
    public function test_activity_log_records_security_event_without_plain_passwords_or_hashes()
    {
        $this->actingAs($this->adminUser)->putJson('/admin/api/profile/password', [
            'current_password' => $this->oldPassword,
            'new_password' => $this->newPassword,
            'new_password_confirmation' => $this->newPassword,
        ]);

        $log = ActivityLog::where('user_id', $this->adminUser->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals('Security', $log->type);
        $this->assertEquals('Admin password updated.', $log->description);

        $this->assertStringNotContainsString($this->oldPassword, $log->description);
        $this->assertStringNotContainsString($this->newPassword, $log->description);
        $this->assertStringNotContainsString($this->adminUser->fresh()->password, $log->description);
    }
}
