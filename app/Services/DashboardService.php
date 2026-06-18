<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\Schedule;
use App\Models\ServiceAlert;
use App\Models\Trip;
use App\Services\DriverPerformanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get statistics for commuter dashboard.
     *
     * @return array
     */
    public function getCommuterStats(): array
    {
        return [
            'active_buses' => Bus::where('status', 'active')->count(),
            'delayed_buses' => Bus::with('route')->where('status', 'active')->get()->filter(function ($bus) {
                return $bus->eta >= $bus->getRouteDelayThreshold();
            })->count(),
            'passengers_today' => Schedule::sum('passengers'),
            'open_alerts' => ServiceAlert::activeAlerts()->count(),
        ];
    }

    /**
     * Get overview KPI statistics for fleet operator dashboard.
     *
     * @return array
     */
    public function getFleetOverviewKpi(): array
    {
        $today = Carbon::today('Asia/Manila');
        $yesterday = $today->copy()->subDay();

        // Active buses: buses with trip activity during the Manila service day.
        $activeBusIds = $this->activeTripBusIdsForDay($today);
        $activeBuses = count($activeBusIds);

        // Delayed buses: active buses whose ETA meets the configurable delay threshold.
        $delayThreshold = Bus::getDelayThreshold();
        $delayedBuses = Bus::whereIn('id', $activeBusIds)->where('eta', '>=', $delayThreshold)->count();

        // Offline buses: in maintenance status.
        $offlineBuses = Bus::where('status', 'maintenance')->count();

        // Idle buses: inactive status.
        $idleBuses = Bus::where('status', 'inactive')->count();

        // Trips completed today - no fallback; a real 0-trip day should show 0.
        $tripsCompleted = Trip::where('status', 'completed')->whereDate('ended_at', $today)->count();

        // Total passengers: sum of passengers on active buses.
        $totalPassengers = Bus::whereIn('id', $activeBusIds)->sum('passengers');

        // Average utilization: (passengers / capacity) * 100.
        $avgUtilization = 0;
        if ($activeBuses > 0) {
            $activeBusData = Bus::whereIn('id', $activeBusIds)->get();
            $totalUtil = $activeBusData->map(function ($b) {
                return $b->capacity > 0 ? ($b->passengers / $b->capacity) * 100 : 0;
            })->avg();
            $avgUtilization = (int) round($totalUtil);
        }

        // Open incidents: reported or under review.
        $openIncidentCount = DB::table('incidents')
            ->whereIn('status', ['reported', 'under_review'])
            ->count();

        // Delta vs yesterday - use completed trips (ended_at) for consistency on both days.
        $tripsYesterday = Trip::where('status', 'completed')
            ->whereDate('ended_at', $yesterday)
            ->count();

        // Active buses yesterday: same trip activity rule used for today's count.
        $activeBusIdsYesterday = $this->activeTripBusIdsForDay($yesterday);
        $activeBusesYesterday = count($activeBusIdsYesterday);

        // Delayed buses yesterday: active buses yesterday whose ETA met the delay threshold.
        $delayedBusesYesterday = Bus::whereIn('id', $activeBusIdsYesterday)
            ->where('eta', '>=', $delayThreshold)
            ->count();

        $activeDelta = $activeBuses - $activeBusesYesterday;
        $tripsDelta = $tripsCompleted - $tripsYesterday;
        $delayedDelta = $delayedBuses - $delayedBusesYesterday;

        return [
            'active_buses' => $activeBuses,
            'delayed_buses' => $delayedBuses,
            'offline_buses' => $offlineBuses,
            'idle_buses' => $idleBuses,
            'trips_completed' => $tripsCompleted,
            'total_passengers' => $totalPassengers,
            'avg_utilization' => $avgUtilization,
            'open_incidents' => $openIncidentCount,
            'deltas' => (object) [
                'active_buses_yesterday' => ($activeDelta >= 0 ? '+' : '') . $activeDelta . ' vs yesterday',
                'delayed_buses_yesterday' => ($delayedDelta >= 0 ? '+' : '') . $delayedDelta . ' vs yesterday',
                'offline_buses_yesterday' => '- in maintenance',
                'idle_buses_yesterday' => '- standby',
                'trips_completed_yesterday' => ($tripsDelta >= 0 ? '+' : '') . $tripsDelta . ' vs yesterday',
                'total_passengers_yesterday' => $activeBuses > 0 ? 'on active buses' : 'no active buses',
                'avg_utilization_yesterday' => $activeBuses > 0 ? 'of capacity used' : '-',
                'open_incidents_yesterday' => $openIncidentCount > 0 ? 'needs attention' : 'all clear',
            ],
        ];
    }

    /**
     * Get bus IDs with trips that overlap the given Manila service day.
     */
    private function activeTripBusIdsForDay(Carbon $day): array
    {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        return Trip::whereIn('status', ['ongoing', 'completed'])
            ->whereNotNull('started_at')
            ->where('started_at', '<=', $end)
            ->where(function ($query) use ($start) {
                $query->whereNull('ended_at')
                    ->orWhere('ended_at', '>=', $start);
            })
            ->pluck('bus_id')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get stats for driver dashboard.
     *
     * @param mixed $driver
     * @return object
     */
    public function getDriverStats($driver): object
    {
        $tripsToday = 0;
        $paxToday = 0;
        $performanceScore = 100;
        $incidents30 = 0;

        if ($driver) {
            $tripsToday = $driver->trips_today;
            $paxToday = $driver->pax_today;

            // Calculate 30-day incidents count from real incident records.
            $dbIncidents30 = DB::table('incidents')
                ->where('driver_id', $driver->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->count();
            $incidents30 = max((int) ($driver->incidents_30 ?? 0), $dbIncidents30);

            // Delegate to the shared scoring service (last 30 days).
            $start30 = Carbon::now()->subDays(30)->startOfDay();
            $end30 = Carbon::now()->endOfDay();
            $performanceScore = DriverPerformanceService::calculateScore(
                $driver->id,
                $start30,
                $end30,
                $driver->performance_score
            );
        }

        return (object) [
            'trips_today' => $tripsToday,
            'pax_today' => $paxToday,
            'performance_score' => $performanceScore,
            'incidents_30' => $incidents30,
        ];
    }
}
