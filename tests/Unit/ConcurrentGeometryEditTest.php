<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Route;
use App\Services\Contracts\RouteGeometryEngineInterface;
use App\Services\ValueObjects\Polyline;
use App\Exceptions\GeometryConflictException;

class ConcurrentGeometryEditTest extends TestCase
{
    use RefreshDatabase;

    private RouteGeometryEngineInterface $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(RouteGeometryEngineInterface::class);
    }

    public function test_second_save_returns_409_when_geometry_version_is_stale()
    {
        $route = Route::factory()->create(['geometry_version' => 0]);

        $polylineA = Polyline::fromArray([[14.55, 121.07], [14.56, 121.08]]);
        $polylineB = Polyline::fromArray([[14.55, 121.07], [14.57, 121.09]]);

        // Admin A saves first with version 0 → succeeds, route now at version 1
        $this->engine->updateGeometry($route->id, $polylineA, clientVersion: 0);
        $this->assertEquals(1, $route->fresh()->geometry_version);

        // Admin B attempts to save with stale version 0 → must throw GeometryConflictException
        $this->expectException(GeometryConflictException::class);
        $this->engine->updateGeometry($route->id, $polylineB, clientVersion: 0);

        // Admin B's geometry must NOT have been persisted
        $stored = $this->engine->getGeometry($route->id);
        $this->assertEquals($polylineA->toLatLngs(), $stored->toLatLngs());

        // Version counter must still be 1 (Admin B's failed save did not increment)
        $this->assertEquals(1, $route->fresh()->geometry_version);
    }
}
