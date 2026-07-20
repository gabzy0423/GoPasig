<?php

namespace App\Services\Routing;

use App\Models\VehiclePosition;
use Carbon\Carbon;

class FleetStatusService
{
    /**
     * Determine vehicle status from liveness first, then derived movement state.
     */
    public function determineStatus(?VehiclePosition $position): string
    {
        if (!$position) {
            return 'Unknown';
        }

        $now = now();
        $lastSeen = Carbon::parse($position->last_updated_at);
        $offlineTimeout = (int) config('fleet.gps.offline_timeout_seconds', 300);

        if ($lastSeen->diffInSeconds($now) > $offlineTimeout) {
            return 'Offline';
        }

        $movementState = strtoupper((string) ($position->movement_state ?? 'UNKNOWN'));
        if ($movementState === 'MOVING') {
            return 'Moving';
        }

        if ($movementState === 'STATIONARY') {
            $idleThreshold = (int) config('fleet.movement.idle_threshold_seconds', 180);
            return $this->stationaryDurationSeconds($position, $now) > $idleThreshold ? 'Idle' : 'Stopped';
        }

        if ($position->speed > 1.0) {
            return 'Moving';
        }

        $stationaryDuration = $position->updated_at ? $position->updated_at->diffInSeconds($now) : 0;
        if ($stationaryDuration > (int) config('fleet.movement.idle_threshold_seconds', 180)) {
            return 'Idle';
        }

        return 'Stopped';
    }

    public function stationaryDurationSeconds(?VehiclePosition $position, ?Carbon $now = null): ?int
    {
        if (!$position || strtoupper((string) ($position->movement_state ?? 'UNKNOWN')) !== 'STATIONARY') {
            return null;
        }

        $stationarySince = $position->movement_state_updated_at ?: $position->last_updated_at;
        if (!$stationarySince) {
            return null;
        }

        return Carbon::parse($stationarySince)->diffInSeconds($now ?: now());
    }
}
