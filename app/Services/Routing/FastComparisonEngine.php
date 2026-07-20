<?php

namespace App\Services\Routing;

use App\Services\ValueObjects\Polyline;
use App\Services\ValueObjects\Coordinate;
use App\Data\ComparisonResult;

class FastComparisonEngine
{
    public function compare(Polyline $original, Polyline $generated): ComparisonResult
    {
        return new ComparisonResult(
            lengthDifferenceKm: round(abs($generated->getLengthKm() - $original->getLengthKm()), 3),
            vertexDifference: abs($generated->count() - $original->count()),
            boundingBoxOverlapPercent: $this->calculateBoundingBoxOverlap($original, $generated),
            hausdorffDistanceMeters: round($this->calculateHausdorffDistance($original, $generated), 2),
            advancedAnalysisPerformed: false,
            frechetSimilarityPercent: null
        );
    }

    private function calculateHausdorffDistance(Polyline $polylineA, Polyline $polylineB): float
    {
        $coordsA = $polylineA->getCoordinates();
        $coordsB = $polylineB->getCoordinates();

        if (empty($coordsA) || empty($coordsB)) {
            return 0.0;
        }

        $hAB = $this->calculateDirectedHausdorff($coordsA, $coordsB);
        $hBA = $this->calculateDirectedHausdorff($coordsB, $coordsA);

        return max($hAB, $hBA);
    }

    private function calculateDirectedHausdorff(array $coordsA, array $coordsB): float
    {
        $maxMinDist = 0.0;

        foreach ($coordsA as $a) {
            $minDist = null;
            foreach ($coordsB as $b) {
                $dist = $this->calculateDistanceMeters($a, $b);
                if ($minDist === null || $dist < $minDist) {
                    $minDist = $dist;
                }
            }
            if ($minDist !== null && $minDist > $maxMinDist) {
                $maxMinDist = $minDist;
            }
        }

        return $maxMinDist;
    }

    private function calculateDistanceMeters(Coordinate $c1, Coordinate $c2): float
    {
        $earthRadius = 6371000.0; // meters
        $lat1 = deg2rad($c1->getLatitude());
        $lng1 = deg2rad($c1->getLongitude());
        $lat2 = deg2rad($c2->getLatitude());
        $lng2 = deg2rad($c2->getLongitude());

        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;

        $a = sin($dlat / 2) * sin($dlat / 2) +
             cos($lat1) * cos($lat2) *
             sin($dlng / 2) * sin($dlng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function calculateBoundingBoxOverlap(Polyline $polylineA, Polyline $polylineB): float
    {
        $B1 = $polylineA->getBounds();
        $B2 = $polylineB->getBounds();

        $interNorth = min($B1['north'], $B2['north']);
        $interSouth = max($B1['south'], $B2['south']);
        $interEast = min($B1['east'], $B2['east']);
        $interWest = max($B1['west'], $B2['west']);

        if ($interNorth <= $interSouth || $interEast <= $interWest) {
            return 0.0;
        }

        $interArea = ($interNorth - $interSouth) * ($interEast - $interWest);
        $areaB1 = ($B1['north'] - $B1['south']) * ($B1['east'] - $B1['west']);
        $areaB2 = ($B2['north'] - $B2['south']) * ($B2['east'] - $B2['west']);

        $unionArea = $areaB1 + $areaB2 - $interArea;

        if ($unionArea <= 0.0) {
            return 0.0;
        }

        return round(($interArea / $unionArea) * 100.0, 2);
    }
}
