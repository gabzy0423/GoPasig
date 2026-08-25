<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\RouteVariantStop;
use App\Models\Trip;
use App\Models\TripPassengerEvent;
use App\Services\Routing\CurrentTripStopResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PassengerLoadService
{
    public function __construct(
        protected CurrentTripStopResolver $currentStopResolver,
        protected TripPassengerEventService $passengerEvents
    ) {}

    /**
     * Apply one accepted driver load change as one atomic operation.
     *
     * Runtime lock order is Bus -> Driver -> Trip. Bus state transitions use
     * the same order, so trip completion cannot interleave with this write.
     *
     * @return array{passengers: int, pax_today: int, route_variant_stop_id: ?int, duplicate: bool}
     */
    public function applyDriverChange(Driver|int $driver, int $change, ?string $requestId = null): array
    {
        if ($change === 0) {
            throw new \InvalidArgumentException('Passenger change must not be zero.');
        }

        $driverId = $driver instanceof Driver ? (int) $driver->id : $driver;
        $driverSnapshot = Driver::find($driverId);
        $plateNumber = trim((string) $driverSnapshot?->assigned_bus);

        if (! $driverSnapshot || $plateNumber === '') {
            throw new \DomainException('No active bus assigned.');
        }

        $busId = Bus::where('plate_number', $plateNumber)->value('id');
        if (! $busId) {
            throw new \DomainException('No active bus assigned.');
        }

        $requestId = $requestId !== null ? trim($requestId) : null;

        return DB::transaction(function () use ($driverId, $busId, $plateNumber, $change, $requestId) {
            $bus = Bus::whereKey($busId)->lockForUpdate()->first();
            $lockedDriver = Driver::whereKey($driverId)->lockForUpdate()->first();

            if (! $bus
                || ! $lockedDriver
                || trim((string) $lockedDriver->assigned_bus) !== $plateNumber
                || $bus->plate_number !== $plateNumber) {
                throw new \DomainException('The driver and bus assignment changed. Refresh the trip page.');
            }

            if ($requestId !== null && $requestId !== '') {
                $existingEvent = TripPassengerEvent::where('request_id', $requestId)->first();
                if ($existingEvent) {
                    if ((int) $existingEvent->driver_id !== $driverId
                        || (int) $existingEvent->bus_id !== (int) $bus->id) {
                        throw new \DomainException('Passenger request identifier is already in use.');
                    }

                    $paxToday = $this->syncDriverBoardedToday($lockedDriver);

                    return [
                        'passengers' => (int) $bus->passengers,
                        'pax_today' => $paxToday,
                        'route_variant_stop_id' => $existingEvent->route_variant_stop_id,
                        'duplicate' => true,
                    ];
                }
            }

            $trip = Trip::query()
                ->where('driver_id', $lockedDriver->id)
                ->where('bus_id', $bus->id)
                ->where('status', 'ongoing')
                ->where('gps_session', 'ACTIVE')
                ->whereNotNull('started_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $trip || $bus->status !== 'operating') {
                throw new \DomainException(
                    'Passenger management is unavailable because the assigned trip is not currently operating.'
                );
            }

            $currentPax = max(0, (int) $bus->passengers);
            $capacity = max(0, (int) $bus->capacity);
            $newPax = max(0, min($capacity, $currentPax + $change));
            $acceptedDelta = abs($newPax - $currentPax);
            $passengerEvent = null;

            if ($acceptedDelta > 0) {
                $currentStop = $this->currentStopResolver->resolve($trip);

                $passengerEvent = $this->passengerEvents->record(
                    $trip,
                    $change > 0
                        ? TripPassengerEvent::TYPE_BOARDED
                        : TripPassengerEvent::TYPE_ALIGHTED,
                    $acceptedDelta,
                    $newPax,
                    $currentStop,
                    $requestId
                );

                $bus->update(['passengers' => $newPax]);

                if ($newPax > (int) ($trip->peak_passengers ?? 0)) {
                    $trip->update(['peak_passengers' => $newPax]);
                }
            }

            $paxToday = $this->syncDriverBoardedToday($lockedDriver);

            return [
                'passengers' => $newPax,
                'pax_today' => $paxToday,
                'route_variant_stop_id' => $passengerEvent?->route_variant_stop_id,
                'duplicate' => false,
            ];
        }, 3);
    }

    /**
     * Caller must already hold the Bus -> Driver -> Trip locks.
     */
    public function alightRemainingAtFinalStop(
        Trip $trip,
        Bus $bus,
        ?RouteVariantStop $stop
    ): ?TripPassengerEvent {
        $remainingPassengers = max(0, (int) $bus->passengers);
        if ($remainingPassengers === 0) {
            return null;
        }

        $event = $this->passengerEvents->record(
            $trip,
            TripPassengerEvent::TYPE_ALIGHTED,
            $remainingPassengers,
            0,
            $stop
        );

        $bus->update(['passengers' => 0]);

        return $event;
    }

    public function boardedTodayForDriver(Driver|int $driver, ?CarbonInterface $at = null): int
    {
        $driverId = $driver instanceof Driver ? (int) $driver->id : $driver;
        $manilaNow = $at
            ? $at->copy()->timezone('Asia/Manila')
            : now('Asia/Manila');
        $startUtc = $manilaNow->copy()->startOfDay()->utc();
        $endUtc = $manilaNow->copy()->endOfDay()->utc();

        return (int) TripPassengerEvent::query()
            ->where('driver_id', $driverId)
            ->where('event_type', TripPassengerEvent::TYPE_BOARDED)
            ->whereBetween('recorded_at', [$startUtc, $endUtc])
            ->sum('passenger_delta');
    }

    private function syncDriverBoardedToday(Driver $driver): int
    {
        $paxToday = $this->boardedTodayForDriver($driver);
        if ((int) $driver->pax_today !== $paxToday) {
            $driver->update(['pax_today' => $paxToday]);
        }

        return $paxToday;
    }
}
