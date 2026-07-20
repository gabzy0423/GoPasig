<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GPSRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $tripId,
        public array $data,
        public string $reason
    ) {}
}
