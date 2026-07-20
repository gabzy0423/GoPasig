<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Route;
use App\Models\Stop;
use App\Models\User;
use App\Services\Routing\IntelligentRoutingEngine;
use App\Services\Routing\ProviderHealthService;
use App\Services\Contracts\ProviderCircuitBreakerInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProviderFailoverTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 'admin']);
    }

    public function test_google_healthy_resolves_google()
    {
        $route = Route::factory()->create(['polyline_coordinates' => [[14.5, 121.0], [14.6, 121.1]]]);
        Stop::create(['route_id' => $route->id, 'name' => 'A', 'lat' => 14.5, 'lng' => 121.0, 'sequence' => 1]);
        Stop::create(['route_id' => $route->id, 'name' => 'B', 'lat' => 14.6, 'lng' => 121.1, 'sequence' => 2]);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'routes' => [['overview_polyline' => ['points' => '_`owA_yoaV_pR_pR']]]
            ], 200)
        ]);

        $engine = app(IntelligentRoutingEngine::class);

        // Under normal healthy conditions, google is selected
        $result = $engine->generatePreview($route, 'google', $this->adminUser->id);
        $this->assertEquals('google', $result->provider);
    }

    public function test_google_tripped_fails_over_to_osrm()
    {
        $route = Route::factory()->create(['polyline_coordinates' => [[14.5, 121.0], [14.6, 121.1]]]);
        Stop::create(['route_id' => $route->id, 'name' => 'A', 'lat' => 14.5, 'lng' => 121.0, 'sequence' => 1]);
        Stop::create(['route_id' => $route->id, 'name' => 'B', 'lat' => 14.6, 'lng' => 121.1, 'sequence' => 2]);

        // Manually open Google circuit breaker state in Cache
        Cache::put('circuit_breaker:google:state', 'Open');
        Cache::put('circuit_breaker:google:last_state_change', time());

        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['geometry' => '_`owA_yoaV_pR_pR']]
            ], 200)
        ]);

        $engine = app(IntelligentRoutingEngine::class);

        // Should automatically failover to OSRM since Google is Open
        $result = $engine->generatePreview($route, 'google', $this->adminUser->id);
        $this->assertEquals('osrm', $result->provider);
    }

    public function test_telemetry_snapshot_updates_after_multiple_requests()
    {
        $healthSvc = app(ProviderHealthService::class);

        // Multiple requests to Google
        $healthSvc->recordRequest('google', 120.0, true);
        $healthSvc->recordRequest('google', 80.0, true);
        $healthSvc->recordRequest('google', 200.0, false);

        $snapshot = $healthSvc->getSnapshot('google');
        $this->assertEquals(3, $snapshot->totalRequests);
        $this->assertEquals(2, $snapshot->successfulRequests);
        $this->assertEquals(133.3, round($snapshot->averageLatencyMs, 1));
        $this->assertEquals(33.3, round($snapshot->failureRate, 1));
    }
}
