<?php

namespace App\Data;

use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\Route;
use App\Models\Stop;

final class CommuterJourneyContext
{
    public function __construct(
        public readonly ?CommuterSession $session,
        public readonly ?CommuterTrip $activeTrip,
        public readonly ?CommuterLocation $location,
        public readonly StopGeofenceEvaluation $stopGeofence,
    ) {}

    public function originStop(): ?Stop
    {
        return $this->activeTrip?->originStop;
    }

    public function destinationStop(): ?Stop
    {
        return $this->activeTrip?->destinationStop;
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
