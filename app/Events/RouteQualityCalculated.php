<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Data\RouteQualityResult;

class RouteQualityCalculated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $routeId,
        public RouteQualityResult $quality
    ) {}
}
