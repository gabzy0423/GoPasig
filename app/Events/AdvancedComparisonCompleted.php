<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdvancedComparisonCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $routeId,
        public string $sessionId,
        public float $frechetSimilarityPercent
    ) {}
}
