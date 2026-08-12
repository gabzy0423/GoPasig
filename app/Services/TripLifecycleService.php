<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\Bus;
use App\Models\Driver;
use App\Enums\TripStatus;
use App\Enums\GpsSessionStatus;
use App\Services\BusStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class TripLifecycleService
{
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
        $status = is_object($trip->status) ? $trip->status->value : $trip->status;
        if ($status !== TripStatus::ONGOING->value) {
            throw new \InvalidArgumentException("Only ongoing trips can be completed.");
        }

        DB::transaction(function () use ($trip) {
            $bus = $trip->bus;
            $driver = $trip->driver;

            // 1. Bus: operating -> ready. Completing a point-to-point leg
            // closes only the current Trip; it must not release assignment.
            if ($bus) {
                BusStateService::transition($bus, 'ready', 'Driver completed point-to-point leg');
                $bus->update([
                    'next_stop' => null,
                    'passengers' => 0,
                    'speed' => 0,
                    'eta' => null,
                ]);
            }

            // 2. Driver: driving -> assigned. Keep bus/route assignment ready
            // for a future point-to-point leg without creating it here.
            if ($driver) {
                $driver->update([
                    'operational_status' => 'assigned',
                ]);
            }

            $endedAt = now();

            // 3. Trip: ongoing -> completed, GPS: ACTIVE -> CLOSED
            $trip->update([
                'status' => TripStatus::COMPLETED,
                'gps_session' => GpsSessionStatus::CLOSED,
                'ended_at' => $endedAt,
            ]);

            if ($trip->schedule_id) {
                $trip->schedule()->update([
                    'actual_arrival_time' => $endedAt->copy()->timezone('Asia/Manila')->format('H:i:s'),
                ]);
            }

            TripLogService::logTrip($trip->fresh(), [
                'completed_at' => $endedAt,
                'status' => TripStatus::COMPLETED->value,
            ]);
        });
    }

    /**
     * Cancel an ongoing trip (e.g. breakdown).
     */
    public function cancelTrip(Trip $trip): void
    {
        $status = is_object($trip->status) ? $trip->status->value : $trip->status;
        if ($status !== TripStatus::ONGOING->value) {
            throw new \InvalidArgumentException("Only ongoing trips can be cancelled.");
        }

        DB::transaction(function () use ($trip) {
            $bus = $trip->bus;
            $driver = $trip->driver;

            // 1. Bus: operating -> breakdown
            if ($bus) {
                BusStateService::transition($bus, 'breakdown', 'Incident reported: breakdown');
            }

            // 2. Driver: driving -> unavailable
            if ($driver) {
                $driver->update([
                    'operational_status' => 'unavailable',
                ]);
            }

            $endedAt = now();

            // 3. Trip: ongoing -> cancelled, GPS: ACTIVE -> CLOSED
            $trip->update([
                'status' => TripStatus::CANCELLED,
                'gps_session' => GpsSessionStatus::CLOSED,
                'ended_at' => $endedAt,
            ]);

            TripLogService::logTrip($trip->fresh(), [
                'completed_at' => $endedAt,
                'status' => TripStatus::CANCELLED->value,
            ]);
        });
    }
}
