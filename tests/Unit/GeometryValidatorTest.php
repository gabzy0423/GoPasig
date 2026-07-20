<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\ValueObjects\Coordinate;
use App\Services\ValueObjects\Polyline;
use App\Services\GeospatialService;
use App\Services\GeometryValidator;
use App\Models\SystemSetting;

class GeometryValidatorTest extends TestCase
{
    use RefreshDatabase;

    private GeometryValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new GeometryValidator(new GeospatialService());
    }

    public function test_validate_coordinates_within_boundaries()
    {
        // Inside NCR bounds
        $c1 = new Coordinate(14.5593, 121.0805);
        $res1 = $this->validator->validateCoordinates($c1);
        $this->assertTrue($res1['valid']);

        // Outside NCR bounds
        $c2 = new Coordinate(10.0000, 120.0000);
        $res2 = $this->validator->validateCoordinates($c2);
        $this->assertFalse($res2['valid']);
    }

    public function test_validate_polyline_constraints()
    {
        // Valid polyline
        $p1 = new Polyline([
            new Coordinate(14.5593, 121.0805),
            new Coordinate(14.5613, 121.0825),
        ]);
        $res1 = $this->validator->validatePolyline($p1);
        $this->assertTrue($res1['valid']);

        // Less than 2 nodes
        $p2 = new Polyline([
            new Coordinate(14.5593, 121.0805),
        ]);
        $res2 = $this->validator->validatePolyline($p2);
        $this->assertFalse($res2['valid']);
    }
}
