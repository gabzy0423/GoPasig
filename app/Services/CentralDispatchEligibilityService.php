<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\ServiceAlert;
use App\Models\Trip;
use Carbon\Carbon;

class CentralDispatchEligibilityService
{
    /**
     * Determine whether a route is eligible for a new Central Dispatch assignment.
     *
     * @return array{eligible: bool, reason: string}
     */
    public static function route(Route $route): array
    {
        if ($route->status === 'Suspended') {
            $alert = ServiceAlert::activeAlerts()
                ->where('suspend_route', true)
                ->where(function ($q) use ($route) {
                    $q->where('route_id', $route->id)
                        ->orWhere('affected_routes', $route->name)
                        ->orWhere('affected_routes', 'like', '%' . $route->name . '%');
                })
                ->orderByRaw("CASE WHEN severity = 'critical' THEN 0 WHEN severity = 'high' THEN 1 WHEN severity = 'warning' THEN 2 ELSE 3 END")
                ->orderByDesc('created_at')
                ->first();

            $alertTitle = $alert ? " ({$alert->title})" : "";
            $reason = "Dispatch Denied: Route {$route->name} is currently suspended by an active Service Alert{$alertTitle}.";

            return [
                'eligible' => false,
                'reason' => $reason,
            ];
        }

        return ['eligible' => true, 'reason' => 'Active'];
    }

    public static function routeIsEligible(Route $route): bool
    {
        return self::route($route)['eligible'];
    }

    /**
     * Determine whether a bus is free for a new Central Dispatch assignment.
     *
     * This is stricter than route/schedule planning availability: a bus can only
     * enter Central Dispatch when it has no retained operational assignment.
     *
     * @return array{eligible: bool, reason: string}
     */
    public static function bus(Bus $bus): array
    {
        if ($bus->status !== Bus::STATUS_INACTIVE) {
            return ['eligible' => false, 'reason' => self::busStatusReason($bus)];
        }

        if (Trip::where('bus_id', $bus->id)->whereIn('status', ['dispatched', 'ongoing'])->exists()) {
            return ['eligible' => false, 'reason' => 'Already assigned to a dispatched or ongoing trip'];
        }

        if ($bus->route_id !== null) {
            return ['eligible' => false, 'reason' => 'Retained route assignment'];
        }

        if (self::hasRetainedDriverContext($bus)) {
            return ['eligible' => false, 'reason' => 'Retained driver assignment'];
        }

        return ['eligible' => true, 'reason' => 'Standby / Dispatchable'];
    }

    public static function busIsEligible(Bus $bus): bool
    {
        return self::bus($bus)['eligible'];
    }

    /**
     * Determine whether a driver is free for a new Central Dispatch assignment.
     *
     * @return array{eligible: bool, reason: string}
     */
    public static function driver(Driver $driver): array
    {
        if ($driver->status !== 'active') {
            return ['eligible' => false, 'reason' => self::driverStatusReason($driver)];
        }

        if ($driver->operational_status !== 'available') {
            return ['eligible' => false, 'reason' => 'Not operationally available'];
        }

        if (trim((string) $driver->assigned_bus) !== '') {
            return ['eligible' => false, 'reason' => 'Retained bus assignment'];
        }

        if (trim((string) $driver->assigned_route) !== '') {
            return ['eligible' => false, 'reason' => 'Retained route assignment'];
        }

        if ($driver->license_expiry && Carbon::parse($driver->license_expiry)->endOfDay()->lt(now())) {
            return ['eligible' => false, 'reason' => 'License expired'];
        }

        if (Trip::where('driver_id', $driver->id)->whereIn('status', ['dispatched', 'ongoing'])->exists()) {
            return ['eligible' => false, 'reason' => 'Already assigned to a dispatched or ongoing trip'];
        }

        return ['eligible' => true, 'reason' => 'Available for Central Dispatch'];
    }

    public static function driverIsEligible(Driver $driver): bool
    {
        return self::driver($driver)['eligible'];
    }

    private static function hasRetainedDriverContext(Bus $bus): bool
    {
        $driverName = trim((string) $bus->driver_name);
        $defaultNames = array_unique(array_filter([
            Bus::DEFAULT_DRIVER_NAME,
            Bus::getDefaultDriverName(),
        ]));

        return $driverName !== '' && ! in_array($driverName, $defaultNames, true);
    }

    private static function busStatusReason(Bus $bus): string
    {
        return match ($bus->status) {
            'ready' => 'Assigned or ready between trips',
            'operating' => 'Operating on an ongoing trip',
            Bus::STATUS_MAINTENANCE => 'Maintenance',
            Bus::STATUS_BREAKDOWN => 'Breakdown',
            Bus::STATUS_INACTIVE => 'Standby / Dispatchable',
            'available' => 'Deprecated legacy available state',
            Bus::STATUS_ACTIVE => 'Legacy active assignment',
            default => 'Invalid dispatch state: ' . $bus->status,
        };
    }

    private static function driverStatusReason(Driver $driver): string
    {
        return match ($driver->status) {
            'inactive' => 'Inactive / off duty',
            'suspended' => 'Suspended',
            default => 'Invalid driver status: ' . $driver->status,
        };
    }
}