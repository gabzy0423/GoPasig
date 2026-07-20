<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ETAUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $tripId,
        public array $etas
    ) {}
}
