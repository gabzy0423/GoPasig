<?php

namespace App\Enums;

enum GeofenceType: string
{
    case STOP = 'STOP';
    case TERMINAL = 'TERMINAL';
    case DEPOT = 'DEPOT';
    case GARAGE = 'GARAGE';
}
