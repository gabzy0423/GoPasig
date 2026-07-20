<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Providers\OsrmRoutingProvider;
use App\Services\ValueObjects\Coordinate;
use App\Exceptions\RoutingProviderException;
use Illuminate\Support\Facades\Http;

class OsrmRoutingProviderTest extends TestCase
{
    public function test_osrm_routing_provider_success()
    {
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

        $provider = new OsrmRoutingProvider();
        $waypoints = [
            new Coordinate(14.5, 121.0),
            new Coordinate(14.6, 121.1)
        ];

        $polyline = $provider->getRouteGeometry($waypoints);

        $this->assertGreaterThanOrEqual(2, $polyline->count());
        $coords = $polyline->getCoordinates();
        $this->assertEquals(14.5, round($coords[0]->getLatitude(), 1));
        $this->assertEquals(121.0, round($coords[0]->getLongitude(), 1));
    }

    public function test_osrm_routing_provider_api_error()
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'NoRoute',
                'message' => 'No route could be found between waypoints.'
            ], 200)
        ]);

        $provider = new OsrmRoutingProvider();
        $waypoints = [
            new Coordinate(14.5, 121.0),
            new Coordinate(14.6, 121.1)
        ];

        $this->expectException(RoutingProviderException::class);
        $this->expectExceptionMessage('NoRoute');

        $provider->getRouteGeometry($waypoints);
    }

    public function test_osrm_routing_provider_http_failure()
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response([], 503)
        ]);

        $provider = new OsrmRoutingProvider();
        $waypoints = [
            new Coordinate(14.5, 121.0),
            new Coordinate(14.6, 121.1)
        ];

        $this->expectException(RoutingProviderException::class);
        $this->expectExceptionMessage('status code 503');

        $provider->getRouteGeometry($waypoints);
    }

    public function test_osrm_routing_provider_insufficient_waypoints()
    {
        $provider = new OsrmRoutingProvider();

        $this->expectException(RoutingProviderException::class);
        $this->expectExceptionMessage('origin and destination waypoints are required');

        $provider->getRouteGeometry([
            new Coordinate(14.5, 121.0)
        ]);
    }
}
