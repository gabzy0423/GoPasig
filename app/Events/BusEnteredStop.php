<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Bus;
use App\Models\Trip;
use App\Models\Stop;
use App\Models\VehiclePosition;
use Carbon\Carbon;

class BusEnteredStop
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Bus $bus,
        public ?Trip $trip,
        public Stop $stop,
        public float $distance,
        public Carbon $timestamp,
        public VehiclePosition $position
    ) {}
}
