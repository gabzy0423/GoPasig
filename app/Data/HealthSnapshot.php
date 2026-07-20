<?php

namespace App\Data;

final class HealthSnapshot
{
    public function __construct(
        public readonly string $provider,
        public readonly float $averageLatencyMs,
        public readonly float $failureRate,
        public readonly int $totalRequests,
        public readonly int $successfulRequests,
        public readonly string $state,
    ) {}
}
