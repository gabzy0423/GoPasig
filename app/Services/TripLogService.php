<?php

namespace App\Services;

use App\Models\CommuterTrip;
use App\Models\Stop;
use App\Models\TripLog;
use App\Models\TripPassengerEvent;
use App\Models\Trip;
use App\Models\Driver;
use Carbon\Carbon;

class TripLogService
{
    /**
     * Log a completed trip to trip_logs table
     */
    public static function logTrip(Trip $trip, array $data = []): ?TripLog
    {
        try {
            return self::logTripOrFail($trip, $data);
        } catch (\Throwable $e) {
            // Log error but don't break trip completion
            \Log::error('Failed to log trip: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Persist a final TripLog and let failures roll back the lifecycle caller.
     */
    public static function logTripOrFail(Trip $trip, array $data = []): TripLog
    {
        if (! $trip->id) {
            throw new \InvalidArgumentException('A persisted Trip is required for TripLog finalization.');
        }

        $peakPassengers = (int) ($data['peak_passengers'] ?? $trip->peak_passengers ?? 0);
        $status = $data['status'] ?? (is_object($trip->status) ? $trip->status->value : $trip->status);
        $eventSummary = self::passengerEventSummary($trip->id);

        return TripLog::updateOrCreate([
            'trip_id' => $trip->id,
        ], [
            'driver_id' => $trip->driver_id,
            'bus_id' => $trip->bus_id,
            'route_id' => $trip->route_id,
            'started_at' => $data['started_at'] ?? $trip->started_at,
            'completed_at' => $data['completed_at'] ?? $trip->ended_at ?? now(),
            'passengers' => $data['passengers'] ?? $eventSummary['boarded'],
            'alighted_passengers' => $data['alighted_passengers'] ?? $eventSummary['alighted'],
            'peak_passengers' => $peakPassengers,
            'status' => $status,
            'is_on_time' => $data['is_on_time'] ?? true,
            'delay_minutes' => $data['delay_minutes'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    private static function passengerEventSummary(int $tripId): array
    {
        return [
            'boarded' => (int) TripPassengerEvent::where('trip_id', $tripId)
                ->where('event_type', TripPassengerEvent::TYPE_BOARDED)
                ->sum('passenger_delta'),
            'alighted' => (int) TripPassengerEvent::where('trip_id', $tripId)
                ->where('event_type', TripPassengerEvent::TYPE_ALIGHTED)
                ->sum('passenger_delta'),
        ];
    }

    /**
     * Get driver's trip history for dashboard
     */
    public static function getDriverTripHistory(Driver $driver, int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $tripLogs = TripLog::forDriver($driver->id)
            ->inDateRange($startDate, $endDate)
            ->with('route', 'bus')
            ->orderByDesc('completed_at')
            ->get();

        return [
            'total_trips' => $tripLogs->count(),
            'on_time_trips' => $tripLogs->where('is_on_time', true)->count(),
            'delayed_trips' => $tripLogs->where('is_on_time', false)->count(),
            'total_passengers' => $tripLogs->sum('passengers'),
            'avg_passengers_per_trip' => $tripLogs->count() > 0 ? (int) round($tripLogs->avg('passengers')) : 0,
            'on_time_rate' => $tripLogs->count() > 0 ? (int) round(($tripLogs->where('is_on_time', true)->count() / $tripLogs->count()) * 100) : 0,
            'trips' => $tripLogs->map(fn($log) => [
                'id' => $log->id,
                'trip_id' => $log->trip_id,
                'date' => $log->completed_at->format('Y-m-d'),
                'time' => $log->completed_at->format('H:i'),
                'route' => $log->route?->name ?? 'N/A',
                'bus' => $log->bus?->plate_number ?? 'N/A',
                'passengers' => $log->passengers,
                'status' => $log->status,
                'on_time' => $log->is_on_time,
                'delay_minutes' => $log->delay_minutes,
                'notes' => $log->notes,
            ])->toArray(),
        ];
    }

    /**
     * Get on-time performance statistics
     */
    public static function getOnTimeStats(Driver $driver, int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $tripLogs = TripLog::forDriver($driver->id)
            ->inDateRange($startDate, $endDate)
            ->get();

        $total = $tripLogs->count();
        if ($total === 0) {
            return [
                'on_time_rate' => 0,
                'total_trips' => 0,
                'on_time_trips' => 0,
                'delayed_trips' => 0,
                'avg_delay_minutes' => 0,
            ];
        }

        $onTimeTrips = $tripLogs->where('is_on_time', true)->count();
        $delayedTrips = $tripLogs->where('is_on_time', false)->count();
        $avgDelay = $delayedTrips > 0 ? (int) round($tripLogs->where('is_on_time', false)->avg('delay_minutes')) : 0;

        return [
            'on_time_rate' => (int) round(($onTimeTrips / $total) * 100),
            'total_trips' => $total,
            'on_time_trips' => $onTimeTrips,
            'delayed_trips' => $delayedTrips,
            'avg_delay_minutes' => $avgDelay,
        ];
    }

    /**
     * Get passenger statistics
     */
    public static function getPassengerStats(Driver $driver, int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $tripLogs = TripLog::forDriver($driver->id)
            ->inDateRange($startDate, $endDate)
            ->get();

        $total = $tripLogs->count();
        if ($total === 0) {
            return [
                'total_passengers' => 0,
                'avg_passengers_per_trip' => 0,
                'total_trips' => 0,
                'peak_passengers' => 0,
            ];
        }

        return [
            'total_passengers' => $tripLogs->sum('passengers'),
            'avg_passengers_per_trip' => (int) round($tripLogs->avg('passengers')),
            'total_trips' => $total,
            'peak_passengers' => $tripLogs->max('peak_passengers'),
        ];
    }

    /**
     * Get daily performance summary
     */
    public static function getDailyPerformance(Driver $driver, int $days = 7): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $tripLogs = TripLog::forDriver($driver->id)
            ->inDateRange($startDate, $endDate)
            ->get()
            ->groupBy(fn($log) => $log->completed_at->format('Y-m-d'));

        $performance = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayLogs = $tripLogs->get($date, collect());

            $performance[] = [
                'date' => $date,
                'trips' => $dayLogs->count(),
                'on_time' => $dayLogs->where('is_on_time', true)->count(),
                'delayed' => $dayLogs->where('is_on_time', false)->count(),
                'passengers' => $dayLogs->sum('passengers'),
            ];
        }

        return $performance;
    }

    /**
     * Get trips with delays for a specific date
     */
    public static function getDelayedTripsForDate(Driver $driver, string $date): array
    {
        $startDate = Carbon::parse($date)->startOfDay();
        $endDate = Carbon::parse($date)->endOfDay();

        $delayedTrips = TripLog::forDriver($driver->id)
            ->inDateRange($startDate, $endDate)
            ->delayed()
            ->with('route', 'bus')
            ->orderBy('delay_minutes', 'desc')
            ->get();

        return $delayedTrips->map(fn($log) => [
            'trip_id' => $log->trip_id,
            'route' => $log->route?->name,
            'time' => $log->completed_at->format('H:i'),
            'delay_minutes' => $log->delay_minutes,
            'passengers' => $log->passengers,
        ])->toArray();
    }

    /**
     * Migrate legacy trip_history JSON to TripLog records (one-time operation)
     */
    public static function migrateFromLegacyTripHistory(Driver $driver): int
    {
        $legacyData = $driver->trip_history ?? [];
        $count = 0;

        foreach ($legacyData as $trip) {
            try {
                TripLog::create([
                    'driver_id' => $driver->id,
                    'trip_id' => $trip['trip_id'] ?? null,
                    'bus_id' => $trip['bus_id'] ?? null,
                    'route_id' => $trip['route_id'] ?? null,
                    'completed_at' => $trip['completed_at'] ?? now(),
                    'passengers' => $trip['passengers'] ?? 0,
                    'status' => 'completed',
                ]);
                $count++;
            } catch (\Exception $e) {
                \Log::warning("Failed to migrate trip history for driver {$driver->id}: " . $e->getMessage());
            }
        }

        // Clear legacy data after migration
        $driver->update(['trip_history' => []]);

        return $count;
    }

    /**
     * Count boarded and alighted passengers from commuter trip records.
     */
    public static function computePassengerFlow(int $busId, int $routeId, string $date): array
    {
        $boarded = CommuterTrip::where('bus_id', $busId)
            ->where('route_id', $routeId)
            ->where('is_simulated', false)
            ->whereDate('created_at', $date)
            ->whereNotNull('boarded_at')
            ->count();

        $alighted = CommuterTrip::where('bus_id', $busId)
            ->where('route_id', $routeId)
            ->where('is_simulated', false)
            ->whereDate('created_at', $date)
            ->whereNotNull('arrived_at')
            ->count();

        return [
            'boarded' => $boarded,
            'alighted' => $alighted,
        ];
    }

    /**
     * Estimate peak onboard load using stop-by-stop boarding/alighting aggregation.
     */
    public static function computePeakLoad(int $busId, int $routeId, string $date): int
    {
        $flow = self::computePassengerFlow($busId, $routeId, $date);
        $routeStops = Stop::where('route_id', $routeId)->orderBy('sequence')->get();
        $currentLoad = 0;
        $maxLoad = 0;

        foreach ($routeStops as $stop) {
            $stopBoarded = CommuterTrip::where('bus_id', $busId)
                ->where('route_id', $routeId)
                ->where('is_simulated', false)
                ->where('origin_stop_id', $stop->id)
                ->whereDate('created_at', $date)
                ->whereNotNull('boarded_at')
                ->count();

            $stopAlighted = CommuterTrip::where('bus_id', $busId)
                ->where('route_id', $routeId)
                ->where('is_simulated', false)
                ->where('destination_stop_id', $stop->id)
                ->whereDate('created_at', $date)
                ->whereNotNull('arrived_at')
                ->count();

            $currentLoad += ($stopBoarded - $stopAlighted);
            if ($currentLoad > $maxLoad) {
                $maxLoad = $currentLoad;
            }
        }

        return max($maxLoad, $flow['boarded']);
    }
}
