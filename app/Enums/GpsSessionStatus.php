<?php

namespace App\Enums;

enum GpsSessionStatus: string
{
    case OFF = 'OFF';
    case ACTIVE = 'ACTIVE';
    case CLOSED = 'CLOSED';
}
