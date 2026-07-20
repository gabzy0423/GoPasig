<?php

namespace App\Services\Routing;

use App\Services\ValueObjects\Polyline;
use App\Data\ComparisonResult;

class RouteComparisonService
{
    private FastComparisonEngine $fastEngine;
    private FrechetComparisonEngine $frechetEngine;

    public function __construct(
        ?FastComparisonEngine $fastEngine = null,
        ?FrechetComparisonEngine $frechetEngine = null
    ) {
        $this->fastEngine = $fastEngine ?? new FastComparisonEngine();
        $this->frechetEngine = $frechetEngine ?? new FrechetComparisonEngine();
    }

    /**
     * Compare two polylines using fast metrics.
     */
    public function compareFast(Polyline $original, Polyline $generated): ComparisonResult
    {
        return $this->fastEngine->compare($original, $generated);
    }

    /**
     * Compare two polylines including Fréchet shape similarity percentage.
     */
    public function compareAdvanced(Polyline $original, Polyline $generated): ComparisonResult
    {
        $fast = $this->fastEngine->compare($original, $generated);
        $similarity = $this->frechetEngine->calculateSimilarity($original, $generated);

        return new ComparisonResult(
            lengthDifferenceKm: $fast->lengthDifferenceKm,
            vertexDifference: $fast->vertexDifference,
            boundingBoxOverlapPercent: $fast->boundingBoxOverlapPercent,
            hausdorffDistanceMeters: $fast->hausdorffDistanceMeters,
            advancedAnalysisPerformed: true,
            frechetSimilarityPercent: $similarity
        );
    }
}
