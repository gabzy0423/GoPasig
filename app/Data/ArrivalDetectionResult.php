<?php

namespace App\Data;

use App\Models\CommuterTrip;
use App\Models\RouteVariantStop;
use App\Models\Stop;

final class ArrivalDetectionResult
{
    public function __construct(
        public readonly ?CommuterTrip $journey = null,
        public readonly Stop|RouteVariantStop|null $destinationStop = null,
        public readonly bool $insideDestinationGeofence = false,
        public readonly bool $pending = false,
        public readonly bool $arrived = false,
        public readonly int $confirmationCount = 0,
        public readonly ?int $pendingJourneyId = null,
        public readonly ?int $pendingDestinationStopId = null,
        public readonly ?int $pendingBusId = null,
        public readonly ?string $reason = null,
    ) {}

    public static function none(?string $reason = null, ?CommuterTrip $journey = null): self
    {
        return new self(journey: $journey, reason: $reason);
    }

    public static function firstConfirmation(
        CommuterTrip $journey,
        Stop|RouteVariantStop $destinationStop,
        int $busId
    ): self {
        return new self(
            journey: $journey,
            destinationStop: $destinationStop,
            insideDestinationGeofence: true,
            pending: true,
            confirmationCount: 1,
            pendingJourneyId: $journey->id,
            pendingDestinationStopId: $destinationStop->id,
            pendingBusId: $busId,
            reason: 'first_confirmation'
        );
    }

    public static function arrived(
        CommuterTrip $journey,
        Stop|RouteVariantStop $destinationStop,
        int $busId
    ): self {
        return new self(
            journey: $journey,
            destinationStop: $destinationStop,
            insideDestinationGeofence: true,
            arrived: true,
            confirmationCount: 0,
            pendingJourneyId: $journey->id,
            pendingDestinationStopId: $destinationStop->id,
            pendingBusId: $busId,
            reason: 'arrival_confirmed'
        );
    }
}
