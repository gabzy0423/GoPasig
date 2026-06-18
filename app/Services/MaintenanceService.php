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

            $bus = Bus::find($record->bus_id);
            if ($bus) {
                $restoreStatus = $bus->previous_status ?? 'active';
                $bus->update(['status' => $restoreStatus, 'previous_status' => null]);
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
            $bus->lockToMaintenance();
        } elseif ($newStatus === 'completed') {
            $restoreStatus = $bus->previous_status ?? 'active';
            $bus->update(['status' => $restoreStatus, 'previous_status' => null]);
        } elseif ($newStatus === 'cancelled') {
            $query = MaintenanceRecord::where('bus_id', $busId)
                ->whereIn('status', ['scheduled', 'in_progress']);

            if ($editingId) {
                $query->where('id', '!=', $editingId);
            }

            if (!$query->exists()) {
                $restoreStatus = $bus->previous_status ?? 'active';
                $bus->update(['status' => $restoreStatus, 'previous_status' => null]);
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

        return [
            'in_maintenance' => true,
            'status' => 'maintenance',
            'record_id' => $record->id,
            'maintenance_status' => $record->status,
            'inspection_status' => $record->getInspectionStatus(),
            'inspection_passed' => $record->inspection_passed,
            'completion_time' => $record->scheduled_at
                ->addMinutes($record->expected_duration_minutes)
                ->timezone('Asia/Manila')
                ->format('h:i A')
        ];
    }

    /**
     * Sync maintenance record with bus status
     * Ensures consistency between record status and bus status
     */
    public static function syncMaintenanceWithBusStatus(MaintenanceRecord $record): void
    {
        $bus = Bus::find($record->bus_id);
        if (!$bus) return;

        // If maintenance is completed but bus is still in maintenance, fix it
        if ($record->status === 'completed' && $bus->status === 'maintenance') {
            $restoreStatus = $bus->previous_status ?? 'active';
            $bus->update(['status' => $restoreStatus, 'previous_status' => null]);
        }

        // If maintenance is scheduled/in_progress but bus is active, lock it
        if (in_array($record->status, ['scheduled', 'in_progress']) && $bus->status !== 'maintenance') {
            $bus->lockToMaintenance();
        }

        // If maintenance is cancelled but bus is in maintenance, check if others exist
        if ($record->status === 'cancelled' && $bus->status === 'maintenance') {
            $hasOther = MaintenanceRecord::where('bus_id', $bus->id)
                ->where('id', '!=', $record->id)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->exists();

            if (!$hasOther) {
                $restoreStatus = $bus->previous_status ?? 'active';
                $bus->update(['status' => $restoreStatus, 'previous_status' => null]);
            }
        }
    }
}
