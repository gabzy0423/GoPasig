<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Route;
use App\Models\Stop;
use App\Models\User;
use App\Models\RouteGenerationSession;
use App\Services\Routing\IntelligentRoutingEngine;
use App\Services\Routing\RouteGenerationSessionService;
use App\Exceptions\RoutingProviderException;
use App\Exceptions\GeometryConflictException;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

class IntelligentRoutingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        // Register an admin user for permission scopes if needed
        $this->adminUser = User::factory()->create(['role' => 'admin']);
    }

    public function test_generate_preview_success()
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
                        // Encoded polyline representing: [14.5, 121.0] -> [14.6, 121.1]
                        'geometry' => '_`owA_yoaV_pR_pR'
                    ]
                ]
            ], 200)
        ]);

        $engine = app(IntelligentRoutingEngine::class);
        $result = $engine->generatePreview($route, 'osrm', $this->adminUser->id);

        $this->assertNotNull($result->sessionId);
        $this->assertEquals('osrm', $result->provider);
        $this->assertNotEmpty($result->generatedGeometry);
        $this->assertEquals(0, $result->comparisonMetrics['vertex_difference']);

        // Verify session persists in database
        $session = RouteGenerationSession::find($result->sessionId);
        $this->assertNotNull($session);
        $this->assertEquals('pending', $session->status);
    }

    public function test_generate_preview_insufficient_stops()
    {
        $route = Route::factory()->create();
        Stop::create(['route_id' => $route->id, 'name' => 'Stop A', 'lat' => 14.5, 'lng' => 121.0, 'sequence' => 1]); // Only 1 stop

        $engine = app(IntelligentRoutingEngine::class);

        $this->expectException(RoutingProviderException::class);
        $this->expectExceptionMessage('At least origin and destination stops are required');

        $engine->generatePreview($route, 'osrm', $this->adminUser->id);
    }

    public function test_accept_preview_success()
    {
        $route = Route::factory()->create([
            'polyline_coordinates' => [[14.5, 121.0], [14.6, 121.1]],
            'geometry_version' => 1
        ]);
        Stop::create(['route_id' => $route->id, 'name' => 'Stop A', 'lat' => 14.5, 'lng' => 121.0, 'sequence' => 1]);
        Stop::create(['route_id' => $route->id, 'name' => 'Stop B', 'lat' => 14.6, 'lng' => 121.1, 'sequence' => 2]);

        $service = app(RouteGenerationSessionService::class);
        $session = $service->createSession(
            $route->id,
            'osrm',
            \App\Services\ValueObjects\Polyline::fromArray([[14.52, 121.02], [14.58, 121.08]]),
            []
        );

        $engine = app(IntelligentRoutingEngine::class);
        $engine->acceptPreview($session->id, 1); // matches client version 1

        $route->refresh();
        $this->assertEquals(2, $route->geometry_version); // Incremented
        $this->assertCount(2, $route->polyline_coordinates);
        $this->assertEquals(14.52, $route->polyline_coordinates[0][0]);

        // Session should be deleted from table
        $this->assertNull(RouteGenerationSession::find($session->id));
    }

    public function test_accept_preview_conflict_exception()
    {
        $route = Route::factory()->create([
            'polyline_coordinates' => [[14.5, 121.0], [14.6, 121.1]],
            'geometry_version' => 2
        ]);

        $service = app(RouteGenerationSessionService::class);
        $session = $service->createSession(
            $route->id,
            'osrm',
            \App\Services\ValueObjects\Polyline::fromArray([[14.52, 121.02], [14.58, 121.08]]),
            []
        );

        $engine = app(IntelligentRoutingEngine::class);

        $this->expectException(GeometryConflictException::class);
        $engine->acceptPreview($session->id, 1); // client version 1 is outdated (DB is 2)
    }

    public function test_reject_preview_success()
    {
        $route = Route::factory()->create();
        $service = app(RouteGenerationSessionService::class);
        $session = $service->createSession(
            $route->id,
            'osrm',
            \App\Services\ValueObjects\Polyline::fromArray([[14.5, 121.0], [14.6, 121.1]]),
            []
        );

        $engine = app(IntelligentRoutingEngine::class);
        $engine->rejectPreview($session->id);

        $this->assertNull(RouteGenerationSession::find($session->id));
    }
}
