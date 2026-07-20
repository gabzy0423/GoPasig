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

            // 3. Trip: dispatched -> ongoing, GPS: OFF -> ACTIVE
            $trip->update([
                'status' => TripStatus::ONGOING,
                'gps_session' => GpsSessionStatus::ACTIVE,
                'started_at' => now(),
                'gps_session_started_at' => now(),
            ]);
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

            // 1. Bus: operating -> available
            if ($bus) {
                BusStateService::transition($bus, 'available', 'Driver completed trip');
                $bus->update([
                    'driver_name' => Bus::DEFAULT_DRIVER_NAME,
                    'route_id' => null,
                    'next_stop' => null,
                    'passengers' => 0,
                    'speed' => 0,
                    'eta' => null,
                ]);
            }

            // 2. Driver: driving -> available
            if ($driver) {
                $driver->update([
                    'operational_status' => 'available',
                    'assigned_bus' => null,
                    'assigned_route' => null,
                ]);
            }

            // 3. Trip: ongoing -> completed, GPS: ACTIVE -> CLOSED
            $trip->update([
                'status' => TripStatus::COMPLETED,
                'gps_session' => GpsSessionStatus::CLOSED,
                'ended_at' => now(),
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

            // 3. Trip: ongoing -> cancelled, GPS: ACTIVE -> CLOSED
            $trip->update([
                'status' => TripStatus::CANCELLED,
                'gps_session' => GpsSessionStatus::CLOSED,
                'ended_at' => now(),
            ]);
        });
    }
}
