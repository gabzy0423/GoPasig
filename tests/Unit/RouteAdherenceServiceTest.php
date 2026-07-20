<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Routing\RouteAdherenceService;
use App\Services\ValueObjects\Coordinate;

class RouteAdherenceServiceTest extends TestCase
{
    public function test_route_adherence_on_route()
    {
        $service = new RouteAdherenceService();

        $polyline = [
            [14.5, 121.0],
            [14.6, 121.1]
        ];

        // Vehicle is exactly on the segment line
        $pos = new Coordinate(14.55, 121.05);

        $result = $service->checkAdherence(1, $pos, $polyline);

        $this->assertFalse($result->isDeviated);
        $this->assertEquals('Minor', $result->severity);
    }

    public function test_route_adherence_major_deviation()
    {
        config(['fleet.deviation.minor_meters' => 50.0]);
        config(['fleet.deviation.major_meters' => 150.0]);
        config(['fleet.deviation.critical_meters' => 300.0]);

        $service = new RouteAdherenceService();

        $polyline = [
            [14.5, 121.0],
            [14.6, 121.1]
        ];

        // Point is off-axis (~196m) to fall between 150m and 300m
        $pos = new Coordinate(14.5525, 121.050);

        $result = $service->checkAdherence(1, $pos, $polyline);

        $this->assertTrue($result->isDeviated);
        $this->assertEquals('Major', $result->severity);
        $this->assertGreaterThan(150.0, $result->distanceMeters);
        $this->assertLessThan(300.0, $result->distanceMeters);
    }
}
