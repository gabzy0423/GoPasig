<?php

namespace App\Validators;

use App\Exceptions\RouteSuspendedException;
use App\Models\Route;
use App\Services\CentralDispatchEligibilityService;

class RouteDispatchValidator
{
    public static function validate(Route $route): void
    {
        $eligibility = CentralDispatchEligibilityService::route($route);
        if (! $eligibility['eligible']) {
            throw new RouteSuspendedException($eligibility['reason']);
        }
    }
}
