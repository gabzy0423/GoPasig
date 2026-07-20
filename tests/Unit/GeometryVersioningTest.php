<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Route;
use App\Models\User;
use App\Models\RouteGeometryVersion;
use App\Services\GeometryVersioningService;
use App\Services\ValueObjects\Polyline;

class GeometryVersioningTest extends TestCase
{
    use RefreshDatabase;

    private GeometryVersioningService $versioning;

    protected function setUp(): void
    {
        parent::setUp();
        $this->versioning = new GeometryVersioningService();
    }

    public function test_snapshot_creates_version_history()
    {
        $route = Route::factory()->create();
        $polyline = Polyline::fromArray([[14.5, 121.0], [14.6, 121.1]]);

        $version = $this->versioning->snapshot($route->id, $polyline, 'Initial version');

        $this->assertDatabaseHas('route_geometry_versions', [
            'id' => $version->id,
            'route_id' => $route->id,
            'label' => 'Initial version',
            'vertex_count' => 2,
        ]);
    }

    public function test_prune_removes_old_versions()
    {
        $route = Route::factory()->create();
        $polyline = Polyline::fromArray([[14.5, 121.0], [14.6, 121.1]]);

        // Create 5 versions
        for ($i = 1; $i <= 5; $i++) {
            $this->versioning->snapshot($route->id, $polyline, "Version {$i}");
        }

        $this->assertEquals(5, RouteGeometryVersion::where('route_id', $route->id)->count());

        // Prune keeping 3
        $deleted = $this->versioning->prune($route->id, 3);

        $this->assertEquals(2, $deleted);
        $this->assertEquals(3, RouteGeometryVersion::where('route_id', $route->id)->count());
    }
}
