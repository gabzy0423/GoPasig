<?php

namespace App\Services;

use App\Models\MaintenanceRecord;
use App\Models\Bus;
use Illuminate\Support\Facades\DB;

class MaintenanceService
{
    /**
     * Complete a maintenance record with inspection validation
     * Ensures bus status is updated to active
     */
    public static function completeMaintenance(MaintenanceRecord $record): array
    {
        // Safety gate: inspection must be passed
        if (!$record->canBeCompleted()) {
            return [
                'success' => false,
                'message' => 'Cannot complete: Safety inspection must PASS first',
                'inspection_status' => $record->getInspectionStatus()
            ];
        }

        DB::transaction(function () use ($record) {
            $record->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            $bus = Bus::find($record->getRawOriginal('bus_id'));
            if ($bus) {
                $restoreStatus = $bus->previous_status ?? 'active';
                \App\Services\BusStateService::transition($bus, $restoreStatus, 'Maintenance completed');
            }
        });

        return [
            'success' => true,
            'message' => 'Maintenance completed! Bus returned to active service.',
            'record' => $record->load('bus')
        ];
    }

    /**
     * Handle bus status side effects for maintenance operations
     * Used by both Admin and Fleet controllers
     */
    public static function handleBusStatusSideEffects($busId, $oldStatus, $newStatus, $editingId = null): void
    {
        $bus = Bus::find($busId);
        if (!$bus) return;

        if (in_array($newStatus, ['scheduled', 'in_progress'])) {
            \App\Services\BusStateService::transition($bus, \App\Models\Bus::STATUS_MAINTENANCE, 'Maintenance scheduled');
        } elseif ($newStatus === 'completed') {
            $restoreStatus = $bus->previous_status ?? \App\Models\Bus::STATUS_INACTIVE;
            \App\Services\BusStateService::transition($bus, $restoreStatus, 'Maintenance completed');
        } elseif ($newStatus === 'cancelled') {
            $query = MaintenanceRecord::where('bus_id', $busId)
                ->whereIn('status', ['scheduled', 'in_progress']);

            if ($editingId) {
                $query->where('id', '!=', $editingId);
            }

            if (!$query->exists()) {
                $restoreStatus = $bus->previous_status ?? 'active';
                \App\Services\BusStateService::transition($bus, $restoreStatus, 'Maintenance cancelled');
            }
        }
    }

    /**
     * Get current bus maintenance status for display
     */
    public static function getBusMaintenanceStatus(Bus $bus): array
    {
        if ($bus->status !== 'maintenance') {
            return [
                'in_maintenance' => false,
                'status' => 'active'
            ];
        }

        // Get active maintenance record
        $record = MaintenanceRecord::where('bus_id', $bus->id)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->orderByDesc('scheduled_at')
            ->first();

        if (!$record) {
            return [
                'in_maintenance' => false,
                'status' => 'active'
            ];
        }

        $completionTime = null;
        if ($record->scheduled_at && $record->expected_duration_minutes) {
            $completionTime = $record->scheduled_at->copy()
                ->addMinutes($record->expected_duration_minutes)
                ->timezone('Asia/Manila')
                ->format('h:i A');
        } elseif ($record->scheduled_at) {
            $completionTime = $record->scheduled_at
                ->timezone('Asia/Manila')
                ->format('h:i A') . ' (+?m)';
        }

        return [
            'in_maintenance' => true,
            'status' => 'maintenance',
            'record_id' => $record->id,
            'maintenance_status' => $record->status,
            'inspection_status' => $record->getInspectionStatus(),
            'inspection_passed' => $record->inspection_passed,
            'completion_time' => $completionTime
        ];
    }

    /**
     * Sync maintenance record with bus status.
     * Maintenance Result is the single source of truth:
     *   Passed Inspection       → inactive, has_observation = false
     *   Passed with Observation → inactive, has_observation = true
     *   Failed Inspection       → stays in maintenance, has_observation = false
     */
    public static function syncMaintenanceWithBusStatus(MaintenanceRecord $record): void
    {
        $bus = Bus::find($record->getRawOriginal('bus_id'));
        if (!$bus) return;

        if ($record->status === 'completed') {
            $result         = $record->maintenance_result;
            $releaseBus     = $result !== 'Failed Inspection';
            $hasObservation = $result === 'Passed with Observation';

            if ($releaseBus && $bus->status === \App\Models\Bus::STATUS_MAINTENANCE) {
                \App\Services\BusStateService::transition(
                    $bus,
                    \App\Models\Bus::STATUS_INACTIVE,
                    "Sync: Maintenance completed ({$result})"
                );
            }
            // Sync observation flag regardless
            if ($bus->has_observation !== $hasObservation) {
                $bus->update(['has_observation' => $hasObservation]);
            }
            return;
        }

        // If maintenance is scheduled/in_progress but bus is not in maintenance, lock it
        if (in_array($record->status, ['scheduled', 'in_progress']) && $bus->status !== \App\Models\Bus::STATUS_MAINTENANCE) {
            \App\Services\BusStateService::transition($bus, \App\Models\Bus::STATUS_MAINTENANCE, 'Sync: Maintenance active');
        }

        // If maintenance is cancelled but bus is in maintenance, check if others exist
        if ($record->status === 'cancelled' && $bus->status === \App\Models\Bus::STATUS_MAINTENANCE) {
            $hasOther = MaintenanceRecord::where('bus_id', $bus->id)
                ->where('id', '!=', $record->id)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->exists();

            if (!$hasOther) {
                $restoreStatus = $bus->previous_status ?? \App\Models\Bus::STATUS_INACTIVE;
                \App\Services\BusStateService::transition($bus, $restoreStatus, 'Sync: Maintenance cancelled');
            }
        }
    }
}
