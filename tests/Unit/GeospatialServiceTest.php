<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ValueObjects\Coordinate;
use App\Services\GeospatialService;

class GeospatialServiceTest extends TestCase
{
    private GeospatialService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GeospatialService();
    }

    public function test_calculate_distance_between_coordinates()
    {
        // Coordinates for Manila and Quezon City (approx 10.5 km apart)
        $manila = new Coordinate(14.5995, 120.9842);
        $qc = new Coordinate(14.6760, 121.0437);

        $distanceMeters = $this->service->calculateDistance($manila, $qc);
        $distanceKm = $this->service->calculateDistanceKm($manila, $qc);

        // Expect distance to be within reasonable bounds (approx 10,500m)
        $this->assertGreaterThan(9000, $distanceMeters);
        $this->assertLessThan(12000, $distanceMeters);
        $this->assertEquals($distanceMeters / 1000.0, $distanceKm);
    }

    public function test_calculate_bearing_between_coordinates()
    {
        // East heading test
        $c1 = new Coordinate(0.0, 0.0);
        $c2 = new Coordinate(0.0, 1.0);

        $bearing = $this->service->calculateBearing($c1, $c2);
        $this->assertEquals(90.0, round($bearing));
    }
}
