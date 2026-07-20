<?php

namespace App\Services\Routing;

use Illuminate\Support\Facades\Cache;

class ProviderQuotaService
{
    /**
     * Increment the daily API count for a provider.
     */
    public function recordRequest(string $provider): void
    {
        $date = date('Y-m-d');
        $key = "quota:{$provider}:{$date}";

        if (Cache::has($key)) {
            Cache::increment($key);
        } else {
            Cache::put($key, 1, 172800); // 48 hours TTL
        }
    }

    /**
     * Get the count of requests made today for a provider.
     */
    public function getDailyCount(string $provider): int
    {
        $date = date('Y-m-d');
        $key = "quota:{$provider}:{$date}";
        return (int) Cache::get($key, 0);
    }

    /**
     * Get the remaining quota limit for the provider.
     */
    public function getRemainingQuota(string $provider): int
    {
        $limit = (int) config("routing.providers.{$provider}.daily_limit", -1);
        if ($limit === -1) {
            return 999999; // Represents unlimited
        }
        $count = $this->getDailyCount($provider);
        return max(0, $limit - $count);
    }

    /**
     * Calculate estimated daily costs based on pricing configuration.
     */
    public function getBillingEstimate(string $provider): float
    {
        $rate = (float) config("routing.providers.{$provider}.price_per_request", 0.0);
        return $this->getDailyCount($provider) * $rate;
    }
}
