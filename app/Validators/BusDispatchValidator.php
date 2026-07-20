<?php

namespace App\Validators;

use App\Models\Bus;
use App\Exceptions\BusUnavailableException;

class BusDispatchValidator
{
    public static function validate(Bus $bus): void
    {
        if ($bus->status === 'ready' || $bus->status === 'operating' || $bus->status === Bus::STATUS_ACTIVE) {
            throw new BusUnavailableException("Bus {$bus->plate_number} is already active or dispatched.");
        }

        if ($bus->status === Bus::STATUS_MAINTENANCE) {
            throw new BusUnavailableException("Bus {$bus->plate_number} is currently in maintenance.");
        }

        if ($bus->status === Bus::STATUS_BREAKDOWN) {
            throw new BusUnavailableException("Bus {$bus->plate_number} has an unresolved breakdown.");
        }

        if ($bus->status !== 'available') {
            throw new BusUnavailableException("Bus {$bus->plate_number} is in an invalid state for dispatch: {$bus->status}.");
        }
    }
}
