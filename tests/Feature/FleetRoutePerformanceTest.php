<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetRoutePerformanceTest extends TestCase
{
    use RefreshDatabase;

    private $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->route = Route::create([
            'id' => 1,
            'name' => 'Route A',
            'description' => 'SPED to Pasig City Hall',
            'polyline_coordinates' => [[14.5593, 121.0805], [14.5838, 121.0620]],
            'status' => 'Active',
        ]);
    }

    public function test_dispatcher_can_access_route_performance(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $response = $this->actingAs($dispatcher)->get('/fleet/routes');
        $response->assertRedirect('/fleet/dashboard?tab=routes');

        $dashboardResponse = $this->actingAs($dispatcher)->get('/fleet/dashboard?tab=routes');
        $dashboardResponse->assertStatus(200);
    }

    public function test_unauthorized_users_cannot_access_route_performance(): void
    {
        // Admin user is unauthorized for dispatcher routes
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get('/fleet/routes');
        $response->assertStatus(403);

        // Driver user is unauthorized
        $driver = User::factory()->create(['role' => 'driver']);
        $response = $this->actingAs($driver)->get('/fleet/routes');
        $response->assertStatus(403);
    }

    public function test_guest_users_are_redirected_to_login(): void
    {
        // Guest user is redirected to login
        $response = $this->get('/fleet/routes');
        $response->assertRedirect('/login');
    }

    public function test_export_route_report_works(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $response = $this->actingAs($dispatcher)->get('/fleet/api/routes-export?route_id=all');

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('GoPasig Route Performance Report', $response->streamedContent());
    }

    public function test_api_routes_data_filtering_works(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $response = $this->actingAs($dispatcher)->get('/fleet/api/routes-data?route_id=1');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'routePerformanceSummary',
            'headwayData',
            'scheduleCompliance',
            'stops',
            'deviationLog',
            'routeHealthScore'
        ]);
    }

    public function test_api_deviation_filtering_works(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $response = $this->actingAs($dispatcher)->json('GET', '/fleet/api/routes-data', [
            'route_id' => '1',
            'deviation_types' => ['Off-Route']
        ]);

        $response->assertStatus(200);
    }
}

