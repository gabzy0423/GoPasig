<?php

namespace App\Enums;

enum RouteDeviationSeverity: string
{
    case ON_ROUTE = 'ON_ROUTE';
    case MINOR = 'MINOR';
    case MAJOR = 'MAJOR';
    case CRITICAL = 'CRITICAL';
}
