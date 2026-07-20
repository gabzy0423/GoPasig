<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Routing\ProviderCircuitBreaker;
use App\Data\HealthSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProviderCircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    public function test_circuit_breaker_trips_to_open_when_failures_exceed_threshold()
    {
        config(['routing.circuit_breaker.failure_rate_threshold' => 50.0]);
        config(['routing.circuit_breaker.min_requests_to_trip' => 4]);

        $cb = new ProviderCircuitBreaker();

        // 1. Healthy Snapshot
        $healthy = new HealthSnapshot('google', 100.0, 25.0, 4, 3, 'Closed');
        $state = $cb->evaluate($healthy);
        $this->assertEquals('Closed', $state);
        $this->assertTrue($cb->canRequest('google'));

        // 2. Unhealthy Snapshot (75% failure rate, 4 requests)
        $unhealthy = new HealthSnapshot('google', 150.0, 75.0, 4, 1, 'Closed');
        $state = $cb->evaluate($unhealthy);
        $this->assertEquals('Open', $state);
        $this->assertFalse($cb->canRequest('google'));
    }

    public function test_cooldown_expires_transitions_to_half_open()
    {
        config(['routing.circuit_breaker.cooldown_seconds' => 10]);
        $cb = new ProviderCircuitBreaker();

        // Set to Open state
        Cache::put("circuit_breaker:google:state", 'Open');
        Cache::put("circuit_breaker:google:last_state_change", time() - 20); // 20s ago (exceeds 10s cooldown)

        // canRequest should transition state to Half-Open and return true
        $this->assertTrue($cb->canRequest('google'));
        $this->assertEquals('Half-Open', Cache::get("circuit_breaker:google:state"));
    }

    public function test_half_open_closes_on_success_reopens_on_failure()
    {
        $cb = new ProviderCircuitBreaker();

        // 1. Half-Open successes ➔ Closed
        Cache::put("circuit_breaker:google:state", 'Half-Open');
        $successSnapshot = new HealthSnapshot('google', 100.0, 0.0, 1, 1, 'Half-Open');
        $state = $cb->evaluate($successSnapshot);
        $this->assertEquals('Closed', $state);

        // 2. Half-Open failure ➔ Open
        Cache::put("circuit_breaker:google:state", 'Half-Open');
        $failureSnapshot = new HealthSnapshot('google', 100.0, 100.0, 1, 0, 'Half-Open');
        $state = $cb->evaluate($failureSnapshot);
        $this->assertEquals('Open', $state);
    }
}
