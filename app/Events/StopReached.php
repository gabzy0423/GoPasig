<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StopReached
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $tripId,
        public int $stopId,
        public string $source
    ) {}
}
