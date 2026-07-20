<?php

namespace App\Validators;

use App\Models\Driver;
use App\Models\Trip;
use App\Exceptions\DriverUnavailableException;
use Carbon\Carbon;

class DriverDispatchValidator
{
    public static function validate(Driver $driver): void
    {
        if ($driver->status === 'suspended') {
            throw new DriverUnavailableException("Driver {$driver->first_name} {$driver->last_name} is suspended.");
        }

        if ($driver->status === 'inactive') {
            throw new DriverUnavailableException("Driver {$driver->first_name} {$driver->last_name} is inactive.");
        }

        if (Carbon::parse($driver->license_expiry)->endOfDay()->lt(now())) {
            $expiry = Carbon::parse($driver->license_expiry)->format('Y-m-d');
            throw new DriverUnavailableException("Driver {$driver->first_name} {$driver->last_name}'s license expired on {$expiry}.");
        }

        // Check operational status
        if ($driver->operational_status !== 'available') {
            throw new DriverUnavailableException("Driver {$driver->first_name} {$driver->last_name} is not operational: {$driver->operational_status}.");
        }

        // Check if driver has an ongoing or dispatched trip
        $ongoingTripExists = Trip::where('driver_id', $driver->id)
            ->whereIn('status', ['ongoing', 'dispatched'])
            ->exists();

        if ($ongoingTripExists) {
            throw new DriverUnavailableException("Driver {$driver->first_name} {$driver->last_name} is already assigned to a dispatched or ongoing trip.");
        }
    }
}
