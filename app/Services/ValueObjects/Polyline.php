<?php

namespace App\Services\ValueObjects;

class Polyline
{
    /** @var Coordinate[] */
    private array $coordinates;

    public function __construct(array $coordinates = [])
    {
        $this->coordinates = array_map(function ($coord) {
            return $coord instanceof Coordinate ? $coord : Coordinate::fromArray((array) $coord);
        }, $coordinates);
    }

    /**
     * @return Coordinate[]
     */
    public function getCoordinates(): array
    {
        return $this->coordinates;
    }

    public function count(): int
    {
        return count($this->coordinates);
    }

    public function isEmpty(): bool
    {
        return empty($this->coordinates);
    }

    public function toArray(): array
    {
        return array_map(fn(Coordinate $c) => $c->toArray(), $this->coordinates);
    }

    public function toLatLngs(): array
    {
        return array_map(fn(Coordinate $c) => $c->toLatLngArray(), $this->coordinates);
    }

    public static function fromArray(array $coords): self
    {
        $points = [];
        foreach ($coords as $point) {
            if (is_array($point)) {
                if (isset($point['latitude']) || isset($point['lat'])) {
                    $points[] = Coordinate::fromArray($point);
                } else {
                    $points[] = Coordinate::fromLatLngArray($point);
                }
            } elseif ($point instanceof Coordinate) {
                $points[] = $point;
            }
        }
        return new self($points);
    }

    // --- New Operations Methods ---

    /**
     * Compare equality of coordinates with another polyline.
     */
    public function equals(Polyline $other): bool
    {
        if ($this->count() !== $other->count()) {
            return false;
        }

        $coordsB = $other->getCoordinates();
        foreach ($this->coordinates as $i => $coordA) {
            if (!$coordA->equals($coordsB[$i])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get bounding box coordinates.
     */
    public function getBounds(): array
    {
        if ($this->isEmpty()) {
            return ['north' => 0.0, 'south' => 0.0, 'east' => 0.0, 'west' => 0.0];
        }

        $lats = array_map(fn(Coordinate $c) => $c->getLatitude(), $this->coordinates);
        $lngs = array_map(fn(Coordinate $c) => $c->getLongitude(), $this->coordinates);

        return [
            'north' => max($lats),
            'south' => min($lats),
            'east' => max($lngs),
            'west' => min($lngs),
        ];
    }

    /**
     * Get bounding box center point.
     */
    public function getCenter(): array
    {
        $bounds = $this->getBounds();
        return [
            'lat' => ($bounds['north'] + $bounds['south']) / 2.0,
            'lng' => ($bounds['east'] + $bounds['west']) / 2.0,
        ];
    }

    /**
     * Get segment count.
     */
    public function getSegmentCount(): int
    {
        return max(0, $this->count() - 1);
    }

    /**
     * Check if first and last vertices are within a distance tolerance.
     */
    public function isClosedLoop(float $toleranceMeters = 50.0): bool
    {
        if ($this->count() < 3) {
            return false;
        }

        $first = $this->coordinates[0];
        $last = $this->coordinates[$this->count() - 1];

        return ($this->calculateDistanceKm($first, $last) * 1000.0) <= $toleranceMeters;
    }

    /**
     * Count duplicate consecutive points.
     */
    public function getDuplicateVerticesCount(): int
    {
        $duplicates = 0;
        $n = $this->count();
        for ($i = 0; $i < $n - 1; $i++) {
            if ($this->coordinates[$i]->equals($this->coordinates[$i + 1])) {
                $duplicates++;
            }
        }
        return $duplicates;
    }

    /**
     * Calculate total polyline length in kilometers.
     */
    public function getLengthKm(): float
    {
        $total = 0.0;
        $n = $this->count();
        for ($i = 0; $i < $n - 1; $i++) {
            $total += $this->calculateDistanceKm($this->coordinates[$i], $this->coordinates[$i + 1]);
        }
        return $total;
    }

    /**
     * Calculate average segment length in meters.
     */
    public function getAverageSegmentLengthM(): float
    {
        $segments = $this->getSegmentCount();
        if ($segments === 0) {
            return 0.0;
        }
        return ($this->getLengthKm() * 1000.0) / $segments;
    }

    /**
     * Calculate minimum segment length in meters.
     */
    public function getMinSegmentLengthM(): float
    {
        $n = $this->count();
        if ($n < 2) {
            return 0.0;
        }

        $min = null;
        for ($i = 0; $i < $n - 1; $i++) {
            if ($this->coordinates[$i]->equals($this->coordinates[$i + 1])) {
                continue;
            }
            $dist = $this->calculateDistanceKm($this->coordinates[$i], $this->coordinates[$i + 1]) * 1000.0;
            if ($min === null || $dist < $min) {
                $min = $dist;
            }
        }

        return $min ?? 0.0;
    }

    /**
     * Calculate maximum segment length in meters.
     */
    public function getMaxSegmentLengthM(): float
    {
        $n = $this->count();
        if ($n < 2) {
            return 0.0;
        }

        $max = 0.0;
        for ($i = 0; $i < $n - 1; $i++) {
            $dist = $this->calculateDistanceKm($this->coordinates[$i], $this->coordinates[$i + 1]) * 1000.0;
            if ($dist > $max) {
                $max = $dist;
            }
        }

        return $max;
    }

    /**
     * Get maximum vertex spacing in meters.
     */
    public function getMaxVertexSpacingM(): float
    {
        return $this->getMaxSegmentLengthM();
    }

    /**
     * Helper to compute distance between two coordinates in kilometers (Haversine).
     */
    private function calculateDistanceKm(Coordinate $c1, Coordinate $c2): float
    {
        $earthRadius = 6371.0;
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
