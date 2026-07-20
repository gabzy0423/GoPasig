<?php

namespace App\Services\Contracts;

use App\Services\ValueObjects\Coordinate;
use App\Services\ValueObjects\Polyline;
use App\Data\ValidationResult;

interface GeometryValidatorInterface
{
    /**
     * Validate coordinates fall within LGU boundaries.
     */
    public function validateCoordinates(Coordinate $coord): array;

    /**
     * Validate route polyline constraints.
     */
    public function validatePolyline(Polyline $polyline): ValidationResult;

    /**
     * Validate segment rules (duplicate consecutive nodes, min segment length, intersections).
     */
    public function validateSegments(Polyline $polyline): ValidationResult;
}
