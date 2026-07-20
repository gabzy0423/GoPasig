<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ValueObjects\Coordinate;
use App\Services\ValueObjects\Polyline;

class PolylineTest extends TestCase
{
    public function test_polyline_properties_and_accessors()
    {
        $c1 = new Coordinate(14.5593, 121.0805);
        $c2 = new Coordinate(14.5613, 121.0825);

        $polyline = new Polyline([$c1, $c2]);

        $this->assertEquals(2, $polyline->count());
        $this->assertFalse($polyline->isEmpty());
        $this->assertInstanceOf(Coordinate::class, $polyline->getCoordinates()[0]);
    }

    public function test_polyline_from_array_and_back()
    {
        $rawCoords = [
            [14.5593, 121.0805],
            [14.5613, 121.0825],
        ];

        $polyline = Polyline::fromArray($rawCoords);
        $this->assertEquals(2, $polyline->count());

        $latLngs = $polyline->toLatLngs();
        $this->assertEquals($rawCoords, $latLngs);
    }
}
