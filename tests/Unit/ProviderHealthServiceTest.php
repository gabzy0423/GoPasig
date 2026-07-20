<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Routing\ProviderHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProviderHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_telemetry_updates_snapshot()
    {
        $service = new ProviderHealthService();

        // Initially clean
        $snapshot = $service->getSnapshot('google');
        $this->assertEquals(0.0, $snapshot->averageLatencyMs);
        $this->assertEquals(0.0, $snapshot->failureRate);
        $this->assertEquals(0, $snapshot->totalRequests);

        // Record some requests
        $service->recordRequest('google', 100.0, true);
        $service->recordRequest('google', 200.0, true);
        $service->recordRequest('google', 150.0, false); // failure

        $snapshot = $service->getSnapshot('google');
        $this->assertEquals(150.0, $snapshot->averageLatencyMs);
        $this->assertEquals(100.0 * (1 / 3), $snapshot->failureRate);
        $this->assertEquals(3, $snapshot->totalRequests);
        $this->assertEquals(2, $snapshot->successfulRequests);
    }
}
