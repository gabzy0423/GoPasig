<?php

namespace App\Services;

use App\Services\ValueObjects\Coordinate;
use App\Services\ValueObjects\Polyline;

class GeometrySimplifier
{
    /**
     * Simplify a Polyline using the Douglas-Peucker algorithm.
     */
    public function simplify(Polyline $polyline, float $toleranceDegrees = 0.00005): Polyline
    {
        $points = $polyline->getCoordinates();
        $n = count($points);
        if ($n < 3) {
            return $polyline;
        }

        $keep = array_fill(0, $n, true);
        $this->douglasPeucker($points, 0, $n - 1, $toleranceDegrees, $keep);

        $simplified = [];
        for ($i = 0; $i < $n; $i++) {
            if ($keep[$i]) {
                $simplified[] = $points[$i];
            }
        }

        return new Polyline($simplified);
    }

    private function douglasPeucker(array $points, int $start, int $end, float $tolerance, array &$keep): void
    {
        $maxDist = 0.0;
        $index = 0;

        for ($i = $start + 1; $i < $end; $i++) {
            $dist = $this->perpendicularDistance($points[$i], $points[$start], $points[$end]);
            if ($dist > $maxDist) {
                $maxDist = $dist;
                $index = $i;
            }
        }

        if ($maxDist > $tolerance) {
            $keep[$index] = true;
            $this->douglasPeucker($points, $start, $index, $tolerance, $keep);
            $this->douglasPeucker($points, $index, $end, $tolerance, $keep);
        } else {
            for ($i = $start + 1; $i < $end; $i++) {
                $keep[$i] = false;
            }
        }
    }

    private function perpendicularDistance(Coordinate $p, Coordinate $start, Coordinate $end): float
    {
        $x = $p->getLongitude();
        $y = $p->getLatitude();
        $x1 = $start->getLongitude();
        $y1 = $start->getLatitude();
        $x2 = $end->getLongitude();
        $y2 = $end->getLatitude();

        $dx = $x2 - $x1;
        $dy = $y2 - $y1;

        if ($dx === 0.0 && $dy === 0.0) {
            return sqrt(pow($x - $x1, 2) + pow($y - $y1, 2));
        }

        $numerator = abs($dy * $x - $dx * $y + $x2 * $y1 - $y2 * $x1);
        $denominator = sqrt(pow($dx, 2) + pow($dy, 2));

        return $numerator / $denominator;
    }
}
