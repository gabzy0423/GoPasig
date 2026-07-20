<?php

namespace App\Data;

final class PositionUpdateResult
{
    public function __construct(
        public readonly int $busId,
        public readonly ?int $tripId,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly float $speed,
        public readonly float $heading,
        public readonly string $status,
        public readonly string $timestamp
    ) {}
}
