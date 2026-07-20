<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Routing\FrechetComparisonEngine;
use App\Services\ValueObjects\Polyline;

class FrechetComparisonTest extends TestCase
{
    public function test_frechet_similarity_percentage_calculations()
    {
        $engine = new FrechetComparisonEngine();

        // 1. Identical Polylines ➔ 100% similarity
        $polyA = Polyline::fromArray([
            [14.5, 121.0],
            [14.55, 121.05],
            [14.6, 121.1]
        ]);
        $polyB = Polyline::fromArray([
            [14.5, 121.0],
            [14.55, 121.05],
            [14.6, 121.1]
        ]);

        $similarity = $engine->calculateSimilarity($polyA, $polyB);
        $this->assertEquals(100.0, $similarity);

        // 2. Slightly Shifted Polylines ➔ High similarity (e.g. >80%)
        $polyShifted = Polyline::fromArray([
            [14.5, 121.0001],
            [14.55, 121.0501],
            [14.6, 121.1001]
        ]);
        $similarityShifted = $engine->calculateSimilarity($polyA, $polyShifted);
        $this->assertGreaterThan(80.0, $similarityShifted);
        $this->assertLessThan(100.0, $similarityShifted);

        // 3. Different Polylines ➔ Low similarity (e.g. <50%)
        $polyDifferent = Polyline::fromArray([
            [14.5, 121.0],
            [14.7, 121.2], // Large shift
            [14.6, 121.1]
        ]);
        $similarityDifferent = $engine->calculateSimilarity($polyA, $polyDifferent);
        $this->assertLessThan(50.0, $similarityDifferent);
    }
}
