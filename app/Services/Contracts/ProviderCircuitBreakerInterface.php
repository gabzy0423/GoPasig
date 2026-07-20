<?php

namespace App\Services\Contracts;

use App\Data\HealthSnapshot;

interface ProviderCircuitBreakerInterface
{
    /**
     * Evaluate the health snapshot and update circuit breaker state.
     */
    public function evaluate(HealthSnapshot $snapshot): string;

    /**
     * Confirm if a provider can receive requests.
     */
    public function canRequest(string $provider): bool;
}
