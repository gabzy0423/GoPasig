<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DispatcherProfilePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected $dispatcherUser;
    protected $oldPassword = 'DispatcherOldPass123!';
    protected $newPassword = 'DispatcherNewPass456!';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcherUser = User::factory()->create([
            'name' => 'Dispatcher Security',
            'email' => 'dispatcher.security@gopasig.gov.ph',
            'role' => 'fleet_manager',
            'password' => Hash::make($this->oldPassword),
        ]);
    }

    /** @test */
    public function test_guest_cannot_update_dispatcher_password()
    {
        $response = $this->putJson('/fleet/api/profile/password', [
            'current_password' => $this->oldPassword,
            'new_password' => $this->newPassword,
            'new_password_confirmation' => $this->newPassword,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function test_incorrect_current_password_is_rejected_for_dispatcher()
    {
        $response = $this->actingAs($this->dispatcherUser)->putJson('/fleet/api/profile/password', [
            'current_password' => 'WrongPass123!',
            'new_password' => $this->newPassword,
            'new_password_confirmation' => $this->newPassword,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    /** @test */
    public function test_valid_dispatcher_password_update_succeeds_and_hashes_password()
    {
        $response = $this->actingAs($this->dispatcherUser)->putJson('/fleet/api/profile/password', [
            'current_password' => $this->oldPassword,
            'new_password' => $this->newPassword,
            'new_password_confirmation' => $this->newPassword,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password updated successfully.',
            ]);

        $freshUser = $this->dispatcherUser->fresh();
        $this->assertTrue(Hash::check($this->newPassword, $freshUser->password));
        $this->assertFalse(Hash::check($this->oldPassword, $freshUser->password));
        $this->assertNotNull($freshUser->password_changed_at);
    }

    /** @test */
    public function test_dispatcher_session_remains_authenticated_after_password_update()
    {
        $response = $this->actingAs($this->dispatcherUser)->putJson('/fleet/api/profile/password', [
            'current_password' => $this->oldPassword,
            'new_password' => $this->newPassword,
            'new_password_confirmation' => $this->newPassword,
        ]);

        $response->assertStatus(200);
        $this->assertAuthenticatedAs($this->dispatcherUser);
    }

    /** @test */
    public function test_dispatcher_old_password_fails_and_new_password_authenticates()
    {
        $this->actingAs($this->dispatcherUser)->putJson('/fleet/api/profile/password', [
            'current_password' => $this->oldPassword,
            'new_password' => $this->newPassword,
            'new_password_confirmation' => $this->newPassword,
        ]);

        Auth::logout();
        $this->app['session']->flush();

        $oldLogin = $this->post('/login', [
            'email' => $this->dispatcherUser->email,
            'password' => $this->oldPassword,
        ]);
        $this->assertGuest();

        $newLogin = $this->post('/login', [
            'email' => $this->dispatcherUser->email,
            'password' => $this->newPassword,
        ]);
        $this->assertAuthenticatedAs($this->dispatcherUser);
    }

    /** @test */
    public function test_activity_log_records_dispatcher_security_event()
    {
        $this->actingAs($this->dispatcherUser)->putJson('/fleet/api/profile/password', [
            'current_password' => $this->oldPassword,
            'new_password' => $this->newPassword,
            'new_password_confirmation' => $this->newPassword,
        ]);

        $log = ActivityLog::where('user_id', $this->dispatcherUser->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals('Security', $log->type);
        $this->assertStringContainsString('password updated.', $log->description);

        $this->assertStringNotContainsString($this->oldPassword, $log->description);
        $this->assertStringNotContainsString($this->newPassword, $log->description);
    }
}
