<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\RouteVariantStop;
use App\Enums\TripStatus;
use App\Enums\GpsSessionStatus;
use App\Services\BusStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class TripLifecycleService
{
    public function __construct(
        protected PassengerLoadService $passengerLoads
    ) {}

    /**
     * Start a dispatched trip.
     */
    public function startTrip(Trip $trip): void
    {
        $status = is_object($trip->status) ? $trip->status->value : $trip->status;
        if ($status !== TripStatus::DISPATCHED->value) {
            throw new \InvalidArgumentException("Only dispatched trips can be started.");
        }

        DB::transaction(function () use ($trip) {
            $bus = $trip->bus;
            $driver = $trip->driver;

            // 1. Bus: ready -> operating
            if ($bus) {
                Cache::forget('bus_kalman_state_' . $bus->id);
                BusStateService::transition($bus, 'operating', 'Driver started live trip session');
            }

            // 2. Driver: assigned -> driving
            if ($driver) {
                $driver->update([
                    'operational_status' => 'driving'
                ]);
                $driver->increment('trips_today');
            }

            $startedAt = now();

            // 3. Trip: dispatched -> ongoing, GPS: OFF -> ACTIVE
            $trip->update([
                'status' => TripStatus::ONGOING,
                'gps_session' => GpsSessionStatus::ACTIVE,
                'started_at' => $startedAt,
                'gps_session_started_at' => $startedAt,
            ]);

            if ($trip->schedule_id) {
                $trip->schedule()->update([
                    'actual_departure_time' => $startedAt->copy()->timezone('Asia/Manila')->format('H:i:s'),
                ]);
            }
        });
    }

    /**
     * Complete an ongoing trip.
     */
    public function completeTrip(Trip $trip): void
    {
        $this->finalizeCompletedTrip($trip, null, false);
    }

    public function completeTripAtFinalStop(Trip $trip, ?RouteVariantStop $stop): void
    {
        $this->finalizeCompletedTrip($trip, $stop, true);
    }

    /**
     * Cancel an ongoing trip (e.g. breakdown).
     */
    public function cancelTrip(Trip $trip): void
    {
        DB::transaction(function () use ($trip) {
            $bus = Bus::whereKey($trip->bus_id)->lockForUpdate()->first();
            $driver = Driver::whereKey($trip->driver_id)->lockForUpdate()->first();
            $lockedTrip = Trip::whereKey($trip->id)->lockForUpdate()->firstOrFail();

            if ($lockedTrip->status !== TripStatus::ONGOING->value) {
                throw new \InvalidArgumentException("Only ongoing trips can be cancelled.");
            }

            // 1. Bus: operating -> breakdown
            if ($bus) {
                BusStateService::transition(
                    $bus,
                    'breakdown',
                    'Incident reported: breakdown',
                    finalizeActiveTrips: false
                );
            }

            // 2. Driver: driving -> unavailable
            if ($driver) {
                $driver->update([
                    'operational_status' => 'unavailable',
                ]);
            }

            $endedAt = now();

            // 3. Trip: ongoing -> cancelled, GPS: ACTIVE -> CLOSED
            $lockedTrip->update([
                'status' => TripStatus::CANCELLED,
                'gps_session' => GpsSessionStatus::CLOSED,
                'ended_at' => $endedAt,
            ]);

            TripLogService::logTripOrFail($lockedTrip->fresh(), [
                'completed_at' => $endedAt,
                'status' => TripStatus::CANCELLED->value,
            ]);
        }, 3);
    }

    private function finalizeCompletedTrip(
        Trip $trip,
        ?RouteVariantStop $finalStop,
        bool $allowFinalStopAlighting
    ): void {
        DB::transaction(function () use ($trip, $finalStop, $allowFinalStopAlighting) {
            $bus = Bus::whereKey($trip->bus_id)->lockForUpdate()->first();
            $driver = Driver::whereKey($trip->driver_id)->lockForUpdate()->first();
            $lockedTrip = Trip::whereKey($trip->id)->lockForUpdate()->firstOrFail();

            if ($lockedTrip->status !== TripStatus::ONGOING->value) {
                if ($allowFinalStopAlighting && $lockedTrip->status === TripStatus::COMPLETED->value) {
                    return;
                }

                throw new \InvalidArgumentException("Only ongoing trips can be completed.");
            }

            if ($bus && (int) $bus->passengers > 0) {
                if (! $allowFinalStopAlighting) {
                    throw new \DomainException(
                        'Cannot end the trip while passengers remain onboard. Reach the final stop or record alighting first.'
                    );
                }

                $this->passengerLoads->alightRemainingAtFinalStop($lockedTrip, $bus, $finalStop);
            }

            if ($bus) {
                BusStateService::transition($bus, 'ready', 'Driver completed point-to-point leg');
                $bus->update([
                    'next_stop' => null,
                    'passengers' => 0,
                    'speed' => 0,
                    'eta' => null,
                ]);
            }

            if ($driver) {
                $driver->update(['operational_status' => 'assigned']);
            }

            $endedAt = now();
            $lockedTrip->update([
                'status' => TripStatus::COMPLETED,
                'gps_session' => GpsSessionStatus::CLOSED,
                'ended_at' => $endedAt,
            ]);

            if ($lockedTrip->schedule_id) {
                $lockedTrip->schedule()->update([
                    'actual_arrival_time' => $endedAt->copy()->timezone('Asia/Manila')->format('H:i:s'),
                ]);
            }

            TripLogService::logTripOrFail($lockedTrip->fresh(), [
                'completed_at' => $endedAt,
                'status' => TripStatus::COMPLETED->value,
            ]);
        }, 3);
    }
}
