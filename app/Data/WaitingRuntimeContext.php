<?php

namespace App\Data;

use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\Route;
use App\Models\Stop;
use Carbon\CarbonImmutable;

final class WaitingRuntimeContext
{
    public function __construct(
        public readonly ?CommuterTrip $journey,
        public readonly ?CommuterSession $session,
        public readonly ?Stop $originStop,
        public readonly ?Stop $destinationStop,
        public readonly ?Route $route,
        public readonly ?Stop $nearestStop,
        public readonly ?int $waitingDurationSeconds,
        public readonly ?CommuterLocation $latestCommuterGps,
        public readonly CarbonImmutable $latestRecoveryTimestamp,
    ) {}

    public static function fromJourneyContext(CommuterJourneyContext $context, CarbonImmutable $recoveredAt): self
    {
        $journey = $context->activeTrip;

        return new self(
            journey: $journey,
            session: $context->session,
            originStop: $journey?->originStop,
            destinationStop: $journey?->destinationStop,
            route: $journey?->route,
            nearestStop: $context->nearestStop(),
            waitingDurationSeconds: $journey?->created_at ? max(0, $journey->created_at->diffInSeconds($recoveredAt)) : null,
            latestCommuterGps: $context->location,
            latestRecoveryTimestamp: $recoveredAt,
        );
    }
}
