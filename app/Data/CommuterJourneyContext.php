<?php

namespace App\Data;

use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\Route;
use App\Models\RouteVariantStop;
use App\Models\Stop;

final class CommuterJourneyContext
{
    public function __construct(
        public readonly ?CommuterSession $session,
        public readonly ?CommuterTrip $activeTrip,
        public readonly ?CommuterLocation $location,
        public readonly StopGeofenceEvaluation $stopGeofence,
    ) {}

    public function originStop(): Stop|RouteVariantStop|null
    {
        return $this->activeTrip?->resolvedOriginStop();
    }

    public function destinationStop(): Stop|RouteVariantStop|null
    {
        return $this->activeTrip?->resolvedDestinationStop();
    }

    public function route(): ?Route
    {
        return $this->activeTrip?->route;
    }

    public function nearestStop(): ?Stop
    {
        return $this->stopGeofence->nearestStop;
    }

    public function activeStop(): ?Stop
    {
        return $this->stopGeofence->activeStop;
    }
}
