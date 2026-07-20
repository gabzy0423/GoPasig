<?php

namespace App\Services;

use App\Models\MaintenanceRecord;
use App\Models\Bus;
use App\Models\MaintenanceInspection;
use Carbon\Carbon;

class MaintenanceStatisticsService
{
    /**
     * Compute and format all summary statistics for the maintenance dashboard.
     *
     * @return array
     */
    public function getSummary(): array
    {
        $totalRecords = MaintenanceRecord::count();
        $scheduledCount = MaintenanceRecord::where('status', 'scheduled')->count();
        $inProgressCount = MaintenanceRecord::where('status', 'in_progress')->count();
        $completedCount = MaintenanceRecord::where('status', 'completed')->count();
        $cancelledCount = MaintenanceRecord::where('status', 'cancelled')->count();
        
        // Observation count must be based on buses, NOT maintenance records, to prevent duplicate counting.
        $observationCount = Bus::where('has_observation', true)->count();

        // Overdue count: status = scheduled and scheduled_at < now()
        $overdueCount = MaintenanceRecord::where('status', 'scheduled')
            ->where('scheduled_at', '<', Carbon::now())
            ->count();

        // requiringRepairCount: status = in_progress and inspection_passed = false (Option B: active failed tickets)
        $requiringRepairCount = MaintenanceRecord::where('status', 'in_progress')
            ->where('inspection_passed', false)
            ->count();

        // Average duration calculation
        $avgMinutes = MaintenanceRecord::whereNotNull('actual_duration_minutes')->avg('actual_duration_minutes') ?? 0;
        $avgMinutesVal = (int) round($avgMinutes);
        $hours = floor($avgMinutesVal / 60);
        $minutes = $avgMinutesVal % 60;

        if ($hours > 0) {
            $averageDuration = "{$hours} hr" . ($hours > 1 ? 's' : '') . ($minutes > 0 ? " {$minutes} min" . ($minutes > 1 ? 's' : '') : '');
        } else {
            $averageDuration = "{$minutes} min" . ($minutes > 1 ? 's' : '');
        }

        return [
            'totalRecords' => $totalRecords,
            'scheduledCount' => $scheduledCount,
            'inProgressCount' => $inProgressCount,
            'completedCount' => $completedCount,
            'cancelledCount' => $cancelledCount,
            'observationCount' => $observationCount,
            'overdueCount' => $overdueCount,
            'requiringRepairCount' => $requiringRepairCount,
            'averageDuration' => $averageDuration,
        ];
    }
}
