<?php

namespace App\Services\Routing;

use App\Services\ValueObjects\Polyline;
use App\Services\ValueObjects\Coordinate;

class FrechetComparisonEngine
{
    /**
     * Compute Fréchet similarity percentage using an iterative bottom-up DP table.
     */
    public function calculateSimilarity(Polyline $polylineA, Polyline $polylineB, float $normalizationThresholdMeters = 500.0): float
    {
        $coordsA = $polylineA->getCoordinates();
        $coordsB = $polylineB->getCoordinates();

        $n = count($coordsA);
        $m = count($coordsB);

        if ($n === 0 || $m === 0) {
            return 0.0;
        }

        // Iterative bottom-up DP table to handle large polylines safely without stack overflows
        $dp = array_fill(0, $n, array_fill(0, $m, 0.0));

        $dp[0][0] = $this->distance($coordsA[0], $coordsB[0]);

        for ($i = 1; $i < $n; $i++) {
            $dp[$i][0] = max($dp[$i - 1][0], $this->distance($coordsA[$i], $coordsB[0]));
        }

        for ($j = 1; $j < $m; $j++) {
            $dp[0][$j] = max($dp[0][$j - 1], $this->distance($coordsA[0], $coordsB[$j]));
        }

        for ($i = 1; $i < $n; $i++) {
            for ($j = 1; $j < $m; $j++) {
                $dist = $this->distance($coordsA[$i], $coordsB[$j]);
                $prevMin = min($dp[$i - 1][$j], $dp[$i - 1][$j - 1], $dp[$i][$j - 1]);
                $dp[$i][$j] = max($prevMin, $dist);
            }
        }

        $frechetDistance = $dp[$n - 1][$m - 1];

        // Convert distance to similarity %
        $similarity = 100.0 - ($frechetDistance / $normalizationThresholdMeters) * 100.0;

        return round(max(0.0, min(100.0, $similarity)), 2);
    }

    private function distance(Coordinate $c1, Coordinate $c2): float
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
}
