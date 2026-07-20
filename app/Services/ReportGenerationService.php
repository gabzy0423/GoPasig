<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\TripLog;
use App\Models\MaintenanceRecord;
use App\Models\SystemSetting;
use Carbon\Carbon;

class ReportGenerationService
{
    /**
     * Generate Fleet Performance Report
     * Includes: overall utilization, on-time rate, incident count, maintenance status
     */
    public static function generateFleetPerformanceReport(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?int $routeId = null
    ): array {
        $startDate = $startDate ?? Carbon::now()->startOfMonth();
        $endDate = $endDate ?? Carbon::now()->endOfMonth();

        $query = Bus::with('route');
        if ($routeId) {
            $query->where('route_id', $routeId);
        }

        $buses = $query->get();
        $totalBuses = $buses->count();

        $stats = [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'days' => $startDate->diffInDays($endDate),
            ],
            'fleet_overview' => [
                'total_buses' => $totalBuses,
                'active_buses' => $buses->where('status', 'active')->count(),
                'maintenance_buses' => $buses->where('status', 'maintenance')->count(),
                'inactive_buses' => $buses->where('status', 'inactive')->count(),
            ],
            'performance_metrics' => self::getFleetPerformanceMetrics($buses, $startDate, $endDate),
            'utilization' => self::getFleetUtilization($buses, $startDate, $endDate),
            'maintenance_overview' => self::getFleetMaintenanceOverview($startDate, $endDate),
            'incident_summary' => self::getFleetIncidentSummary($startDate, $endDate),
        ];

        return $stats;
    }

    /**
     * Generate Route Performance Report
     * Includes: schedule adherence, passenger metrics, fleet allocation
     */
    public static function generateRoutePerformanceReport(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $startDate = $startDate ?? Carbon::now()->startOfMonth();
        $endDate = $endDate ?? Carbon::now()->endOfMonth();

        $routes = Route::with('schedules', 'buses', 'stops')->get();

        $routePerformance = [];

        foreach ($routes as $route) {
            $tripLogs = TripLog::forRoute($route->id)
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->get();

            $schedules = Schedule::where('route_id', $route->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            $onTimeCount = $schedules->where('status', 'On time')->count();
            $delayedCount = $schedules->where('status', 'delayed')->count();
            $totalSchedules = $schedules->count();

            $routePerformance[] = [
                'route_id' => $route->id,
                'route_name' => $route->name,
                'stops_count' => $route->stops->count(),
                'assigned_buses' => $route->buses->count(),
                'schedules' => [
                    'total' => $totalSchedules,
                    'on_time' => $onTimeCount,
                    'delayed' => $delayedCount,
                    'on_time_rate' => $totalSchedules > 0 ? round(($onTimeCount / $totalSchedules) * 100, 2) : 0,
                ],
                'trip_logs' => [
                    'total_trips' => $tripLogs->count(),
                    'total_passengers' => (int) $tripLogs->sum('peak_passengers'),
                    'avg_occupancy_rate' => round($tripLogs->avg('occupancy_rate') ?? 0, 2),
                    'avg_trip_duration_minutes' => round($tripLogs->avg('trip_duration_minutes') ?? 0, 2),
                ],
                'utilization' => [
                    'scheduled_hours' => $totalSchedules > 0 ? round(($totalSchedules * ($route->travel_time_minutes ?? 90)) / 60, 2) : 0,
                    'average_passengers_per_trip' => $tripLogs->count() > 0 
                        ? round($tripLogs->sum('peak_passengers') / $tripLogs->count(), 2) 
                        : 0,
                ],
            ];
        }

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'routes' => $routePerformance,
            'summary' => [
                'total_routes' => count($routePerformance),
                'average_on_time_rate' => count($routePerformance) > 0
                    ? round(collect($routePerformance)->avg('schedules.on_time_rate'), 2)
                    : 0,
                'total_scheduled_trips' => collect($routePerformance)->sum('schedules.total'),
            ],
        ];
    }

    /**
     * Generate Driver Rankings Report
     * Includes: performance scores, on-time rates, safety records
     */
    public static function generateDriverRankingsReport(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        int $limit = 50
    ): array {
        $startDate = $startDate ?? Carbon::now()->startOfMonth();
        $endDate = $endDate ?? Carbon::now()->endOfMonth();

        $drivers = Driver::with('tripLogs', 'incidents')->get();

        $driverStats = [];

        foreach ($drivers as $driver) {
            $tripLogs = TripLog::forDriver($driver->id)
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->get();

            $onTimeTrips = $tripLogs->where('is_on_time', true)->count();
            $totalTrips = $tripLogs->count();

            $incidents = $driver->incidents()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            $performanceScore = $driver->performance_score ?? 0;

            $driverStats[] = [
                'driver_id' => $driver->id,
                'name' => $driver->name,
                'license_number' => $driver->license_number,
                'license_expiry' => $driver->license_expiry?->format('Y-m-d'),
                'license_expiry_days' => $driver->license_expiry
                    ? Carbon::now()->diffInDays($driver->license_expiry, false)
                    : null,
                'status' => $driver->status,
                'performance_metrics' => [
                    'performance_score' => $performanceScore,
                    'grade' => self::getPerformanceGrade($performanceScore),
                    'trips_count' => $totalTrips,
                    'on_time_rate' => $totalTrips > 0 ? round(($onTimeTrips / $totalTrips) * 100, 2) : 0,
                    'avg_occupancy_rate' => round($tripLogs->avg('occupancy_rate') ?? 0, 2),
                ],
                'safety_record' => [
                    'incidents_period' => $incidents,
                    'incidents_last_30_days' => $driver->incidents_30,
                    'safety_rating' => max(0, 100 - ($incidents * 5)), // Simple formula
                ],
                'ranking_score' => self::calculateDriverRankingScore($performanceScore, $onTimeTrips, $totalTrips, $incidents),
            ];
        }

        // Sort by ranking score descending
        usort($driverStats, function ($a, $b) {
            return $b['ranking_score'] <=> $a['ranking_score'];
        });

        // Add ranking position
        foreach ($driverStats as $key => $stat) {
            $driverStats[$key]['ranking'] = $key + 1;
        }

        // Top performers (A grade)
        $topPerformers = array_filter($driverStats, function ($stat) {
            return $stat['performance_metrics']['grade'] === 'A';
        });

        // Underperformers (D/F grade)
        $underperformers = array_filter($driverStats, function ($stat) {
            return in_array($stat['performance_metrics']['grade'], ['D', 'F']);
        });

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'rankings' => array_slice($driverStats, 0, $limit),
            'summary' => [
                'total_drivers' => count($driverStats),
                'top_performers_count' => count($topPerformers),
                'underperformers_count' => count($underperformers),
                'average_performance_score' => count($driverStats) > 0
                    ? round(collect($driverStats)->avg('performance_metrics.performance_score'), 2)
                    : 0,
            ],
            'top_performers' => array_slice($topPerformers, 0, 10),
            'underperformers' => array_slice($underperformers, 0, 10),
        ];
    }

    /**
     * Private helper methods
     */

    private static function getFleetPerformanceMetrics($buses, $startDate, $endDate): array
    {
        $schedules = Schedule::whereBetween('created_at', [$startDate, $endDate])->get();
        $onTimeCount = $schedules->where('status', 'On time')->count();
        $delayedCount = $schedules->where('status', 'delayed')->count();
        $totalSchedules = $schedules->count();

        $tripLogs = TripLog::whereBetween('completed_at', [$startDate, $endDate])->get();

        return [
            'schedule_adherence' => [
                'total_scheduled_trips' => $totalSchedules,
                'on_time' => $onTimeCount,
                'delayed' => $delayedCount,
                'on_time_rate' => $totalSchedules > 0 ? round(($onTimeCount / $totalSchedules) * 100, 2) : 0,
            ],
            'passenger_metrics' => [
                'total_passengers' => (int) $tripLogs->sum('peak_passengers'),
                'avg_occupancy_rate' => round($tripLogs->avg('occupancy_rate') ?? 0, 2),
                'total_trips' => $tripLogs->count(),
                'passengers_per_trip' => $tripLogs->count() > 0
                    ? round($tripLogs->sum('peak_passengers') / $tripLogs->count(), 2)
                    : 0,
            ],
        ];
    }

    private static function getFleetUtilization($buses, $startDate, $endDate): array
    {
        $totalCapacity = $buses->sum('capacity');
        $totalSchedulesCount = Schedule::whereIn('bus_id', $buses->pluck('id'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        
        $totalPassengers = Schedule::whereIn('bus_id', $buses->pluck('id'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('passengers');

        return [
            'total_bus_capacity' => $totalCapacity,
            'total_passengers_transported' => $totalPassengers,
            'capacity_utilization_rate' => $totalSchedulesCount > 0 && $totalCapacity > 0
                ? round(($totalPassengers / ($totalCapacity * $totalSchedulesCount)) * 100, 2)
                : 0,
        ];
    }

    private static function getFleetMaintenanceOverview($startDate, $endDate): array
    {
        $maintenance = MaintenanceRecord::whereBetween('scheduled_at', [$startDate, $endDate])->get();

        return [
            'total_maintenance_scheduled' => $maintenance->count(),
            'completed' => $maintenance->where('status', 'completed')->count(),
            'in_progress' => $maintenance->where('status', 'in_progress')->count(),
            'scheduled' => $maintenance->where('status', 'scheduled')->count(),
            'average_completion_days' => round($maintenance->where('status', 'completed')
                ->avg(function ($m) {
                    return $m->scheduled_at->diffInDays($m->completed_at ?? now());
                }) ?? 0, 2),
        ];
    }

    private static function getFleetIncidentSummary($startDate, $endDate): array
    {
        $incidents = \App\Models\Incident::whereBetween('created_at', [$startDate, $endDate])->get();

        return [
            'total_incidents' => $incidents->count(),
            'by_type' => $incidents->groupBy('type')->map->count(),
            'by_severity' => $incidents->groupBy('severity')->map->count(),
        ];
    }

    private static function getPerformanceGrade(int $score): string
    {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }

    private static function calculateDriverRankingScore(int $perfScore, int $onTime, int $total, int $incidents): float
    {
        $onTimeRate = $total > 0 ? ($onTime / $total) * 100 : 0;
        $incidentPenalty = (int) SystemSetting::get('driver_score_incident_penalty', 10);
        $safetyScore = max(0, 100 - ($incidents * $incidentPenalty));

        $weightPerf = (float) SystemSetting::get('report_score_weight_performance', 0.4);
        $weightOnTime = (float) SystemSetting::get('report_score_weight_on_time', 0.4);
        $weightSafety = (float) SystemSetting::get('report_score_weight_safety', 0.2);

        return ($perfScore * $weightPerf) + ($onTimeRate * $weightOnTime) + ($safetyScore * $weightSafety);
    }

    /**
     * Export report as CSV
     */
    public static function exportDriverRankingsAsCSV($drivers): string
    {
        $csv = "Rank,Driver Name,License,Performance Score,Grade,Trips,On-Time Rate,Incidents,Safety Rating\n";

        foreach ($drivers as $driver) {
            $csv .= sprintf(
                "%d,%s,%s,%d,%s,%d,%.2f%%,%d,%d\n",
                $driver['ranking'],
                $driver['name'],
                $driver['license_number'],
                $driver['performance_metrics']['performance_score'],
                $driver['performance_metrics']['grade'],
                $driver['performance_metrics']['trips_count'],
                $driver['performance_metrics']['on_time_rate'],
                $driver['safety_record']['incidents_period'],
                $driver['safety_record']['safety_rating']
            );
        }

        return $csv;
    }

    /**
     * Export report as JSON
     */
    public static function exportReportAsJSON($report): string
    {
        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
