<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ValueObjects\Polyline;
use App\Services\GeometrySimplifier;

class GeometrySimplifierTest extends TestCase
{
    public function test_douglas_peucker_simplification()
    {
        $simplifier = new GeometrySimplifier();
        // A straight line with a colinear redundant point in the middle
        $coords = [
            [14.5, 121.0],
            [14.55, 121.05], // Colinear point
            [14.6, 121.1],
        ];

        $polyline = Polyline::fromArray($coords);
        // Using a tolerance that should filter the middle point
        $simplified = $simplifier->simplify($polyline, 0.01);

        $this->assertEquals(2, $simplified->count());
        $this->assertEquals(14.5, $simplified->getCoordinates()[0]->getLatitude());
        $this->assertEquals(14.6, $simplified->getCoordinates()[1]->getLatitude());
    }
}
