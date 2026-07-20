<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Bus;
use App\Models\Geofence;
use App\Models\VehiclePosition;
use Carbon\Carbon;

class BusExitedDepot
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Bus $bus,
        public Geofence $geofence,
        public float $distance,
        public Carbon $timestamp,
        public VehiclePosition $position
    ) {}
}
