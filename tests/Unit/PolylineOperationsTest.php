<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ValueObjects\Coordinate;
use App\Services\ValueObjects\Polyline;

class PolylineOperationsTest extends TestCase
{
    public function test_polyline_equality()
    {
        $p1 = Polyline::fromArray([[14.5, 121.0], [14.6, 121.1]]);
        $p2 = Polyline::fromArray([[14.5, 121.0], [14.6, 121.1]]);
        $p3 = Polyline::fromArray([[14.5, 121.0], [14.7, 121.2]]);

        $this->assertTrue($p1->equals($p2));
        $this->assertFalse($p1->equals($p3));
    }

    public function test_polyline_bounds_and_center()
    {
        $polyline = Polyline::fromArray([
            [14.5, 121.0],
            [14.7, 121.2],
            [14.6, 121.1],
        ]);

        $bounds = $polyline->getBounds();
        $this->assertEquals(14.7, $bounds['north']);
        $this->assertEquals(14.5, $bounds['south']);
        $this->assertEquals(121.2, $bounds['east']);
        $this->assertEquals(121.0, $bounds['west']);

        $center = $polyline->getCenter();
        $this->assertEquals(14.6, $center['lat']);
        $this->assertEquals(121.1, $center['lng']);
    }

    public function test_polyline_closed_loop()
    {
        $open = Polyline::fromArray([[14.5, 121.0], [14.6, 121.1], [14.7, 121.2]]);
        $closed = Polyline::fromArray([[14.5, 121.0], [14.6, 121.1], [14.5, 121.0]]);

        $this->assertFalse($open->isClosedLoop());
        $this->assertTrue($closed->isClosedLoop());
    }

    public function test_polyline_duplicate_vertices()
    {
        $polyline = Polyline::fromArray([
            [14.5, 121.0],
            [14.5, 121.0],
            [14.6, 121.1],
            [14.6, 121.1],
        ]);

        $this->assertEquals(2, $polyline->getDuplicateVerticesCount());
    }
}
