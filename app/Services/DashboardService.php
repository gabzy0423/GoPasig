<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\ServiceAlert;
use App\Models\Trip;
use App\Models\TripPassengerEvent;
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
            'passengers_today' => Schedule::whereDate('service_date', Carbon::today('Asia/Manila'))->sum('passengers'),
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
        [$todayStartUtc, $todayEndUtc] = $this->manilaDayBoundsUtc($today);
        [$yesterdayStartUtc, $yesterdayEndUtc] = $this->manilaDayBoundsUtc($yesterday);
        $officialRouteIds = $this->officialRouteIds();

        // Active now means buses currently serving an ongoing official Trip.
        $activeBusIds = $this->activeOngoingTripBusIds($officialRouteIds);
        $activeBuses = count($activeBusIds);

        // Delayed buses: active-now buses whose ETA meets the configurable delay threshold.
        $delayThreshold = Bus::getDelayThreshold();
        $delayedBuses = Bus::whereIn('id', $activeBusIds)->where('eta', '>=', $delayThreshold)->count();

        // Unavailable buses: incident/maintenance states, not dispatch candidates.
        $offlineBuses = Bus::whereIn('status', [Bus::STATUS_MAINTENANCE, Bus::STATUS_BREAKDOWN])->count();

        // Standby buses: clean-state buses available or waiting for assignment/start.
        $idleBuses = Bus::whereIn('status', [Bus::STATUS_INACTIVE, 'available', 'ready'])->count();

        // Trips completed today - official Trips only, no fallback.
        $tripsCompleted = Trip::where('status', 'completed')
            ->whereIn('route_id', $officialRouteIds)
            ->whereBetween('ended_at', [$todayStartUtc, $todayEndUtc])
            ->count();

        // Riders today: accepted boarded passenger events on official routes.
        $totalPassengers = $this->boardedPassengerEventsForPeriod($officialRouteIds, $todayStartUtc, $todayEndUtc);

        // Average utilization: current onboard utilization for buses active right now.
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

        // Delta vs yesterday - use the same actual-operation rules on both days.
        $tripsYesterday = Trip::where('status', 'completed')
            ->whereIn('route_id', $officialRouteIds)
            ->whereBetween('ended_at', [$yesterdayStartUtc, $yesterdayEndUtc])
            ->count();

        $activeBusIdsYesterday = $this->operatedTripBusIdsForPeriod($officialRouteIds, $yesterdayStartUtc, $yesterdayEndUtc);
        $activeBusesYesterday = count($activeBusIdsYesterday);

        $delayedBusesYesterday = 0;
        $passengersYesterday = $this->boardedPassengerEventsForPeriod($officialRouteIds, $yesterdayStartUtc, $yesterdayEndUtc);

        $activeDelta = $activeBuses - $activeBusesYesterday;
        $tripsDelta = $tripsCompleted - $tripsYesterday;
        $delayedDelta = $delayedBuses - $delayedBusesYesterday;
        $passengerDelta = $totalPassengers - $passengersYesterday;

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
                'offline_buses_yesterday' => 'unavailable',
                'idle_buses_yesterday' => 'ready or standby',
                'trips_completed_yesterday' => ($tripsDelta >= 0 ? '+' : '') . $tripsDelta . ' vs yesterday',
                'total_passengers_yesterday' => ($passengerDelta >= 0 ? '+' : '') . $passengerDelta . ' vs yesterday',
                'avg_utilization_yesterday' => $activeBuses > 0 ? 'current onboard load' : 'no active buses',
                'open_incidents_yesterday' => $openIncidentCount > 0 ? 'needs attention' : 'all clear',
            ],
        ];
    }

    private function officialRouteIds(): array
    {
        return Route::publicCommuterActiveService()
            ->pluck('id')
            ->all();
    }

    private function manilaDayBoundsUtc(Carbon $day): array
    {
        return [
            $day->copy()->timezone('Asia/Manila')->startOfDay()->utc(),
            $day->copy()->timezone('Asia/Manila')->endOfDay()->utc(),
        ];
    }

    private function activeOngoingTripBusIds(array $officialRouteIds): array
    {
        if (empty($officialRouteIds)) {
            return [];
        }

        return Trip::query()
            ->where('status', 'ongoing')
            ->whereIn('route_id', $officialRouteIds)
            ->whereNotNull('bus_id')
            ->whereHas('bus', function ($query) {
                $query->whereIn('status', Bus::commuterServiceStatuses());
            })
            ->pluck('bus_id')
            ->unique()
            ->values()
            ->all();
    }

    private function operatedTripBusIdsForPeriod(array $officialRouteIds, Carbon $startUtc, Carbon $endUtc): array
    {
        if (empty($officialRouteIds)) {
            return [];
        }

        return Trip::whereIn('status', ['ongoing', 'completed'])
            ->whereIn('route_id', $officialRouteIds)
            ->whereNotNull('started_at')
            ->whereNotNull('bus_id')
            ->where('started_at', '<=', $endUtc)
            ->where(function ($query) use ($startUtc) {
                $query->whereNull('ended_at')
                    ->orWhere('ended_at', '>=', $startUtc);
            })
            ->pluck('bus_id')
            ->unique()
            ->values()
            ->toArray();
    }

    private function boardedPassengerEventsForPeriod(array $officialRouteIds, Carbon $startUtc, Carbon $endUtc): int
    {
        if (empty($officialRouteIds)) {
            return 0;
        }

        return (int) TripPassengerEvent::query()
            ->where('event_type', TripPassengerEvent::TYPE_BOARDED)
            ->whereIn('route_id', $officialRouteIds)
            ->whereBetween('recorded_at', [$startUtc, $endUtc])
            ->sum('passenger_delta');
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
            $paxToday = app(PassengerLoadService::class)->boardedTodayForDriver($driver);

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
