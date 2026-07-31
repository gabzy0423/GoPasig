<?php

namespace App\Data;

use App\Models\Bus;
use App\Models\CommuterTrip;

final class BoardingDetectionResult
{
    public function __construct(
        public readonly ?Bus $candidateBus = null,
        public readonly ?float $distanceMeters = null,
        public readonly ?CommuterTrip $journey = null,
        public readonly bool $boarded = false,
        public readonly ?string $reason = null,
    ) {}

    public static function none(?string $reason = null): self
    {
        return new self(reason: $reason);
    }

    public static function candidate(Bus $bus, float $distanceMeters): self
    {
        return new self(candidateBus: $bus, distanceMeters: $distanceMeters);
    }

    public static function boarded(CommuterTrip $journey, Bus $bus, float $distanceMeters): self
    {
        return new self(candidateBus: $bus, distanceMeters: $distanceMeters, journey: $journey, boarded: true);
    }
}
