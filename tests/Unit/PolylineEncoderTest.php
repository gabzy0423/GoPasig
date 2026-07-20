<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ValueObjects\Polyline;
use App\Services\PolylineEncoder;

class PolylineEncoderTest extends TestCase
{
    public function test_encode_and_decode_round_trip()
    {
        $coords = [
            [38.5, -120.2],
            [40.7, -120.95],
            [43.252, -126.453],
        ];

        $polyline = Polyline::fromArray($coords);
        $encoded = PolylineEncoder::encode($polyline);

        $this->assertNotEmpty($encoded);

        $decoded = PolylineEncoder::decode($encoded);
        $this->assertEquals(3, $decoded->count());

        $decodedCoords = $decoded->toLatLngs();
        $this->assertEquals(38.5, round($decodedCoords[0][0], 5));
        $this->assertEquals(-120.2, round($decodedCoords[0][1], 5));
    }
}
