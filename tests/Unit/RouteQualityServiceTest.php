<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Routing\RouteQualityService;
use App\Data\ComparisonResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RouteQualityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_quality_analysis_excellent_route()
    {
        $service = new RouteQualityService();

        // 100% overlap, 5m Hausdorff, 0.01km diff
        $comparison = new ComparisonResult(
            lengthDifferenceKm: 0.01,
            vertexDifference: 0,
            boundingBoxOverlapPercent: 98.0,
            hausdorffDistanceMeters: 5.0
        );

        $metrics = [
            'max_spacing_meters' => 50.0,
            'duplicate_vertices_count' => 0,
            'has_self_intersections' => false
        ];

        $result = $service->analyze($comparison, $metrics);

        $this->assertEquals(100, $result->score);
        $this->assertEquals('Excellent', $result->grade);
        $this->assertEmpty($result->warnings);
        $this->assertContains('Ready for deployment.', $result->recommendations);
    }

    public function test_quality_analysis_exceeding_spacing_threshold_generates_warnings()
    {
        config(['routing.quality_thresholds.max_spacing_deviation_meters' => 200.0]);
        $service = new RouteQualityService();

        $comparison = new ComparisonResult(
            lengthDifferenceKm: 0.05,
            vertexDifference: 1,
            boundingBoxOverlapPercent: 95.0,
            hausdorffDistanceMeters: 10.0
        );

        // Max spacing of 250m exceeds 200m limit
        $metrics = [
            'max_spacing_meters' => 250.0,
            'duplicate_vertices_count' => 0,
            'has_self_intersections' => false
        ];

        $result = $service->analyze($comparison, $metrics);

        $this->assertLessThan(100, $result->score);
        $this->assertContains('Large spacing between consecutive nodes detected (250m exceeds limit of 200m).', $result->warnings);
        $this->assertContains('Verify route alignment around segments with large node spacings.', $result->recommendations);
    }

    public function test_quality_analysis_self_intersections_and_low_overlap()
    {
        $service = new RouteQualityService();

        $comparison = new ComparisonResult(
            lengthDifferenceKm: 1.5,
            vertexDifference: 10,
            boundingBoxOverlapPercent: 55.0, // Low overlap
            hausdorffDistanceMeters: 300.0 // Large Hausdorff
        );

        $metrics = [
            'max_spacing_meters' => 50.0,
            'duplicate_vertices_count' => 0,
            'has_self_intersections' => true // Self intersection
        ];

        $result = $service->analyze($comparison, $metrics);

        $this->assertLessThan(50, $result->score);
        $this->assertEquals('Poor', $result->grade);
        $this->assertContains('Self-intersections detected in geometry path.', $result->warnings);
    }
}
