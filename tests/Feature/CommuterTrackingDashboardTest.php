<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Route;
use App\Models\Stop;
use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommuterTrackingDashboardTest extends TestCase
{
    use RefreshDatabase;

    private $dispatcher;
    private $admin;
    private $driver;
    private $route;
    private $stop1;
    private $stop2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = User::factory()->create(['role' => 'dispatcher']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->driver = User::factory()->create(['role' => 'driver']);

        $this->route = Route::create([
            'id' => 1,
            'name' => 'Route A',
            'status' => 'Active',
        ]);

        $this->stop1 = Stop::create([
            'route_id' => $this->route->id,
            'name' => 'Stop A',
            'lat' => 14.5,
            'lng' => 121.0,
            'sequence' => 1,
        ]);

        $this->stop2 = Stop::create([
            'route_id' => $this->route->id,
            'name' => 'Stop B',
            'lat' => 14.6,
            'lng' => 121.1,
            'sequence' => 2,
        ]);
    }

    public function test_dispatcher_can_access_commuter_trips_api(): void
    {
        $token = 'test-token-123456';
        CommuterSession::create([
            'session_token' => $token,
            'expires_at' => now()->addHours(1),
        ]);

        CommuterTrip::create([
            'session_token' => $token,
            'route_id' => $this->route->id,
            'origin_stop_id' => $this->stop1->id,
            'destination_stop_id' => $this->stop2->id,
            'status' => 'WAITING',
        ]);

        $response = $this->actingAs($this->dispatcher)->getJson('/fleet/api/commuter-trips');
        $response->assertStatus(200);
        $response->assertJsonFragment(['session_token' => $token]);
    }

    public function test_dispatcher_can_access_commuter_sessions_api(): void
    {
        $token = 'test-session-token-999';
        CommuterSession::create([
            'session_token' => $token,
            'expires_at' => now()->addHours(2),
        ]);

        $response = $this->actingAs($this->dispatcher)->getJson('/fleet/api/commuter-sessions');
        $response->assertStatus(200);
        $response->assertJsonFragment(['session_token' => $token]);
    }

    public function test_unauthorized_roles_cannot_access_commuter_apis(): void
    {
        // Admin is auth'd for admin routes, but not fleet routes (which requires dispatcher role in web.php middleware)
        $response = $this->actingAs($this->admin)->getJson('/fleet/api/commuter-trips');
        $response->assertStatus(403);

        $response = $this->actingAs($this->driver)->getJson('/fleet/api/commuter-trips');
        $response->assertStatus(403);
    }
}
