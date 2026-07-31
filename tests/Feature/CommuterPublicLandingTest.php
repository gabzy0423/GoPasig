<?php

namespace Tests\Feature;

use App\Models\CommuterSession;
use App\Models\Route;
use App\Models\Stop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CommuterPublicLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        foreach (['Route A', 'Route B', 'Route C', 'Route D', 'PHASE2-UAT Route', 'PHASE3C-UAT Point-to-Point A-B', 'Route 1', 'Route 2', 'Route 3'] as $name) {
            $route = Route::create([
                'name' => $name,
                'description' => $name . ' Description',
                'status' => 'Active',
                'color' => '#003F87',
            ]);

            Stop::create([
                'route_id' => $route->id,
                'name' => $name . ' Origin',
                'lat' => 14.5,
                'lng' => 121.0,
                'sequence' => 1,
                'radius_meters' => 100,
            ]);
        }
    }

    public function test_root_is_public_and_initializes_commuter_session(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/commuter/dashboard');
        $response->assertCookie('commuter_session_token');
        $this->assertSame(1, CommuterSession::count());
    }

    public function test_root_enters_public_commuter_experience_without_login(): void
    {
        $root = $this->get('/');
        $token = CommuterSession::latest()->first()->session_token;

        $response = $this->withCookie('commuter_session_token', $token)
            ->get($root->headers->get('Location'));

        $response->assertOk();
        $response->assertSee('GoPasig');
        $response->assertSee('Libreng Sakay');
        $response->assertSee('Staff Login');
        $response->assertDontSee('Sign in to your account');
    }

    public function test_public_commuter_navigation_links_are_guest_accessible(): void
    {
        $response = $this->get('/commuter/dashboard');

        $response->assertOk();
        $response->assertSee('/commuter/dashboard', false);
        $response->assertSee('/commuter/tracker', false);
        $response->assertSee('/commuter/routes', false);
        $response->assertSee('/commuter/stops', false);
        $response->assertSee('/commuter/schedule', false);
        $response->assertSee('/commuter/alerts', false);
        $response->assertSee('/login', false);
        $response->assertSee('Staff Login');
    }

    public function test_direct_public_commuter_pages_refresh_without_login_redirect(): void
    {
        foreach (['/commuter/dashboard', '/commuter/tracker', '/commuter/routes', '/commuter/stops', '/commuter/alerts', '/commuter/schedule'] as $path) {
            $response = $this->get($path);

            $response->assertOk();
            $response->assertDontSee('Sign in to your account');
            $this->assertFalse($response->isRedirect('/login'), $path . ' redirected to login.');
        }
    }

    public function test_guest_commuter_session_persists_across_page_navigation(): void
    {
        $this->get('/commuter/dashboard')->assertOk();
        $session = CommuterSession::latest()->first();
        $this->assertNotNull($session);

        foreach (['/commuter/tracker', '/commuter/routes', '/commuter/stops', '/commuter/schedule', '/commuter/alerts'] as $path) {
            $this->withCookie('commuter_session_token', $session->session_token)
                ->get($path)
                ->assertOk();
        }

        $this->assertSame(1, CommuterSession::count());
    }

    public function test_staff_login_remains_available_and_protected_dashboards_remain_blocked(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/fleet/dashboard')->assertRedirect('/login');
        $this->get('/driver/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_operational_user_still_enters_public_commuter_landing_from_root(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/');

        $response->assertRedirect('/commuter/dashboard');
        $response->assertCookie('commuter_session_token');
    }

    public function test_public_routes_keep_phase_one_canonical_filtering(): void
    {
        $response = $this->get('/commuter/routes');

        $response->assertOk();
        $response->assertSee('Route 1');
        $response->assertSee('Route 2');
        $response->assertSee('Route 3');
        $response->assertDontSee('Route A');
        $response->assertDontSee('Route B');
        $response->assertDontSee('Route C');
        $response->assertDontSee('Route D');
        $response->assertDontSee('PHASE2-UAT');
        $response->assertDontSee('PHASE3C-UAT');
        $response->assertSee('routes: [{"route_id":', false);
    }
}
