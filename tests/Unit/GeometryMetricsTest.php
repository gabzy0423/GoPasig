<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Route;
use App\Services\Contracts\RouteGeometryEngineInterface;
use App\Services\ValueObjects\Polyline;

class GeometryMetricsTest extends TestCase
{
    use RefreshDatabase;

    private RouteGeometryEngineInterface $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(RouteGeometryEngineInterface::class);
    }

    public function test_compute_metrics_calculates_all_gis_properties()
    {
        $route = Route::factory()->create();
        $polyline = Polyline::fromArray([
            [14.55, 121.07],
            [14.56, 121.08],
            [14.57, 121.09],
        ]);

        $metrics = $this->engine->computeMetrics($route->id, $polyline);

        $this->assertArrayHasKey('length_km', $metrics);
        $this->assertArrayHasKey('vertex_count', $metrics);
        $this->assertArrayHasKey('avg_segment_m', $metrics);
        $this->assertArrayHasKey('longest_segment_m', $metrics);
        $this->assertArrayHasKey('shortest_segment_m', $metrics);
        $this->assertArrayHasKey('max_vertex_spacing_m', $metrics);
        $this->assertArrayHasKey('bounds', $metrics);
        $this->assertArrayHasKey('center_point', $metrics);
        $this->assertArrayHasKey('closed_loop', $metrics);
        $this->assertArrayHasKey('self_intersections', $metrics);
        $this->assertArrayHasKey('duplicate_vertices', $metrics);
        $this->assertArrayHasKey('simplified_vertices', $metrics);
        $this->assertArrayHasKey('geometry_status', $metrics);
    }
}
