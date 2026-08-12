<?php

namespace App\Services;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Bus;
use App\Models\BusStatusAuditLog;
use App\Models\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class BusStateService
{
    /**
     * Valid state transitions for bus status
     */
    private const VALID_TRANSITIONS = [
        'active'      => ['inactive', 'maintenance', 'breakdown', 'ready', 'operating'],
        'inactive'    => ['active', 'maintenance', 'breakdown', 'ready', 'operating'],
        'maintenance' => ['active', 'inactive', 'breakdown', 'ready', 'operating'],
        'breakdown'   => ['inactive', 'maintenance'],
        'ready'       => ['inactive', 'maintenance', 'breakdown', 'operating'],
        'operating'   => ['inactive', 'maintenance', 'breakdown', 'ready'],
        'available'   => ['active', 'inactive', 'maintenance', 'breakdown', 'ready', 'operating'],
    ];

    /**
     * Allowed manual state transitions via Admin UI
     */
    public const MANUAL_TRANSITIONS = [
        'active'      => ['inactive', 'breakdown'],
        'inactive'    => ['active', 'breakdown'],
        'maintenance' => ['inactive', 'breakdown'],
        'breakdown'   => ['inactive', 'maintenance'],
        'ready'       => ['inactive', 'breakdown'],
        'operating'   => ['inactive', 'breakdown'],
        'available'   => ['inactive', 'breakdown', 'maintenance'],
    ];

    /**
     * Validate if transition is allowed
     */
    public static function canTransition(string $currentStatus, string $newStatus): bool
    {
        if ($currentStatus === $newStatus) {
            return false;
        }

        return in_array($newStatus, self::VALID_TRANSITIONS[$currentStatus] ?? []);
    }

    /**
     * Transition bus to new status with validation.
     * Throws InvalidStatusTransitionException on invalid transition.
     */
    public static function transition(
        Bus $bus,
        string $newStatus,
        string $reason = '',
        \App\Models\Driver $driver = null,
        \App\Models\Route $route = null
    ): Bus {
        return DB::transaction(function () use ($bus, $newStatus, $reason, $driver, $route) {
            // Row-level lock the bus record to prevent race conditions
            $bus = Bus::where('id', $bus->id)->lockForUpdate()->first();
            if (!$bus) {
                throw new \Exception("Bus record not found for lock");
            }
            $oldStatus = $bus->status;

            if (!self::canTransition($oldStatus, $newStatus)) {
                throw new InvalidStatusTransitionException(
                    $oldStatus,
                    $newStatus,
                    self::VALID_TRANSITIONS[$oldStatus] ?? []
                );
            }

            if ($newStatus === Bus::STATUS_MAINTENANCE) {
                $previousStatus = $oldStatus === Bus::STATUS_MAINTENANCE ? $bus->previous_status : $oldStatus;
                $bus->update([
                    'previous_status' => $previousStatus,
                    'status'          => Bus::STATUS_MAINTENANCE,
                ]);
            } else {
                $bus->update([
                    'status'          => $newStatus,
                    'previous_status' => null, // clear when transitioning back
                ]);
            }

            // Centralized driver assignment on active/ready status transition (dispatch)
            if (($newStatus === Bus::STATUS_ACTIVE || $newStatus === 'ready') && $driver && $route) {
                self::syncDriverAndRouteAssignments($bus, $driver, $route);
            }

            // Centralized Operational State Reset when entering non-operational/standby states
            if ($newStatus === Bus::STATUS_INACTIVE || $newStatus === Bus::STATUS_MAINTENANCE || $newStatus === Bus::STATUS_BREAKDOWN || $newStatus === 'available') {
                $previousDriverName = $bus->driver_name;

                if ($newStatus === Bus::STATUS_INACTIVE || $newStatus === Bus::STATUS_MAINTENANCE || $newStatus === 'available') {
                    // Reset all fields including driver and route
                    $bus->update([
                        'driver_name' => Bus::DEFAULT_DRIVER_NAME,
                        'route_id'    => null,
                        'next_stop'   => null,
                        'passengers'  => 0,
                        'speed'       => 0,
                        'eta'         => null,
                    ]);

                    // Unassign and free driver
                    $driverId = null;
                    $activeTrip = \App\Models\Trip::where('bus_id', $bus->id)
                        ->whereIn('status', ['ongoing', 'dispatched'])
                        ->first();
                    if ($activeTrip) {
                        $driverId = $activeTrip->driver_id;
                    }

                    if (!$driverId) {
                        $driverId = \App\Models\Driver::where('assigned_bus', $bus->plate_number)->value('id');
                    }

                    if ($driverId) {
                        \App\Models\Driver::where('id', $driverId)
                            ->lockForUpdate()
                            ->update([
                                'operational_status' => 'available',
                                'assigned_bus'       => null,
                                'assigned_route'     => null,
                            ]);
                    }

                    if ($previousDriverName && $previousDriverName !== Bus::DEFAULT_DRIVER_NAME) {
                        $nameParts = explode(' ', $previousDriverName);
                        $firstName = $nameParts[0];
                        $lastName  = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : '';
                        
                        \App\Models\Driver::where('first_name', $firstName)
                            ->where('last_name', $lastName)
                            ->lockForUpdate()
                            ->update([
                                'operational_status' => 'available',
                                'assigned_bus'       => null,
                                'assigned_route'     => null,
                            ]);
                    }
                } else {
                    // Breakdown: reset live fields but PRESERVE driver and route assignments on the bus
                    $bus->update([
                        'next_stop'  => null,
                        'passengers' => 0,
                        'speed'      => 0,
                        'eta'        => null,
                    ]);

                    // Deactivate driver (set status to unavailable, but keep assignments)
                    $driverId = null;
                    $activeTrip = \App\Models\Trip::where('bus_id', $bus->id)
                        ->whereIn('status', ['ongoing', 'dispatched'])
                        ->first();
                    if ($activeTrip) {
                        $driverId = $activeTrip->driver_id;
                    }

                    if (!$driverId) {
                        $driverId = \App\Models\Driver::where('assigned_bus', $bus->plate_number)->value('id');
                    }

                    if ($driverId) {
                        \App\Models\Driver::where('id', $driverId)
                            ->lockForUpdate()
                            ->update([
                                'operational_status' => 'unavailable',
                            ]);
                    }

                    if ($previousDriverName && $previousDriverName !== Bus::DEFAULT_DRIVER_NAME) {
                        $nameParts = explode(' ', $previousDriverName);
                        $firstName = $nameParts[0];
                        $lastName  = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : '';
                        
                        \App\Models\Driver::where('first_name', $firstName)
                            ->where('last_name', $lastName)
                            ->lockForUpdate()
                            ->update([
                                'operational_status' => 'unavailable',
                            ]);
                    }
                }

                // Cancel and finalize any ongoing/dispatched trips for this bus.
                $tripsToCancel = \App\Models\Trip::where('bus_id', $bus->id)
                    ->whereIn('status', ['ongoing', 'dispatched'])
                    ->lockForUpdate()
                    ->get();

                $endedAt = now();
                foreach ($tripsToCancel as $tripToCancel) {
                    $tripToCancel->update([
                        'status'      => 'cancelled',
                        'gps_session' => 'CLOSED',
                        'ended_at'    => $endedAt,
                    ]);

                    TripLogService::logTrip($tripToCancel->fresh(), [
                        'completed_at' => $endedAt,
                        'status' => 'cancelled',
                    ]);
                }

                // Cancel active/pending schedules for this bus
                \App\Models\Schedule::where('bus_id', $bus->id)
                    ->whereNotIn('status', [Schedule::STATUS_CANCELLED, 'completed'])
                    ->lockForUpdate()
                    ->update([
                        'status' => Schedule::STATUS_CANCELLED,
                    ]);
            }

            // Centralized Operational State Reset when transitioning AWAY from breakdown
            if ($oldStatus === Bus::STATUS_BREAKDOWN && $newStatus !== Bus::STATUS_BREAKDOWN) {
                $previousDriverName = $bus->driver_name;

                // Unassign driver and route from the bus
                $bus->update([
                    'driver_name' => Bus::DEFAULT_DRIVER_NAME,
                    'route_id'    => null,
                ]);

                // Unassign bus and route from the driver
                $driverId = null;
                $activeTrip = \App\Models\Trip::where('bus_id', $bus->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                if ($activeTrip) {
                    $driverId = $activeTrip->driver_id;
                }

                if (!$driverId) {
                    $driverId = \App\Models\Driver::where('assigned_bus', $bus->plate_number)->value('id');
                }

                if ($driverId) {
                    \App\Models\Driver::where('id', $driverId)
                        ->lockForUpdate()
                        ->update([
                            'operational_status' => 'available',
                            'assigned_bus'       => null,
                            'assigned_route'     => null,
                        ]);
                }

                if ($previousDriverName && $previousDriverName !== Bus::DEFAULT_DRIVER_NAME) {
                    $nameParts = explode(' ', $previousDriverName);
                    $firstName = $nameParts[0];
                    $lastName  = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : '';
                    
                    \App\Models\Driver::where('first_name', $firstName)
                        ->where('last_name', $lastName)
                        ->lockForUpdate()
                        ->update([
                            'operational_status' => 'available',
                            'assigned_bus'       => null,
                            'assigned_route'     => null,
                        ]);
                }
            }

            BusStatusAuditLog::logStatusChange(
                busId: $bus->id,
                newStatus: $newStatus,
                oldStatus: $oldStatus,
                userId: Auth::id(),
                reason: $reason,
            );

            Log::info('Bus status transition', [
                'bus_id'     => $bus->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason'     => $reason,
            ]);

            return $bus->fresh();
        });
    }

    /**
     * Reassign driver and/or route on an already active bus.
     */
    public static function reassignDriverAndRoute(
        Bus $bus,
        \App\Models\Driver $driver = null,
        \App\Models\Route $route = null,
        string $reason = ''
    ): Bus {
        return DB::transaction(function () use ($bus, $driver, $route, $reason) {
            $bus = Bus::where('id', $bus->id)->lockForUpdate()->first();
            if (!$bus) {
                throw new \Exception("Bus record not found for lock");
            }

            if ($bus->status !== Bus::STATUS_ACTIVE && $bus->status !== 'ready' && $bus->status !== 'operating') {
                throw new \Exception("Cannot reassign driver/route for a non-active/non-ready bus.");
            }

            self::syncDriverAndRouteAssignments($bus, $driver, $route);

            BusStatusAuditLog::logStatusChange(
                busId: $bus->id,
                newStatus: $bus->status,
                oldStatus: $bus->status,
                userId: Auth::id(),
                reason: $reason ?: 'Driver/Route reassigned via admin',
            );

            Log::info('Bus driver/route reassigned', [
                'bus_id'     => $bus->id,
                'driver_id'  => $driver?->id,
                'route_id'   => $route?->id,
                'reason'     => $reason,
            ]);

            return $bus->fresh();
        });
    }

    /**
     * Centralized logic to synchronize driver and route assignments.
     */
    private static function syncDriverAndRouteAssignments(
        Bus $bus,
        \App\Models\Driver $driver = null,
        \App\Models\Route $route = null
    ): void {
        if ($driver && $route) {
            // Lock the driver record
            $driver = \App\Models\Driver::where('id', $driver->id)->lockForUpdate()->first();
            $fullName = $driver->first_name . ' ' . $driver->last_name;

            // 1. Clean up: if driver was previously assigned to a different bus, set it to available
            if ($driver->assigned_bus && $driver->assigned_bus !== $bus->plate_number) {
                $prevBus = Bus::where('plate_number', $driver->assigned_bus)->lockForUpdate()->first();
                if ($prevBus) {
                    self::transition($prevBus, Bus::STATUS_INACTIVE, 'Driver reassigned to another bus');
                    $prevBus->update([
                        'driver_name' => Bus::DEFAULT_DRIVER_NAME,
                        'route_id'    => null,
                    ]);
                }
            }

            // 2. Clean up: remove driver name from any other bus records
            $otherBus = Bus::where('driver_name', $fullName)->where('id', '!=', $bus->id)->lockForUpdate()->first();
            if ($otherBus) {
                self::transition($otherBus, Bus::STATUS_INACTIVE, 'Driver reassigned to another bus');
                $otherBus->update([
                    'driver_name' => Bus::DEFAULT_DRIVER_NAME,
                    'route_id'    => null,
                ]);
            }

            // 3. Clean up: set any other driver previously assigned to this bus to available
            \App\Models\Driver::where('assigned_bus', $bus->plate_number)
                ->where('id', '!=', $driver->id)
                ->lockForUpdate()
                ->get()
                ->each(function ($otherDriver) {
                    $otherDriver->update([
                        'operational_status' => 'available',
                        'assigned_bus'       => null,
                        'assigned_route'     => null,
                    ]);
                });

            // 4. Update current bus assignments
            $firstStop = \App\Models\Stop::where('route_id', $route->id)->orderBy('sequence')->first();
            $fallbackTerminal = \App\Models\SystemSetting::get('default_terminal_name', \App\Models\Terminal::getDefaultName());
            $bus->update([
                'route_id'    => $route->id,
                'driver_name' => $fullName,
                'passengers'  => 0,
                'next_stop'   => $firstStop?->name ?? $fallbackTerminal,
                'eta'         => (int) \App\Models\SystemSetting::get('default_dispatch_eta_minutes', 5),
            ]);

            // 5. Update driver status & assignments
            $driver->update([
                'status'             => 'active',
                'operational_status' => 'assigned',
                'assigned_bus'       => $bus->plate_number,
                'assigned_route'     => $route->id,
            ]);
        }
    }

    /**
     * Get valid next statuses for current status
     */
    public static function getValidTransitions(string $currentStatus): array
    {
        return self::VALID_TRANSITIONS[$currentStatus] ?? [];
    }

    /**
     * Get allowed manual state transitions via Admin UI
     */
    public static function getValidManualTransitions(string $currentStatus): array
    {
        return self::MANUAL_TRANSITIONS[$currentStatus] ?? [];
    }

    /**
     * Get allowed initial statuses for registering a new bus.
     */
    public static function getValidInitialStatuses(): array
    {
        return ['inactive', 'maintenance', 'breakdown'];
    }
}
