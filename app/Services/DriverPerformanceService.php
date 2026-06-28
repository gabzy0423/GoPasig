<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Incident;
use App\Models\Schedule;
use App\Models\SystemSetting;
use Carbon\Carbon;

class DriverPerformanceService
{
    public static function calculateScore(int $driverId, Carbon $start, Carbon $end, float $baseScore = null): int
    {
        if (!Driver::find($driverId)) {
            return 0;
        }

        $incidentCount = self::incidentCountForPeriod($driverId, $start, $end);
        $delayCount = self::scheduleWindowQuery($driverId, $start, $end)
            ->whereIn('status', [Schedule::STATUS_DELAYED, 'delayed'])
            ->count();

        $performanceScore = 100
            - ($incidentCount * self::incidentPenalty())
            - ($delayCount * self::delayPenalty());

        return max(0, min(100, $performanceScore));
    }

    public static function recalculate(int $driverId): int|bool
    {
        $driver = Driver::find($driverId);
        if (!$driver) {
            return false;
        }

        try {
            $rollingDays = self::rollingDays();
            $start = Carbon::now('Asia/Manila')->subDays($rollingDays)->startOfDay();
            $end = Carbon::now('Asia/Manila')->endOfDay();
            $incidentCount = self::incidentCountForPeriod($driverId, $start, $end);
            $performanceScore = self::calculateScore($driverId, $start, $end, (float) $driver->performance_score);

            $driver->update([
                'performance_score' => $performanceScore,
                'incidents_30' => $incidentCount,
                'updated_at' => now(),
            ]);

            return $performanceScore;
        } catch (\Throwable) {
            return false;
        }
    }

    protected static function calculateOnTimeRateForPeriod(int $driverId, Carbon $start, Carbon $end): int
    {
        $totalSchedules = self::scheduleWindowQuery($driverId, $start, $end)
            ->whereNotIn('status', [Schedule::STATUS_CANCELLED, 'cancelled'])
            ->count();

        if ($totalSchedules === 0) {
            return 100;
        }

        $onTimeSchedules = self::scheduleWindowQuery($driverId, $start, $end)
            ->where('status', Schedule::STATUS_ON_TIME)
            ->count();

        return (int) round(($onTimeSchedules / $totalSchedules) * 100);
    }

    protected static function calculateIncidentRateForPeriod(int $driverId, Carbon $start, Carbon $end): int
    {
        $incidentPenalty = self::incidentPenalty();
        $incidentCount = self::incidentCountForPeriod($driverId, $start, $end);

        return max(0, 100 - ($incidentCount * $incidentPenalty));
    }

    protected static function calculateOnTimeRate(int $driverId, Carbon $from): int
    {
        return self::calculateOnTimeRateForPeriod($driverId, $from, Carbon::now('Asia/Manila')->endOfDay());
    }

    protected static function calculateIncidentRate(int $driverId, Carbon $from): int
    {
        return self::calculateIncidentRateForPeriod($driverId, $from, Carbon::now('Asia/Manila')->endOfDay());
    }

    protected static function calculatePassengerRating(int $driverId, Carbon $start, Carbon $end = null): int
    {
        return (int) SystemSetting::get('driver_passenger_rating_default', 80);
    }

    public static function getSummary(int $driverId): array
    {
        $driver = Driver::find($driverId);
        if (!$driver) {
            return [];
        }

        $rollingDays = self::rollingDays();
        $start = Carbon::now('Asia/Manila')->subDays($rollingDays)->startOfDay();
        $end = Carbon::now('Asia/Manila')->endOfDay();

        $totalSchedules = self::scheduleWindowQuery($driverId, $start, $end)
            ->whereNotIn('status', [Schedule::STATUS_CANCELLED, 'cancelled'])
            ->count();

        $onTimeSchedules = self::scheduleWindowQuery($driverId, $start, $end)
            ->where('status', Schedule::STATUS_ON_TIME)
            ->count();

        $delayedSchedules = self::scheduleWindowQuery($driverId, $start, $end)
            ->whereIn('status', [Schedule::STATUS_DELAYED, 'delayed'])
            ->count();

        $totalPassengers = self::scheduleWindowQuery($driverId, $start, $end)
            ->sum('passengers');

        $avgPassengersPerTrip = $totalSchedules > 0
            ? round($totalPassengers / $totalSchedules, 1)
            : 0;

        return [
            'driver_id' => $driver->id,
            'driver_name' => "{$driver->first_name} {$driver->last_name}",
            'performance_score' => $driver->performance_score,
            'performance_grade' => self::getPerformanceGrade($driver->performance_score),
            'last_30_days' => [
                'total_schedules' => $totalSchedules,
                'on_time_schedules' => $onTimeSchedules,
                'on_time_rate' => $totalSchedules > 0 ? round(($onTimeSchedules / $totalSchedules) * 100) : 0,
                'delayed_schedules' => $delayedSchedules,
                'delayed_rate' => $totalSchedules > 0 ? round(($delayedSchedules / $totalSchedules) * 100) : 0,
                'total_passengers' => $totalPassengers,
                'avg_passengers_per_trip' => $avgPassengersPerTrip,
            ],
            'incidents' => self::incidentCountForPeriod($driverId, $start, $end),
            'license_expiry' => $driver->license_expiry,
            'license_status' => self::getLicenseStatus($driver->license_expiry),
        ];
    }

    public static function getPerformanceGrade(int $score): string
    {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }

    public static function getLicenseStatus($expiryDate = null): string
    {
        if (!$expiryDate) {
            return 'unknown';
        }

        $expiry = Carbon::parse($expiryDate, 'Asia/Manila')->startOfDay();
        $today = Carbon::today('Asia/Manila');
        $daysUntilExpiry = (int) $today->diffInDays($expiry, false);

        if ($daysUntilExpiry < 0) {
            return 'expired';
        }

        if ($daysUntilExpiry <= self::licenseCriticalDays()) {
            return 'expiring_soon';
        }

        if ($daysUntilExpiry <= self::licenseWarningDays()) {
            return 'expiring_soon_warning';
        }

        return 'valid';
    }

    public static function getTopPerformers(int $limit = 5): array
    {
        return Driver::where('status', 'active')
            ->orderByDesc('performance_score')
            ->take($limit)
            ->get()
            ->map(fn ($driver) => [
                'id' => $driver->id,
                'name' => "{$driver->first_name} {$driver->last_name}",
                'score' => $driver->performance_score,
                'grade' => self::getPerformanceGrade($driver->performance_score),
                'incidents_30' => $driver->incidents_30,
            ])
            ->toArray();
    }

    public static function getUnderperformers(int $limit = 5): array
    {
        return Driver::where('status', 'active')
            ->orderBy('performance_score')
            ->take($limit)
            ->get()
            ->map(fn ($driver) => [
                'id' => $driver->id,
                'name' => "{$driver->first_name} {$driver->last_name}",
                'score' => $driver->performance_score,
                'grade' => self::getPerformanceGrade($driver->performance_score),
                'incidents_30' => $driver->incidents_30,
            ])
            ->toArray();
    }

    public static function recalculateAll(): int
    {
        $updatedCount = 0;

        foreach (Driver::all() as $driver) {
            self::recalculate($driver->id);
            $updatedCount++;
        }

        return $updatedCount;
    }

    public static function registerIncident(int $driverId, string $incidentType, string $description = ''): bool
    {
        $driver = Driver::find($driverId);
        if (!$driver) {
            return false;
        }

        $driver->update([
            'incidents_30' => ((int) $driver->incidents_30) + 1,
            'performance_score' => max(0, ((int) $driver->performance_score) - self::incidentPenalty()),
        ]);

        return true;
    }

    public static function clearExpiredIncidents(int $driverId): bool
    {
        $driver = Driver::find($driverId);
        if (!$driver) {
            return false;
        }

        $rollingDays = self::rollingDays();
        $start = Carbon::now('Asia/Manila')->subDays($rollingDays)->startOfDay();
        $end = Carbon::now('Asia/Manila')->endOfDay();

        $driver->update([
            'incidents_30' => self::incidentCountForPeriod($driverId, $start, $end),
        ]);

        self::recalculate($driverId);

        return true;
    }

    private static function scheduleWindowQuery(int $driverId, Carbon $start, Carbon $end)
    {
        return Schedule::where('driver_id', $driverId)
            ->where(function ($query) use ($start, $end) {
                $query->whereNull('service_date')
                    ->orWhere(function ($dated) use ($start, $end) {
                        $dated->whereDate('service_date', '>=', $start->toDateString())
                            ->whereDate('service_date', '<=', $end->toDateString());
                    });
            });
    }

    private static function incidentCountForPeriod(int $driverId, Carbon $start, Carbon $end): int
    {
        return Incident::where('driver_id', $driverId)
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    private static function incidentPenalty(): int
    {
        return (int) (
            SystemSetting::get('driver_score_incident_penalty')
            ?? SystemSetting::get('incident_score_penalty_per_event', 10)
        );
    }

    private static function delayPenalty(): int
    {
        return (int) SystemSetting::get('driver_score_delay_penalty', 5);
    }

    private static function rollingDays(): int
    {
        return (int) SystemSetting::get('driver_performance_rolling_days', 30);
    }

    private static function licenseCriticalDays(): int
    {
        return (int) SystemSetting::get('license_expiry_warn_critical_days', 7);
    }

    private static function licenseWarningDays(): int
    {
        return (int) SystemSetting::get('license_expiry_warning_threshold_days', 30);
    }
}
