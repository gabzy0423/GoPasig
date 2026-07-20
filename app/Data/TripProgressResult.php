<?php

namespace App\Data;

final class TripProgressResult
{
    public function __construct(
        public readonly int $tripId,
        public readonly ?int $currentStopId,
        public readonly ?int $nextStopId,
        public readonly ?int $lastCompletedStopId,
        public readonly int $completedStopsCount,
        public readonly int $remainingStopsCount,
        public readonly float $tripPercentage,
        public readonly string $routeAdherence,
        public readonly int $currentDelayMinutes,
        public readonly array $upcomingEtas // Array of ETAResult DTOs
    ) {}
}
