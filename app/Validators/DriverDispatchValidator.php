<?php

namespace App\Validators;

use App\Exceptions\DriverUnavailableException;
use App\Models\Driver;
use App\Services\CentralDispatchEligibilityService;

class DriverDispatchValidator
{
    public static function validate(Driver $driver): void
    {
        $eligibility = CentralDispatchEligibilityService::driver($driver);
        if (! $eligibility['eligible']) {
            throw new DriverUnavailableException("Driver {$driver->first_name} {$driver->last_name} is not available for Central Dispatch: {$eligibility['reason']}.");
        }
    }
}
