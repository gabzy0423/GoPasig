<?php

namespace App\Services\Routing;

use App\Services\Contracts\RouteQualityInterface;
use App\Data\ComparisonResult;
use App\Data\RouteQualityResult;

class RouteQualityService implements RouteQualityInterface
{
    /**
     * Compile quality grade based on comparisons and pre-computed geometry metrics.
     */
    public function analyze(ComparisonResult $comparison, array $geometryMetrics): RouteQualityResult
    {
        $score = 100;
        $warnings = [];
        $recommendations = [];

        // 1. Evaluate Hausdorff Distance
        $hausdorff = $comparison->hausdorffDistanceMeters;
        if ($hausdorff > 200.0) {
            $score -= 15;
            $warnings[] = "Large spatial deviation detected (Hausdorff: {$hausdorff}m).";
        } elseif ($hausdorff > 100.0) {
            $score -= 10;
            $warnings[] = "Moderate spatial deviation detected (Hausdorff: {$hausdorff}m).";
        } elseif ($hausdorff > 50.0) {
            $score -= 5;
        }

        // 2. Evaluate Bounding Box Overlap
        $overlap = $comparison->boundingBoxOverlapPercent;
        $minOverlap = (float) config('routing.quality_thresholds.min_overlap_percentage', 70.0);
        if ($overlap < $minOverlap) {
            $score -= 20;
            $warnings[] = "Low bounding box overlap: {$overlap}% (threshold: {$minOverlap}%).";
        } elseif ($overlap < 90.0) {
            $score -= 5;
        }

        // 3. Evaluate Length Difference
        $lenDiff = $comparison->lengthDifferenceKm;
        if ($lenDiff > 1.0) {
            $score -= 15;
            $warnings[] = "Significant length deviation: {$lenDiff} km.";
        } elseif ($lenDiff > 0.5) {
            $score -= 10;
            $warnings[] = "Moderate length deviation: {$lenDiff} km.";
        } elseif ($lenDiff > 0.2) {
            $score -= 5;
        }

        // 4. Evaluate Geometry Metrics (Node Spacing, Intersections, Duplicates)
        $maxSpacing = (float) ($geometryMetrics['max_spacing_meters'] ?? 0.0);
        $spacingLimit = (float) config('routing.quality_thresholds.max_spacing_deviation_meters', 200.0);
        if ($maxSpacing > $spacingLimit) {
            $score -= 15;
            $warnings[] = "Large spacing between consecutive nodes detected ({$maxSpacing}m exceeds limit of {$spacingLimit}m).";
            $recommendations[] = "Verify route alignment around segments with large node spacings.";
        }

        if (!empty($geometryMetrics['has_self_intersections'])) {
            $score -= 15;
            $warnings[] = "Self-intersections detected in geometry path.";
            $recommendations[] = "Fix self-intersecting segments using the manual map editor.";
        }

        if (($geometryMetrics['duplicate_vertices_count'] ?? 0) > 0) {
            $score -= 5;
            $warnings[] = "Duplicate vertices found in path.";
        }

        // Ensure score stays in 0-100 bounds
        $score = max(0, min(100, $score));

        // 5. Determine Grade
        if ($score >= 90) {
            $grade = 'Excellent';
        } elseif ($score >= 75) {
            $grade = 'Good';
        } elseif ($score >= 50) {
            $grade = 'Fair';
        } else {
            $grade = 'Poor';
        }

        // 6. Build Recommendations
        if ($score < 75) {
            $recommendations[] = "Manual route review is highly suggested.";
        }
        if (empty($warnings) && $score >= 90) {
            $recommendations[] = "Ready for deployment.";
        }

        return new RouteQualityResult(
            score: $score,
            grade: $grade,
            warnings: $warnings,
            recommendations: $recommendations
        );
    }
}
