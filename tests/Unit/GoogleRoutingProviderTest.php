<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Providers\GoogleRoutingProvider;
use App\Services\ValueObjects\Coordinate;
use App\Exceptions\RoutingProviderException;
use Illuminate\Support\Facades\Http;

class GoogleRoutingProviderTest extends TestCase
{
    public function test_google_routing_provider_success()
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'routes' => [
                    [
                        'overview_polyline' => [
                            // Encoded polyline representing: [14.5, 121.0] -> [14.6, 121.1]
                            'points' => '_`owA_yoaV_pR_pR'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $provider = new GoogleRoutingProvider();
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

    public function test_google_routing_provider_api_error()
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'REQUEST_DENIED',
                'error_message' => 'The provided API key is invalid.'
            ], 200)
        ]);

        $provider = new GoogleRoutingProvider();
        $waypoints = [
            new Coordinate(14.5, 121.0),
            new Coordinate(14.6, 121.1)
        ];

        $this->expectException(RoutingProviderException::class);
        $this->expectExceptionMessage('REQUEST_DENIED');

        $provider->getRouteGeometry($waypoints);
    }

    public function test_google_routing_provider_http_failure()
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([], 500)
        ]);

        $provider = new GoogleRoutingProvider();
        $waypoints = [
            new Coordinate(14.5, 121.0),
            new Coordinate(14.6, 121.1)
        ];

        $this->expectException(RoutingProviderException::class);
        $this->expectExceptionMessage('status code 500');

        $provider->getRouteGeometry($waypoints);
    }

    public function test_google_routing_provider_insufficient_waypoints()
    {
        $provider = new GoogleRoutingProvider();
        
        $this->expectException(RoutingProviderException::class);
        $this->expectExceptionMessage('origin and destination waypoints are required');

        $provider->getRouteGeometry([
            new Coordinate(14.5, 121.0)
        ]);
    }
}
