<?php

namespace Tests\Feature;

use App\Models\Driver;
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

        // Dispatcher / Fleet Manager
        $dispatcher = User::factory()->create(['role' => 'fleet_manager']);
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
        $dispatcher = User::factory()->create(['role' => 'fleet_manager']);

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
    public function test_dispatcher_login_ignores_api_intended_url(): void
    {
        $dispatcher = User::factory()->create(['role' => 'fleet_manager']);

        $response = $this->withSession(['url.intended' => url('/admin/api/maintenance')])
            ->post('/login', [
                'email' => $dispatcher->email,
                'password' => 'password',
            ]);

        $response->assertRedirect('/fleet/dashboard');
    }

    public function test_admin_login_ignores_api_intended_url(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->withSession(['url.intended' => url('/admin/api/maintenance')])
            ->post('/login', [
                'email' => $admin->email,
                'password' => 'password',
            ]);

        $response->assertRedirect('/admin/dashboard');
    }

    public function test_login_preserves_non_api_intended_web_url(): void
    {
        $dispatcher = User::factory()->create(['role' => 'fleet_manager']);

        $response = $this->withSession(['url.intended' => url('/fleet/maintenance')])
            ->post('/login', [
                'email' => $dispatcher->email,
                'password' => 'password',
            ]);

        $response->assertRedirect('/fleet/maintenance');
    }

    public function test_maintenance_api_route_remains_json(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->getJson('/admin/api/maintenance');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }
    public function test_login_and_logout_responses_are_not_cached(): void
    {
        $dispatcher = User::factory()->create(['role' => 'fleet_manager']);

        $loginPage = $this->get('/login');
        $this->assertStringContainsString('no-store', $loginPage->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', $loginPage->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=0', $loginPage->headers->get('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', $loginPage->headers->get('Cache-Control'));
        $loginPage->assertHeader('Pragma', 'no-cache');

        $this->withMiddleware();
        $token = session()->token();

        $this->post('/login', [
            '_token' => $token,
            'email' => $dispatcher->email,
            'password' => 'password',
        ])->assertRedirect('/fleet/dashboard');

        $logoutResponse = $this->post('/logout', [
            '_token' => session()->token(),
        ]);

        $logoutResponse->assertRedirect('/login');
        $this->assertStringContainsString('no-store', $logoutResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', $logoutResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=0', $logoutResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', $logoutResponse->headers->get('Cache-Control'));
        $logoutResponse->assertHeader('Pragma', 'no-cache');
    }

    public function test_logout_is_post_only(): void
    {
        $this->get('/logout')->assertMethodNotAllowed();
    }

    public function test_users_can_switch_roles_in_same_browser_session_with_fresh_csrf_tokens(): void
    {
        $users = [
            'admin' => User::factory()->create(['role' => 'admin']),
            'dispatcher' => User::factory()->create(['role' => 'fleet_manager']),
            'driver' => User::factory()->create(['role' => 'driver']),
        ];

        $dashboards = [
            'admin' => '/admin/dashboard',
            'dispatcher' => '/fleet/dashboard',
            'driver' => '/driver/dashboard',
        ];

        $switches = [
            ['dispatcher', 'dispatcher'],
            ['dispatcher', 'admin'],
            ['admin', 'dispatcher'],
            ['admin', 'driver'],
            ['driver', 'admin'],
        ];

        $this->withMiddleware();

        foreach ($switches as [$firstRole, $secondRole]) {
            $this->get('/login')->assertOk();

            $this->post('/login', [
                '_token' => session()->token(),
                'email' => $users[$firstRole]->email,
                'password' => 'password',
            ])->assertRedirect($dashboards[$firstRole]);

            $this->assertAuthenticatedAs($users[$firstRole]);

            $this->post('/logout', [
                '_token' => session()->token(),
            ])->assertRedirect('/login');

            $this->assertGuest();

            $this->get('/login')->assertOk();

            $this->post('/login', [
                '_token' => session()->token(),
                'email' => $users[$secondRole]->email,
                'password' => 'password',
            ])->assertRedirect($dashboards[$secondRole]);

            $this->assertAuthenticatedAs($users[$secondRole]);

            $this->post('/logout', [
                '_token' => session()->token(),
            ])->assertRedirect('/login');
        }
    }
    public function test_authenticated_dashboards_are_not_cached(): void
    {
        $roles = [
            ['role' => 'admin', 'path' => '/admin/dashboard'],
            ['role' => 'fleet_manager', 'path' => '/fleet/dashboard'],
            ['role' => 'driver', 'path' => '/driver/dashboard'],
        ];

        foreach ($roles as $case) {
            $user = User::factory()->create(['role' => $case['role']]);


            if ($case['role'] === 'driver') {
                Driver::factory()->create(['user_id' => $user->id]);
            }

            $response = $this->actingAs($user)->get($case['path']);

            $response->assertOk();
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('max-age=0', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('must-revalidate', $response->headers->get('Cache-Control'));
            $response->assertHeader('Pragma', 'no-cache');

            $this->post('/logout');
        }
    }

    public function test_admin_dashboard_registers_logout_request_lifecycle_manager(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertSee('request-lifecycle.js', false);
        $response->assertSee('GoPasigAdminRequestLifecycle', false);
        $response->assertSee('method="POST"', false);
        $response->assertSee('/logout', false);
    }
    public function test_fleet_dashboard_registers_logout_request_lifecycle_manager(): void
    {
        $dispatcher = User::factory()->create(['role' => 'fleet_manager']);

        $response = $this->actingAs($dispatcher)->get('/fleet/dashboard');

        $response->assertOk();
        $response->assertSee('fleet-dashboard/request-lifecycle.js', false);
        $response->assertSee('GoPasigFleetRequestLifecycle', false);
        $this->assertLessThan(
            strpos($response->getContent(), 'fleet-dashboard/overview.js'),
            strpos($response->getContent(), 'fleet-dashboard/request-lifecycle.js')
        );
        $response->assertSee('method="POST"', false);
        $response->assertSee('/logout', false);
    }
    public function test_all_roles_can_switch_to_all_roles_in_same_browser_session_with_fresh_csrf_tokens(): void
    {
        $users = [
            'admin' => User::factory()->create(['role' => 'admin']),
            'dispatcher' => User::factory()->create(['role' => 'fleet_manager']),
            'driver' => User::factory()->create(['role' => 'driver']),
        ];

        $dashboards = [
            'admin' => '/admin/dashboard',
            'dispatcher' => '/fleet/dashboard',
            'driver' => '/driver/dashboard',
        ];

        $this->withMiddleware();

        foreach (array_keys($users) as $firstRole) {
            foreach (array_keys($users) as $secondRole) {
                $this->get('/login')->assertOk();

                $this->post('/login', [
                    '_token' => session()->token(),
                    'email' => $users[$firstRole]->email,
                    'password' => 'password',
                ])->assertRedirect($dashboards[$firstRole]);

                $this->assertAuthenticatedAs($users[$firstRole]);

                $this->post('/logout', [
                    '_token' => session()->token(),
                ])->assertRedirect('/login');

                $this->assertGuest();

                $this->get('/login')->assertOk();

                $this->post('/login', [
                    '_token' => session()->token(),
                    'email' => $users[$secondRole]->email,
                    'password' => 'password',
                ])->assertRedirect($dashboards[$secondRole]);

                $this->assertAuthenticatedAs($users[$secondRole]);

                $this->post('/logout', [
                    '_token' => session()->token(),
                ])->assertRedirect('/login');
            }
        }
    }
}
