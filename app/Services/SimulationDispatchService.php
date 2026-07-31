<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\Schedule;
use App\Models\Trip;
use App\Validators\BusDispatchValidator;
use App\Validators\DriverDispatchValidator;
use App\Validators\RouteDispatchValidator;
use App\Exceptions\DuplicateDispatchException;
use App\Exceptions\ScheduleConflictException;
use Carbon\Carbon;
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
        string $notes = '',
        ?RouteVariant $routeVariant = null,
        ?Schedule $schedule = null
    ): Trip {
        return DB::transaction(function () use ($bus, $driver, $route, $dispatcherId, $notes, $routeVariant, $schedule) {
            // 1. Lock in the official global hierarchy sequence: Bus -> Driver
            $bus = Bus::where('id', $bus->id)->lockForUpdate()->first();
            $driver = Driver::where('id', $driver->id)->lockForUpdate()->first();

            if (!$bus || !$driver) {
                throw new \Exception("Resource not found for dispatch locking.");
            }

            // 2. Validate availability using dedicated validators
            BusDispatchValidator::validate($bus);
            DriverDispatchValidator::validate($driver);
            RouteDispatchValidator::validate($route);

            // 3. Resolve the service day before checking same-day runtime state.
            $serviceDay = self::resolveServiceDay($schedule);

            // 4. Idempotency checks: verify no existing ongoing or dispatched trips
            $busTripExists = Trip::where('bus_id', $bus->id)->whereIn('status', ['ongoing', 'dispatched'])->exists();
            if ($busTripExists) {
                throw new DuplicateDispatchException("Bus {$bus->plate_number} already has an ongoing or dispatched trip.");
            }

            $driverTripExists = Trip::where('driver_id', $driver->id)->whereIn('status', ['ongoing', 'dispatched'])->exists();
            if ($driverTripExists) {
                throw new DuplicateDispatchException("Driver {$driver->first_name} {$driver->last_name} already has an ongoing or dispatched trip.");
            }

            $selectedVariant = app(RouteVariantSelectionService::class)->resolveForDispatch($route, $routeVariant?->id, $schedule);

            self::resetStaleRuntimeOccupancyBeforeFirstDispatch($bus, $serviceDay);

            // 5. Start Trip via TripService (lifecycle service) - starts in 'dispatched'
            $trip = TripService::startTrip($bus, $driver, $route, $bus->passengers ?: 0, $selectedVariant, $schedule);

            // 6. Transition Bus state to 'ready' (dispatched state) via BusStateService
            BusStateService::transition($bus, 'ready', $notes ?: 'Simulation Dispatch', $driver, $route);

            // 6b. Transition Driver operational status to 'assigned'
            $driver->update(['operational_status' => 'assigned']);

            // 7. Write Dispatch Log via DispatchLogService (LAST write)
            DispatchLogService::createDispatchLog($trip->id, $dispatcherId, $notes ?: 'Automatic dispatch.');

            return $trip;
        });
    }


    public static function dispatchFromSchedule(
        Schedule $schedule,
        ?int $dispatcherId = null,
        string $notes = ''
    ): Trip {
        return DB::transaction(function () use ($schedule, $dispatcherId, $notes) {
            $schedule = Schedule::where('id', $schedule->id)
                ->lockForUpdate()
                ->firstOrFail();

            $schedule->loadMissing(['bus', 'driver', 'route', 'routeVariant']);

            if (Trip::where('schedule_id', $schedule->id)->lockForUpdate()->exists()) {
                throw new \RuntimeException('Schedule has already been dispatched and linked to a trip.');
            }

            if (! $schedule->bus || ! $schedule->driver || ! $schedule->route) {
                throw new \InvalidArgumentException('Schedule must have a bus, driver, and route before dispatch.');
            }

            return self::dispatch(
                $schedule->bus,
                $schedule->driver,
                $schedule->route,
                $dispatcherId,
                $notes ?: 'Scheduled dispatch.',
                $schedule->routeVariant,
                $schedule
            );
        });
    }

    private static function resolveServiceDay(?Schedule $schedule): string
    {
        if ($schedule?->service_date) {
            return Carbon::parse($schedule->service_date)->toDateString();
        }

        return now('Asia/Manila')->toDateString();
    }

    private static function resetStaleRuntimeOccupancyBeforeFirstDispatch(Bus $bus, string $serviceDay): void
    {
        if ((int) $bus->passengers <= 0) {
            return;
        }

        if ($bus->status === 'operating') {
            return;
        }

        if (Trip::where('bus_id', $bus->id)->whereIn('status', ['ongoing', 'dispatched'])->exists()) {
            return;
        }

        if (self::hasDispatchForServiceDay($bus, $serviceDay)) {
            return;
        }

        $bus->update(['passengers' => 0]);
        $bus->refresh();
    }

    private static function hasDispatchForServiceDay(Bus $bus, string $serviceDay): bool
    {
        return Trip::where('bus_id', $bus->id)
            ->where(function ($query) use ($serviceDay) {
                $query->whereHas('schedule', function ($scheduleQuery) use ($serviceDay) {
                    $scheduleQuery->whereDate('service_date', $serviceDay);
                })->orWhere(function ($tripQuery) use ($serviceDay) {
                    $tripQuery->whereNull('schedule_id')
                        ->whereDate('dispatched_at', $serviceDay);
                });
            })
            ->exists();
    }
}
