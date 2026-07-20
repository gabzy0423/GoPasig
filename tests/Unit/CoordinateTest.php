<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ValueObjects\Coordinate;

class CoordinateTest extends TestCase
{
    public function test_coordinate_properties_and_accessors()
    {
        $coord = new Coordinate(14.5593, 121.0805, 90.0, 10.0, 35.0, '2026-07-14 12:00:00');

        $this->assertEquals(14.5593, $coord->getLatitude());
        $this->assertEquals(121.0805, $coord->getLongitude());
        $this->assertEquals(90.0, $coord->getBearing());
        $this->assertEquals(10.0, $coord->getAccuracy());
        $this->assertEquals(35.0, $coord->getSpeed());
        $this->assertEquals('2026-07-14 12:00:00', $coord->getTimestamp());
    }

    public function test_coordinate_conversion_to_and_from_array()
    {
        $data = [
            'latitude' => 14.5593,
            'longitude' => 121.0805,
            'bearing' => 45.0,
            'accuracy' => 5.0,
            'speed' => 12.0,
            'timestamp' => '2026-07-14 12:30:00'
        ];

        $coord = Coordinate::fromArray($data);
        $this->assertEquals(14.5593, $coord->getLatitude());
        $this->assertEquals(121.0805, $coord->getLongitude());
        $this->assertEquals(45.0, $coord->getBearing());

        $array = $coord->toArray();
        $this->assertEquals($data, $array);
    }

    public function test_coordinate_equality()
    {
        $coord1 = new Coordinate(14.5593, 121.0805);
        $coord2 = new Coordinate(14.5593, 121.0805);
        $coord3 = new Coordinate(14.5594, 121.0805);

        $this->assertTrue($coord1->equals($coord2));
        $this->assertFalse($coord1->equals($coord3));
    }
}
