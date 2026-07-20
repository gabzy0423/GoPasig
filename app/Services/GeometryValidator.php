<?php

namespace App\Services;

use App\Services\Contracts\GeometryValidatorInterface;
use App\Services\Contracts\GeospatialServiceInterface;
use App\Services\ValueObjects\Coordinate;
use App\Services\ValueObjects\Polyline;
use App\Models\SystemSetting;
use App\Enums\GeometryStatus;
use App\Data\ValidationResult;

class GeometryValidator implements GeometryValidatorInterface
{
    private GeospatialServiceInterface $geospatial;

    public function __construct(GeospatialServiceInterface $geospatial)
    {
        $this->geospatial = $geospatial;
    }

    public function validateCoordinates(Coordinate $coord): array
    {
        $lat = $coord->getLatitude();
        $lng = $coord->getLongitude();

        $northBound = (float) SystemSetting::get('coordinates_bounds_north_latitude', 14.85);
        $southBound = (float) SystemSetting::get('coordinates_bounds_south_latitude', 14.30);
        $eastBound = (float) SystemSetting::get('coordinates_bounds_east_longitude', 121.20);
        $westBound = (float) SystemSetting::get('coordinates_bounds_west_longitude', 120.95);

        if ($lat < $southBound || $lat > $northBound) {
            return [
                'valid' => false,
                'error' => "Latitude {$lat} is outside service area bounds ({$southBound} to {$northBound})"
            ];
        }

        if ($lng < $westBound || $lng > $eastBound) {
            return [
                'valid' => false,
                'error' => "Longitude {$lng} is outside service area bounds ({$westBound} to {$eastBound})"
            ];
        }

        return ['valid' => true];
    }

    public function validatePolyline(Polyline $polyline): ValidationResult
    {
        $issues = [];
        $warnings = [];

        if ($polyline->isEmpty()) {
            return new ValidationResult(GeometryStatus::INVALID, ['Route polyline cannot be empty']);
        }

        $coords = $polyline->getCoordinates();

        if (count($coords) < 2) {
            return new ValidationResult(GeometryStatus::INVALID, ['Route polyline must have at least 2 points (origin and destination)']);
        }

        // Validate each coordinate pair
        foreach ($coords as $index => $coord) {
            $coordValidation = $this->validateCoordinates($coord);
            if (!$coordValidation['valid']) {
                $issues[] = "Point {$index}: " . $coordValidation['error'];
            }
        }

        // Check for Realistic Geometry jumps
        $maxJumpKm = (float) SystemSetting::get('max_polyline_jump_km', 50.0);
        for ($i = 0; $i < count($coords) - 1; $i++) {
            $distance = $this->geospatial->calculateDistanceKm($coords[$i], $coords[$i + 1]);
            if ($distance > $maxJumpKm) {
                $issues[] = "Point {$i} to " . ($i + 1) . ": Excessive distance jump of " . round($distance, 2) . " km exceeds threshold of {$maxJumpKm} km";
            }
        }

        $status = empty($issues) ? GeometryStatus::VALID : GeometryStatus::INVALID;
        return new ValidationResult($status, $issues, $warnings);
    }

    public function validateSegments(Polyline $polyline): ValidationResult
    {
        $issues = [];
        $warnings = [];

        $coords = $polyline->getCoordinates();
        $n = count($coords);

        if ($n < 2) {
            return new ValidationResult(GeometryStatus::VALID);
        }

        // 1. Duplicates (consecutive identical points are a blocking issue: INVALID)
        for ($i = 0; $i < $n - 1; $i++) {
            if ($coords[$i]->equals($coords[$i + 1])) {
                $issues[] = "Duplicate consecutive points at index {$i} and " . ($i + 1);
            }
        }

        // 2. Min segment length (warning: non-blocking)
        $minSegmentM = (float) SystemSetting::get('min_segment_length_meters', 1.0);
        for ($i = 0; $i < $n - 1; $i++) {
            if ($coords[$i]->equals($coords[$i + 1])) {
                continue;
            }
            $dist = $this->geospatial->calculateDistanceKm($coords[$i], $coords[$i + 1]) * 1000.0;
            if ($dist < $minSegmentM) {
                $warnings[] = "Segment between vertex {$i} and " . ($i + 1) . " is too short (" . round($dist, 2) . "m)";
            }
        }

        // 3. Self-intersections (warning: non-blocking)
        $intersections = 0;
        for ($i = 0; $i < $n - 2; $i++) {
            for ($j = $i + 2; $j < $n - 1; $j++) {
                if ($this->segmentsIntersect($coords[$i], $coords[$i + 1], $coords[$j], $coords[$j + 1])) {
                    $intersections++;
                    if ($intersections <= 5) {
                        $warnings[] = "Self-intersection: segment {$i}-" . ($i + 1) . " crosses segment {$j}-" . ($j + 1);
                    }
                }
            }
        }

        if ($intersections > 5) {
            $warnings[] = "Total self-intersections: {$intersections}";
        }

        $status = GeometryStatus::VALID;
        if (!empty($issues)) {
            $status = GeometryStatus::INVALID;
        } elseif (!empty($warnings)) {
            $status = GeometryStatus::WARNING;
        }

        return new ValidationResult($status, $issues, $warnings);
    }

    private function ccw(Coordinate $a, Coordinate $b, Coordinate $c): bool
    {
        return ($c->getLatitude() - $a->getLatitude()) * ($b->getLongitude() - $a->getLongitude()) >
               ($b->getLatitude() - $a->getLatitude()) * ($c->getLongitude() - $a->getLongitude());
    }

    private function segmentsIntersect(Coordinate $a, Coordinate $b, Coordinate $c, Coordinate $d): bool
    {
        if ($a->equals($c) || $a->equals($d) || $b->equals($c) || $b->equals($d)) {
            return false;
        }

        return ($this->ccw($a, $c, $d) != $this->ccw($b, $c, $d)) &&
               ($this->ccw($a, $b, $c) != $this->ccw($a, $b, $d));
    }
}
