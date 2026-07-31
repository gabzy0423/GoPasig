<?php

namespace App\Data;

use App\Services\ValueObjects\Coordinate;

final class CommuterLocation
{
    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
        public readonly ?float $accuracy = null,
        public readonly ?string $timestamp = null,
    ) {}

    public function coordinate(): Coordinate
    {
        return new Coordinate($this->lat, $this->lng, accuracy: $this->accuracy, timestamp: $this->timestamp);
    }
}
