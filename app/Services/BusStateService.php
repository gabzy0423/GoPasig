<?php

namespace App\Services;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Bus;
use Illuminate\Support\Facades\Log;

class BusStateService
{
    /**
     * Valid state transitions for bus status
     * Issue 4.2.2: Bus status state machine
     */
    private const VALID_TRANSITIONS = [
        'active'      => ['inactive', 'maintenance'],
        'inactive'    => ['active', 'maintenance'],
        'maintenance' => ['active', 'inactive'],
    ];

    /**
     * Validate if transition is allowed
     */
    public static function canTransition(string $currentStatus, string $newStatus): bool
    {
        if ($currentStatus === $newStatus) {
            return true;
        }

        return in_array($newStatus, self::VALID_TRANSITIONS[$currentStatus] ?? []);
    }

    /**
     * Transition bus to new status with validation.
     * Throws InvalidStatusTransitionException on invalid transition.
     */
    public static function transition(Bus $bus, string $newStatus, string $reason = ''): Bus
    {
        $oldStatus = $bus->status;

        if (!self::canTransition($oldStatus, $newStatus)) {
            throw new InvalidStatusTransitionException(
                $oldStatus,
                $newStatus,
                self::VALID_TRANSITIONS[$oldStatus] ?? []
            );
        }

        if ($newStatus === 'maintenance') {
            $bus->lockToMaintenance();
        } else {
            $bus->update(['status' => $newStatus]);
        }

        Log::info('Bus status transition', [
            'bus_id'     => $bus->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason'     => $reason,
        ]);

        return $bus;
    }

    /**
     * Get valid next statuses for current status
     */
    public static function getValidTransitions(string $currentStatus): array
    {
        return self::VALID_TRANSITIONS[$currentStatus] ?? [];
    }
}
