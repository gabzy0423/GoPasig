<?php

namespace App\Events;

use App\Models\VehiclePosition;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PositionUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public VehiclePosition $position) {}
}
