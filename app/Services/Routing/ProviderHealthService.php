<?php

namespace App\Services\Routing;

use App\Data\HealthSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProviderHealthService
{
    /**
     * Log a routing request attempt details in database.
     */
    public function recordRequest(string $provider, float $latencyMs, bool $success): void
    {
        DB::table('provider_health_logs')->insert([
            'provider' => $provider,
            'latency_ms' => $latencyMs,
            'success' => $success,
            'created_at' => now(),
        ]);
    }

    /**
     * Compute and compile the current health snapshot for a provider.
     */
    public function getSnapshot(string $provider): HealthSnapshot
    {
        $windowSize = (int) config('routing.circuit_breaker.sliding_window_size', 10);

        $logs = DB::table('provider_health_logs')
            ->where('provider', $provider)
            ->orderByDesc('id') // Order by autoincrement ID to ensure exact sequence order
            ->limit($windowSize)
            ->get();

        $state = Cache::get("circuit_breaker:{$provider}:state", 'Closed');

        if ($logs->isEmpty()) {
            return new HealthSnapshot($provider, 0.0, 0.0, 0, 0, $state);
        }

        $total = $logs->count();
        $successful = 0;
        $totalLatency = 0.0;

        foreach ($logs as $log) {
            if ($log->success) {
                $successful++;
            }
            $totalLatency += (float) $log->latency_ms;
        }

        $avgLatency = $totalLatency / $total;
        $failures = $total - $successful;
        $failureRate = ($failures / $total) * 100.0;

        return new HealthSnapshot(
            $provider,
            $avgLatency,
            $failureRate,
            $total,
            $successful,
            $state
        );
    }
}
