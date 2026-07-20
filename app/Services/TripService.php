<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;

use App\Enums\TripStatus;
use App\Enums\GpsSessionStatus;

class TripService
{
    public static function startTrip(Bus $bus, Driver $driver, Route $route, int $peakPassengers = 0): Trip
    {
        return Trip::create([
            'bus_id'          => $bus->id,
            'driver_id'       => $driver->id,
            'route_id'        => $route->id,
            'status'          => TripStatus::DISPATCHED->value,
            'gps_session'     => GpsSessionStatus::OFF->value,
            'peak_passengers' => $peakPassengers,
            'dispatched_at'   => now(),
            'started_at'      => null,
        ]);
    }

    public static function endTrip(Trip $trip, string $status = 'completed'): void
    {
        $trip->update([
            'status'      => $status,
            'gps_session' => GpsSessionStatus::CLOSED->value,
            'ended_at'    => now(),
        ]);
    }

    public static function cancelTrip(Trip $trip): void
    {
        self::endTrip($trip, 'cancelled');
    }

    public static function updatePeakPassengers(Trip $trip, int $newPax): void
    {
        $trip->update([
            'peak_passengers' => $newPax,
        ]);
    }
}
