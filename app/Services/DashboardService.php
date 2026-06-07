<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\Schedule;
use App\Models\ServiceAlert;
use App\Models\Trip;
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
            'delayed_buses' => Bus::where('status', 'active')->where('eta', '>=', Bus::DELAY_THRESHOLD)->count(),
            'passengers_today' => Schedule::sum('passengers'),
            'open_alerts' => ServiceAlert::where('status', 'active')->count(),
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

        // Active buses: ongoing trips
        $activeBusIds = Trip::where('status', 'ongoing')->pluck('bus_id')->toArray();
        $activeBuses  = count($activeBusIds);

        // Delayed buses: active buses with eta >= 10
        $delayedBuses = Bus::whereIn('id', $activeBusIds)->where('eta', '>=', 10)->count();

        // Offline buses: in maintenance status
        $offlineBuses = Bus::where('status', 'maintenance')->count();

        // Idle buses: inactive status
        $idleBuses = Bus::where('status', 'inactive')->count();

        // Trips completed today (fallback to all-time completed if none today)
        $tripsCompleted = Trip::where('status', 'completed')->whereDate('ended_at', $today)->count();
        if ($tripsCompleted === 0) {
            $tripsCompleted = Trip::where('status', 'completed')->count();
        }

        // Total passengers: sum of passengers on active buses
        $totalPassengers = Bus::whereIn('id', $activeBusIds)->sum('passengers');

        // Average utilization: (passengers / capacity) * 100
        $avgUtilization = 0;
        if ($activeBuses > 0) {
            $activeBusData = Bus::whereIn('id', $activeBusIds)->get();
            $totalUtil = $activeBusData->map(function ($b) {
                return $b->capacity > 0 ? ($b->passengers / $b->capacity) * 100 : 0;
            })->avg();
            $avgUtilization = (int) round($totalUtil);
        }

        // Open incidents: reported or under review
        $openIncidentCount = DB::table('incidents')
            ->whereIn('status', ['reported', 'under_review'])
            ->count();

        // Delta vs yesterday
        $activeBusIdsYesterday = Trip::where('status', 'ongoing')
            ->whereDate('started_at', Carbon::yesterday())
            ->pluck('bus_id')->toArray();
        $activeBusesYesterday = count($activeBusIdsYesterday);

        $tripsYesterday = Trip::where('status', 'completed')
            ->whereDate('ended_at', Carbon::yesterday())
            ->count();

        $activeDelta    = $activeBuses - $activeBusesYesterday;
        $tripsDelta     = $tripsCompleted - $tripsYesterday;

        return [
            'active_buses'    => $activeBuses,
            'delayed_buses'   => $delayedBuses,
            'offline_buses'   => $offlineBuses,
            'idle_buses'      => $idleBuses,
            'trips_completed' => $tripsCompleted,
            'total_passengers'=> $totalPassengers,
            'avg_utilization' => $avgUtilization,
            'open_incidents'  => $openIncidentCount,
            'deltas' => (object) [
                'active_buses_yesterday'      => ($activeDelta >= 0 ? '+' : '') . $activeDelta . ' vs yesterday',
                'delayed_buses_yesterday'     => '— today',
                'offline_buses_yesterday'     => '— in maintenance',
                'idle_buses_yesterday'        => '— standby',
                'trips_completed_yesterday'   => ($tripsDelta >= 0 ? '+' : '') . $tripsDelta . ' vs yesterday',
                'total_passengers_yesterday'  => $activeBuses > 0 ? 'on active buses' : 'no active buses',
                'avg_utilization_yesterday'   => $activeBuses > 0 ? 'of capacity used' : '—',
                'open_incidents_yesterday'    => $openIncidentCount > 0 ? 'needs attention' : 'all clear',
            ],
        ];
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

            // Calculate 30-day incidents count from real incident records
            $dbIncidents30 = DB::table('incidents')
                ->where('driver_id', $driver->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->count();
            $incidents30 = max((int)($driver->incidents_30 ?? 0), $dbIncidents30);

            // Calculate dynamic performance score: starting from 100%
            // Deduct 10 points for each incident in the last 30 days
            // Deduct 5 points for each delayed schedule in the last 30 days
            $delayedSchedules = DB::table('schedules')
                ->where('driver_id', $driver->id)
                ->where('status', 'delayed')
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            $calculatedScore = 100 - ($dbIncidents30 * 10) - ($delayedSchedules * 5);
            $performanceScore = max(0, min(100, $calculatedScore));

            // If no incidents and no delays, use the base profile score if it exists
            if ($dbIncidents30 === 0 && $delayedSchedules === 0) {
                $performanceScore = $driver->performance_score ?? 100;
            }
        }

        return (object)[
            'trips_today' => $tripsToday,
            'pax_today' => $paxToday,
            'performance_score' => $performanceScore,
            'incidents_30' => $incidents30,
        ];
    }
}
