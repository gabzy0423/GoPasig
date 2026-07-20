<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Routing\ETAEngine;
use App\Services\ValueObjects\Coordinate;

class ETAEngineTest extends TestCase
{
    public function test_eta_engine_calculates_distance_along_polyline()
    {
        $engine = new ETAEngine();

        $polyline = [
            [14.5, 121.0],
            [14.55, 121.05],
            [14.6, 121.1]
        ];

        // Upcoming stops mapped
        $upcomingStops = [
            [
                'id' => 10,
                'lat' => 14.55,
                'lng' => 121.05
            ],
            [
                'id' => 11,
                'lat' => 14.6,
                'lng' => 121.1
            ]
        ];

        // Vehicle position is on first segment
        $pos = new Coordinate(14.525, 121.025);

        $etas = $engine->calculateETAs(1, $pos, $polyline, $upcomingStops, 10.0); // 10 m/s speed

        $this->assertCount(2, $etas);
        $this->assertEquals(10, $etas[0]->stopId);
        $this->assertEquals(11, $etas[1]->stopId);
        $this->assertGreaterThan(0.0, $etas[0]->distanceRemainingMeters);
        $this->assertGreaterThan($etas[0]->distanceRemainingMeters, $etas[1]->distanceRemainingMeters);
    }
}
