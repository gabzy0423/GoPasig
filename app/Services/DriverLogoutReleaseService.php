<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\BusStatusAuditLog;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DriverLogoutReleaseService
{
    /**
     * Release a driver's retained post-trip assignment before logout.
     *
     * @return array{logout_allowed: bool, released: bool, message: ?string}
     */
    public function release(User $user): array
    {
        if ($user->role !== 'driver') {
            return $this->result(true, false);
        }

        return DB::transaction(function () use ($user) {
            $driver = Driver::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $driver) {
                return $this->result(true, false);
            }

            $activeTrip = Trip::where('driver_id', $driver->id)
                ->whereIn('status', ['dispatched', 'ongoing'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($activeTrip) {
                $action = $activeTrip->status === 'ongoing'
                    ? 'End the active trip first.'
                    : 'Ask dispatch to cancel or complete the pending assignment first.';

                return $this->result(
                    false,
                    false,
                    "Cannot sign out while Trip #{$activeTrip->id} is {$activeTrip->status}. {$action}",
                );
            }

            $bus = $this->assignedBus($driver);

            if ($bus) {
                $busActiveTrip = Trip::where('bus_id', $bus->id)
                    ->whereIn('status', ['dispatched', 'ongoing'])
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();

                if ($busActiveTrip) {
                    return $this->result(
                        false,
                        false,
                        "Cannot sign out while the assigned bus has active Trip #{$busActiveTrip->id}.",
                    );
                }

                // Breakdown retains incident ownership and must be resolved by dispatch.
                if ($bus->status === Bus::STATUS_BREAKDOWN) {
                    return $this->result(true, false, 'Breakdown assignment retained for incident resolution.');
                }

                $oldStatus = $bus->status;
                $newStatus = $oldStatus === Bus::STATUS_MAINTENANCE
                    ? Bus::STATUS_MAINTENANCE
                    : Bus::STATUS_INACTIVE;

                $bus->update([
                    'status' => $newStatus,
                    'previous_status' => $newStatus === Bus::STATUS_INACTIVE ? null : $bus->previous_status,
                    'driver_name' => Bus::getDefaultDriverName(),
                    'route_id' => null,
                    'next_stop' => null,
                    'passengers' => 0,
                    'speed' => 0,
                    'eta' => null,
                ]);

                BusStatusAuditLog::logStatusChange(
                    busId: $bus->id,
                    newStatus: $newStatus,
                    oldStatus: $oldStatus,
                    userId: $user->id,
                    reason: 'Driver logout released retained assignment',
                    metadata: [
                        'source' => 'driver_logout',
                        'driver_id' => $driver->id,
                    ],
                );
            }

            $driver->update([
                'operational_status' => $driver->status === 'active' ? 'available' : 'unavailable',
                'assigned_bus' => null,
                'assigned_route' => null,
            ]);

            return $this->result(true, true);
        });
    }

    private function assignedBus(Driver $driver): ?Bus
    {
        $plateNumber = trim((string) $driver->assigned_bus);

        if ($plateNumber === '') {
            return null;
        }

        return Bus::where('plate_number', $plateNumber)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @return array{logout_allowed: bool, released: bool, message: ?string}
     */
    private function result(bool $logoutAllowed, bool $released, ?string $message = null): array
    {
        return [
            'logout_allowed' => $logoutAllowed,
            'released' => $released,
            'message' => $message,
        ];
    }
}
