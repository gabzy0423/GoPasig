<?php

namespace App\Enums;

enum SpatialPresenceState: string
{
    case OUTSIDE = 'OUTSIDE';
    case ENTERING = 'ENTERING';
    case INSIDE = 'INSIDE';
    case EXIT_PENDING = 'EXIT_PENDING';
}
