<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RouteDeviationDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $tripId,
        public float $lat,
        public float $lng,
        public float $distanceMeters,
        public string $severity
    ) {}
}
