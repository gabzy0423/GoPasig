<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Route;
use App\Models\Stop;
use App\Models\User;
use App\Models\RouteGenerationSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoutePreviewApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 'admin']);
    }

    public function test_api_generate_preview()
    {
        $route = Route::factory()->create([
            'polyline_coordinates' => [[14.5, 121.0], [14.6, 121.1]]
        ]);
        Stop::create(['route_id' => $route->id, 'name' => 'Stop A', 'lat' => 14.5, 'lng' => 121.0, 'sequence' => 1]);
        Stop::create(['route_id' => $route->id, 'name' => 'Stop B', 'lat' => 14.6, 'lng' => 121.1, 'sequence' => 2]);

        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    [
                        'geometry' => '_`owA_yoaV_pR_pR'
                    ]
                ]
            ], 200)
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.api.routes.geometry.generate_preview', $route->id), [
                'provider' => 'osrm'
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'session_id',
                'generated_geometry',
                'comparison' => [
                    'length_difference_km',
                    'vertex_difference',
                    'bounding_box_overlap_percent',
                    'hausdorff_distance_meters'
                ],
                'provider',
                'expires_at'
            ]);
    }

    public function test_api_accept_preview()
    {
        $route = Route::factory()->create([
            'polyline_coordinates' => [[14.5, 121.0], [14.6, 121.1]],
            'geometry_version' => 1
        ]);

        $session = RouteGenerationSession::create([
            'route_id' => $route->id,
            'provider' => 'osrm',
            'generated_geometry' => [[14.55, 121.05], [14.65, 121.15]],
            'comparison_metrics' => [],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30)
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.api.routes.geometry.accept_preview', $route->id), [
                'session_id' => $session->id,
                'last_geometry_version' => 1
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'geometry_version' => 2
            ]);

        $this->assertEquals(2, $route->fresh()->geometry_version);
        $this->assertNull(RouteGenerationSession::find($session->id));
    }

    public function test_api_accept_preview_conflict()
    {
        $route = Route::factory()->create([
            'polyline_coordinates' => [[14.5, 121.0], [14.6, 121.1]],
            'geometry_version' => 2
        ]);

        $session = RouteGenerationSession::create([
            'route_id' => $route->id,
            'provider' => 'osrm',
            'generated_geometry' => [[14.55, 121.05], [14.65, 121.15]],
            'comparison_metrics' => [],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30)
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.api.routes.geometry.accept_preview', $route->id), [
                'session_id' => $session->id,
                'last_geometry_version' => 1 // Outdated version client parameter
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'conflict' => true
            ]);
    }

    public function test_api_reject_preview()
    {
        $route = Route::factory()->create();

        $session = RouteGenerationSession::create([
            'route_id' => $route->id,
            'provider' => 'osrm',
            'generated_geometry' => [[14.5, 121.0], [14.6, 121.1]],
            'comparison_metrics' => [],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30)
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.api.routes.geometry.reject_preview', $route->id), [
                'session_id' => $session->id
            ]);

        $response->assertStatus(204);
        $this->assertNull(RouteGenerationSession::find($session->id));
    }
}
