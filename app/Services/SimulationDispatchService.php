<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Trip;
use App\Validators\BusDispatchValidator;
use App\Validators\DriverDispatchValidator;
use App\Exceptions\DuplicateDispatchException;
use App\Exceptions\ScheduleConflictException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SimulationDispatchService
{
    /**
     * Orchestrates the entire dispatch workflow atomically.
     */
    public static function dispatch(
        Bus $bus,
        Driver $driver,
        Route $route,
        ?int $dispatcherId = null,
        string $notes = ''
    ): Trip {
        return DB::transaction(function () use ($bus, $driver, $route, $dispatcherId, $notes) {
            // 1. Lock in the official global hierarchy sequence: Bus -> Driver
            $bus = Bus::where('id', $bus->id)->lockForUpdate()->first();
            $driver = Driver::where('id', $driver->id)->lockForUpdate()->first();

            if (!$bus || !$driver) {
                throw new \Exception("Resource not found for dispatch locking.");
            }

            // 2. Validate availability using dedicated validators
            BusDispatchValidator::validate($bus);
            DriverDispatchValidator::validate($driver);

            // 3. Idempotency checks: verify no existing ongoing or dispatched trips
            $busTripExists = Trip::where('bus_id', $bus->id)->whereIn('status', ['ongoing', 'dispatched'])->exists();
            if ($busTripExists) {
                throw new DuplicateDispatchException("Bus {$bus->plate_number} already has an ongoing or dispatched trip.");
            }

            $driverTripExists = Trip::where('driver_id', $driver->id)->whereIn('status', ['ongoing', 'dispatched'])->exists();
            if ($driverTripExists) {
                throw new DuplicateDispatchException("Driver {$driver->first_name} {$driver->last_name} already has an ongoing or dispatched trip.");
            }

            // 4. Validate schedule conflicts (overlaps, rest periods, daily hours)
            $timeSlot = now()->toTimeString();
            $duration = 120; // standard trip travel time window

            // A. Check bus overlapping schedule conflict via reflection helper
            $busConflict = self::checkTimeSlotConflict($bus->id, $timeSlot, $duration, 'bus');
            if ($busConflict['conflict']) {
                throw new ScheduleConflictException("Bus {$bus->plate_number} already scheduled: {$busConflict['conflict_details']}");
            }

            // B. Check driver overlapping schedule conflict via reflection helper
            $driverConflict = self::checkTimeSlotConflict($driver->id, $timeSlot, $duration, 'driver');
            if ($driverConflict['conflict']) {
                throw new ScheduleConflictException("Driver already scheduled: {$driverConflict['conflict_details']}");
            }

            // C. Check driver rest periods
            $restCheck = \App\Services\ScheduleConflictService::checkDriverRestPeriod($driver->id, $timeSlot);
            if (!$restCheck['compliant']) {
                throw new ScheduleConflictException($restCheck['message']);
            }

            // D. Check driver daily hours limit
            $dailyHoursCheck = \App\Services\ScheduleConflictService::checkDailyHoursLimit($driver->id, $timeSlot, $duration);
            if (!$dailyHoursCheck['allowed']) {
                throw new ScheduleConflictException($dailyHoursCheck['message']);
            }

            // 5. Start Trip via TripService (lifecycle service) - starts in 'dispatched'
            $trip = TripService::startTrip($bus, $driver, $route, $bus->passengers ?: 0);

            // 6. Transition Bus state to 'ready' (dispatched state) via BusStateService
            BusStateService::transition($bus, 'ready', $notes ?: 'Simulation Dispatch', $driver, $route);

            // 6b. Transition Driver operational status to 'assigned'
            $driver->update(['operational_status' => 'assigned']);

            // 7. Write Dispatch Log via DispatchLogService (LAST write)
            DispatchLogService::createDispatchLog($trip->id, $dispatcherId, $notes ?: 'Automatic dispatch.');

            return $trip;
        });
    }

    /**
     * Reflection helper to call protected checkTimeSlotConflict on ScheduleConflictService.
     */
    private static function checkTimeSlotConflict(
        int $entityId,
        string $departureTime,
        int $durationMinutes,
        string $entityType
    ): array {
        $method = new \ReflectionMethod(\App\Services\ScheduleConflictService::class, 'checkTimeSlotConflict');
        $method->setAccessible(true);
        return $method->invoke(null, $entityId, $departureTime, $durationMinutes, null, $entityType);
    }
}
