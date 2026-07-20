<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ValueObjects\Coordinate;
use App\Services\GeospatialService;
use App\Services\Spatial\GeofenceEngine;

class GeofenceEngineTest extends TestCase
{
    private GeofenceEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new GeofenceEngine(new GeospatialService());
    }

    public function test_is_inside_circle()
    {
        $center = new Coordinate(14.5593, 121.0805); // SPED Terminal
        
        // Point very close
        $insidePoint = new Coordinate(14.55932, 121.08052);
        // Point far away
        $outsidePoint = new Coordinate(14.5650, 121.0850);

        $this->assertTrue($this->engine->isInsideCircle($insidePoint, $center, 30.0));
        $this->assertFalse($this->engine->isInsideCircle($outsidePoint, $center, 30.0));
    }

    public function test_is_inside_polygon()
    {
        // Simple square polygon around (14.5, 121.0)
        $polygon = [
            [14.49, 120.99],
            [14.51, 120.99],
            [14.51, 121.01],
            [14.49, 121.01],
            [14.49, 120.99]
        ];

        $insidePoint = new Coordinate(14.50, 121.00);
        $outsidePoint = new Coordinate(14.52, 121.00);

        $this->assertTrue($this->engine->isInsidePolygon($insidePoint, $polygon));
        $this->assertFalse($this->engine->isInsidePolygon($outsidePoint, $polygon));
    }
}
