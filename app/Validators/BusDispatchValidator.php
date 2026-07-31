<?php

namespace App\Validators;

use App\Exceptions\BusUnavailableException;
use App\Models\Bus;
use App\Services\CentralDispatchEligibilityService;

class BusDispatchValidator
{
    public static function validate(Bus $bus): void
    {
        $eligibility = CentralDispatchEligibilityService::bus($bus);
        if (! $eligibility['eligible']) {
            throw new BusUnavailableException("Bus {$bus->plate_number} is not available for Central Dispatch: {$eligibility['reason']}.");
        }
    }
}
