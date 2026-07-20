<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BusEnteredGeofence
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $busId,
        public ?int $tripId,
        public int $geofenceId,
        public string $type
    ) {}
}
