<?php

namespace App\Services\Routing;

use App\Services\Contracts\ProviderCircuitBreakerInterface;
use App\Data\HealthSnapshot;
use App\Events\ProviderOpened;
use App\Events\ProviderHalfOpened;
use App\Events\ProviderRecovered;
use Illuminate\Support\Facades\Cache;

class ProviderCircuitBreaker implements ProviderCircuitBreakerInterface
{
    /**
     * Evaluate the health snapshot and transition circuit states.
     */
    public function evaluate(HealthSnapshot $snapshot): string
    {
        $provider = $snapshot->provider;
        $currentState = Cache::get("circuit_breaker:{$provider}:state", 'Closed');

        $failureThreshold = (float) config('routing.circuit_breaker.failure_rate_threshold', 50.0);
        $minRequests = (int) config('routing.circuit_breaker.min_requests_to_trip', 5);

        if ($currentState === 'Closed') {
            if ($snapshot->failureRate >= $failureThreshold && $snapshot->totalRequests >= $minRequests) {
                $this->transitionTo($provider, 'Open');
                event(new ProviderOpened($provider));
                return 'Open';
            }
        } elseif ($currentState === 'Half-Open') {
            if ($snapshot->failureRate > 0) {
                // Any failure in Half-Open sends it back to Open
                $this->transitionTo($provider, 'Open');
                event(new ProviderOpened($provider));
                return 'Open';
            } else {
                // If it succeeds and has no failures, close the circuit (Recovered)
                $this->transitionTo($provider, 'Closed');
                event(new ProviderRecovered($provider));
                return 'Closed';
            }
        }

        return $currentState;
    }

    /**
     * Confirm if a provider can receive requests (checks state, cooldowns).
     */
    public function canRequest(string $provider): bool
    {
        $state = Cache::get("circuit_breaker:{$provider}:state", 'Closed');

        if ($state === 'Closed' || $state === 'Half-Open') {
            return true;
        }

        if ($state === 'Open') {
            $lastChange = (int) Cache::get("circuit_breaker:{$provider}:last_state_change", 0);
            $cooldown = (int) config('routing.circuit_breaker.cooldown_seconds', 300);

            if ((time() - $lastChange) >= $cooldown) {
                $this->transitionTo($provider, 'Half-Open');
                event(new ProviderHalfOpened($provider));
                return true;
            }
        }

        return false;
    }

    private function transitionTo(string $provider, string $state): void
    {
        Cache::put("circuit_breaker:{$provider}:state", $state);
        Cache::put("circuit_breaker:{$provider}:last_state_change", time());
    }
}
