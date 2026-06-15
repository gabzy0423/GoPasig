<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/admin/dashboard');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_role_based_redirection(): void
    {
        // Admin
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);
        $response->assertRedirect('/admin/dashboard');
        $this->post('/logout');

        // Dispatcher
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);
        $response = $this->post('/login', [
            'email' => $dispatcher->email,
            'password' => 'password',
        ]);
        $response->assertRedirect('/fleet/dashboard');
        $this->post('/logout');

        // Driver
        $driver = User::factory()->create(['role' => 'driver']);
        $response = $this->post('/login', [
            'email' => $driver->email,
            'password' => 'password',
        ]);
        $response->assertRedirect('/driver/dashboard');
        $this->post('/logout');
    }

    public function test_commuter_is_publicly_accessible(): void
    {
        $response = $this->get('/commuter/dashboard');
        $response->assertStatus(200);
    }

    public function test_commuter_session_refreshes_correctly(): void
    {
        $response1 = $this->get('/commuter/dashboard');
        $response1->assertStatus(200);

        // Find the created session record
        $session = \App\Models\CommuterSession::latest()->first();
        $this->assertNotNull($session);
        $token = $session->session_token;

        // Manually set created_at and updated_at back in time to test refresh
        $createdAt = now()->subHours(2);
        $session->created_at = $createdAt;
        $session->updated_at = $createdAt;
        $session->expires_at = now()->addHours(22);
        $session->save();

        // Make a second request passing the cookie back (Laravel encrypts the raw token)
        $response2 = $this->withCookie('commuter_session_token', $token)
            ->get('/commuter/dashboard');
        $response2->assertStatus(200);

        // Refresh and check that updated_at and expires_at have been updated
        $session->refresh();
        $this->assertTrue($session->updated_at->isAfter($createdAt));
        $this->assertTrue($session->expires_at->isAfter(now()->addHours(23)));
    }

    public function test_unauthorized_users_cannot_access_other_dashboards(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        // Log in as dispatcher
        $this->actingAs($dispatcher);

        // Try to access admin dashboard
        $response = $this->get('/admin/dashboard');
        $response->assertStatus(403);
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create();

        // Perform 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
            $response->assertSessionHasErrors('email');
        }

        // The 6th attempt should be blocked and rate limited
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
    }
}
