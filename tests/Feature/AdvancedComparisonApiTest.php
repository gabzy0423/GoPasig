<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Route;
use App\Models\User;
use App\Models\RouteGenerationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdvancedComparisonApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 'admin']);
    }

    public function test_api_runs_advanced_frechet_analysis()
    {
        $route = Route::factory()->create([
            'polyline_coordinates' => [[14.5, 121.0], [14.6, 121.1]]
        ]);

        $session = RouteGenerationSession::create([
            'route_id' => $route->id,
            'provider' => 'osrm',
            'generated_geometry' => [[14.5001, 121.0001], [14.6001, 121.1001]], // very small shift (~15m)
            'comparison_metrics' => [
                'length_difference_km' => 0.1,
                'vertex_difference' => 0,
                'bounding_box_overlap_percent' => 95.0,
                'hausdorff_distance_meters' => 50.0,
                'advanced_analysis_performed' => false,
                'frechet_similarity_percent' => null,
                'quality' => [
                    'score' => 95,
                    'grade' => 'Excellent',
                    'warnings' => [],
                    'recommendations' => []
                ]
            ],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30)
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.api.routes.geometry.advanced_analysis', $route->id), [
                'session_id' => $session->id
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'comparison' => [
                    'length_difference_km',
                    'vertex_difference',
                    'bounding_box_overlap_percent',
                    'hausdorff_distance_meters',
                    'advanced_analysis_performed',
                    'frechet_similarity_percent'
                ],
                'quality' => [
                    'score',
                    'grade',
                    'warnings',
                    'recommendations'
                ]
            ])
            ->assertJsonPath('comparison.advanced_analysis_performed', true);

        $this->assertGreaterThan(80.0, $response->json('comparison.frechet_similarity_percent'));

        // Confirm database session values updated in-place
        $session->refresh();
        $this->assertTrue($session->comparison_metrics['advanced_analysis_performed']);
        $this->assertGreaterThan(80.0, $session->comparison_metrics['frechet_similarity_percent']);
    }
}
