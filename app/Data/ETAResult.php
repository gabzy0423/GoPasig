<?php

namespace App\Data;

final class ETAResult
{
    public function __construct(
        public readonly int $stopId,
        public readonly string $etaTimestamp,
        public readonly float $distanceRemainingMeters,
        public readonly int $delayMinutes
    ) {}

    public function toArray(): array
    {
        return [
            'stop_id' => $this->stopId,
            'eta_timestamp' => $this->etaTimestamp,
            'distance_remaining_meters' => $this->distanceRemainingMeters,
            'delay_minutes' => $this->delayMinutes,
        ];
    }
}
