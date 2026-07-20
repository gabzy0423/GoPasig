<?php

namespace App\Enums;

enum TripStatus: string
{
    case PENDING = 'pending';
    case DISPATCHED = 'dispatched';
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
