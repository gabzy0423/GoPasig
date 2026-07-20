<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Repositories\RouteGeometryRepository;
use App\Models\Route;
use Illuminate\Support\Facades\Cache;

class RouteGeometryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_route_polyline_caches_results()
    {
        $repo = new RouteGeometryRepository();

        // Create test route
        $route = Route::create([
            'name' => 'Test Route',
            'polyline_coordinates' => [
                [14.5593, 121.0805],
                [14.5613, 121.0825]
            ]
        ]);

        $cacheKey = "route_geometry_{$route->id}";
        Cache::forget($cacheKey);

        // Fetch through repository
        $poly1 = $repo->getRoutePolyline($route->id);

        // Verify it is cached
        $this->assertTrue(Cache::has($cacheKey));

        // Fetch again, should match
        $poly2 = $repo->getRoutePolyline($route->id);
        $this->assertEquals($poly1->toArray(), $poly2->toArray());

        // Invalidate cache
        $repo->clearCache($route->id);
        $this->assertFalse(Cache::has($cacheKey));
    }
}
