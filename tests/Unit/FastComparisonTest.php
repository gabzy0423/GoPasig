<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Routing\RouteComparisonService;
use App\Services\ValueObjects\Polyline;

class FastComparisonTest extends TestCase
{
    public function test_fast_comparison_metrics()
    {
        $comparer = new RouteComparisonService();

        // Original polyline: 3 points
        $original = Polyline::fromArray([
            [14.5, 121.0],
            [14.55, 121.05],
            [14.6, 121.1]
        ]);

        // Generated polyline: 4 points, slightly shifted
        $generated = Polyline::fromArray([
            [14.5, 121.0],
            [14.53, 121.03],
            [14.57, 121.07],
            [14.6, 121.1]
        ]);

        $metrics = $comparer->compareFast($original, $generated);

        $this->assertNotNull($metrics->lengthDifferenceKm);
        $this->assertNotNull($metrics->vertexDifference);
        $this->assertNotNull($metrics->boundingBoxOverlapPercent);
        $this->assertNotNull($metrics->hausdorffDistanceMeters);

        $this->assertEquals(1, $metrics->vertexDifference);
        $this->assertGreaterThan(0.0, $metrics->boundingBoxOverlapPercent);
        $this->assertGreaterThan(0.0, $metrics->hausdorffDistanceMeters);
        $this->assertFalse($metrics->advancedAnalysisPerformed);
        $this->assertNull($metrics->frechetSimilarityPercent);
    }
}
