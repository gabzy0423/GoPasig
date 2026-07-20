<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Route;
use App\Services\Contracts\RouteGeometryEngineInterface;
use App\Services\ValueObjects\Polyline;

class RouteGeometryEngineTest extends TestCase
{
    use RefreshDatabase;

    private RouteGeometryEngineInterface $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(RouteGeometryEngineInterface::class);
    }

    public function test_engine_updates_route_geometry_successfully()
    {
        $route = Route::factory()->create(['geometry_version' => 0]);
        $polyline = Polyline::fromArray([[14.55, 121.07], [14.56, 121.08]]);

        $result = $this->engine->updateGeometry($route->id, $polyline, clientVersion: 0);

        $this->assertEquals(1, $route->fresh()->geometry_version);
        $this->assertEquals($polyline->toLatLngs(), $route->fresh()->polyline_coordinates);
    }
}
