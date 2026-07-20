<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Route;
use App\Models\RouteGenerationSession;
use App\Services\Routing\RouteGenerationSessionService;
use App\Services\ValueObjects\Polyline;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RouteGenerationSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_retrieve_session()
    {
        $route = Route::factory()->create([
            'polyline_coordinates' => [[14.5, 121.0], [14.6, 121.1]]
        ]);

        $service = new RouteGenerationSessionService();
        $polyline = Polyline::fromArray([[14.5, 121.0], [14.6, 121.1]]);

        $session = $service->createSession(
            $route->id,
            'osrm',
            $polyline,
            ['length_difference_km' => 0.0, 'vertex_difference' => 0]
        );

        $this->assertNotNull($session->id);
        $this->assertEquals($route->id, $session->route_id);
        $this->assertEquals('osrm', $session->provider);
        $this->assertEquals('pending', $session->status);
        $this->assertFalse($session->isExpired());

        // Test active session reuse finder
        $active = $service->findActiveSession($route->id, 'osrm');
        $this->assertNotNull($active);
        $this->assertEquals($session->id, $active->id);
    }

    public function test_expire_existing_pending_sessions_on_new_create()
    {
        $route = Route::factory()->create();
        $service = new RouteGenerationSessionService();
        $polyline = Polyline::fromArray([[14.5, 121.0], [14.6, 121.1]]);

        $session1 = $service->createSession($route->id, 'osrm', $polyline, []);
        $session2 = $service->createSession($route->id, 'osrm', $polyline, []);

        $this->assertEquals('rejected', $session1->fresh()->status);
        $this->assertEquals('pending', $session2->fresh()->status);
    }

    public function test_expired_session()
    {
        $route = Route::factory()->create();
        $service = new RouteGenerationSessionService();
        $polyline = Polyline::fromArray([[14.5, 121.0], [14.6, 121.1]]);

        $session = $service->createSession($route->id, 'osrm', $polyline, [], null, -10); // Expired 10m ago

        $this->assertTrue($session->isExpired());

        // Should not be resolved by active session finder
        $active = $service->findActiveSession($route->id, 'osrm');
        $this->assertNull($active);

        // Pruning should delete it
        $service->pruneExpired();
        $this->assertNull(RouteGenerationSession::find($session->id));
    }
}
