<?php

namespace App\Data;

final class RouteDeviationResult
{
    public function __construct(
        public readonly int $tripId,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly float $distanceMeters,
        public readonly string $severity,
        public readonly bool $isDeviated
    ) {}
}
