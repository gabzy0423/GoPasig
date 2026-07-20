<?php

namespace App\Services\Contracts;

use App\Data\ComparisonResult;
use App\Data\RouteQualityResult;

interface RouteQualityInterface
{
    /**
     * Compile quality grade based on comparisons and pre-computed geometry metrics.
     */
    public function analyze(ComparisonResult $comparison, array $geometryMetrics): RouteQualityResult;
}
