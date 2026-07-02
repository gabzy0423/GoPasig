<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Route;
use App\Models\Stop;
use App\Models\CommuterTrip;
use App\Models\TripLog;
use App\Models\SystemSetting;
use App\Models\DemandHistory;
use App\Models\TimeSlotConfiguration;
use App\Models\Terminal;
use App\Services\TripLogService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Fetch all dashboard analytics data dynamically.
     */
    public function index(Request $request)
    {
        // Parse date range parameters
        $startDate = $request->query('start') ? Carbon::parse($request->query('start')) : Carbon::today();
        $endDate = $request->query('end') ? Carbon::parse($request->query('end')) : Carbon::today();

        // If no explicit date range, default based on system setting
        if (!$request->has('start') && !$request->has('end')) {
            $dateRange = SystemSetting::get('analytics_default_date_range', 'today');

            switch ($dateRange) {
                case 'yesterday':
                    $rangeStart = Carbon::yesterday();
                    $rangeEnd = Carbon::yesterday();
                    break;
                case 'week':
                    $rangeStart = Carbon::now()->startOfWeek();
                    $rangeEnd = Carbon::now()->endOfWeek();
                    break;
                case 'month':
                    $rangeStart = Carbon::now()->startOfMonth();
                    $rangeEnd = Carbon::now()->endOfMonth();
                    break;
                case 'today':
                default:
                    $rangeStart = Carbon::today();
                    $rangeEnd = Carbon::today();
                    break;
            }
        } else {
            // Custom range
            $rangeStart = $startDate;
            $rangeEnd = $endDate;
        }

        // Fetch bus capacity limit from SystemSetting (not hardcoded)
        $busCapacityLimit = (int) SystemSetting::get('default_bus_capacity', 45);

        // 1. KPI Metrics
        $todaySchedules = Schedule::whereBetween('service_date', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()]);

        $totalPaxToday = $todaySchedules->sum('passengers');
        $avgPaxTrip = round($todaySchedules->avg('passengers'), 1) ?: 0;

        $totalBuses = Bus::count();
        $activeBusesCount = Bus::where('status', 'active')->count();
        $fleetUtil = $totalBuses > 0 ? round(($activeBusesCount / $totalBuses) * 100) : 0;

        $onTimeCount = Schedule::whereBetween('service_date', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])->where('status', Schedule::STATUS_ON_TIME)->count();
        $totalSchedules = Schedule::whereBetween('service_date', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])->count();
        $onTimeRate = $totalSchedules > 0 ? round(($onTimeCount / $totalSchedules) * 100) : 100;
        $delayedCount = Schedule::whereBetween('service_date', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])->where('status', Schedule::STATUS_DELAYED)->count();

        $tripsCompleted = Trip::where('status', 'completed')->whereBetween('created_at', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])->count();

        // Running weekly total (prevent double-counting today's passengers)
        $startOfWeek = Carbon::now()->startOfWeek()->toDateString();
        $endOfWeek = Carbon::now()->endOfWeek()->toDateString();
        $todayDateStr = Carbon::today()->toDateString();
        $historicalWeeklyPax = DemandHistory::whereBetween('date', [$startOfWeek, $endOfWeek])
            ->whereDate('date', '!=', $todayDateStr)
            ->sum('total_commuters');
        $paxThisWeek = $historicalWeeklyPax + $totalPaxToday;
        $insufficientWeeklyData = false;
        // Flag when we don't have enough historical data
        if ($paxThisWeek === 0 && $totalPaxToday === 0) {
            $insufficientWeeklyData = true;
        }

        // Calculate yesterday's metrics dynamically
        $totalPaxYesterday = Schedule::whereDate('service_date', Carbon::yesterday())->sum('passengers');
        // No synthetic fallback: report 0 when there is no historical data.
        $diffPct = $totalPaxYesterday > 0 ? round((($totalPaxToday - $totalPaxYesterday) / $totalPaxYesterday) * 100) : 0;
        $paxChangeYesterday = ($diffPct >= 0 ? '+' : '') . $diffPct . '% vs yesterday';

        // Calculate last week's metrics dynamically
        $startOfLastWeek = Carbon::now()->subWeek()->startOfWeek()->toDateString();
        $endOfLastWeek = Carbon::now()->subWeek()->endOfWeek()->toDateString();
        $paxLastWeek = DemandHistory::whereBetween('date', [$startOfLastWeek, $endOfLastWeek])->sum('total_commuters');
        // No synthetic fallback: report 0 when there is no historical data.
        $weekDiffPct = $paxLastWeek > 0 ? round((($paxThisWeek - $paxLastWeek) / $paxLastWeek) * 100) : 0;
        $paxChangeLastWeek = ($weekDiffPct >= 0 ? '+' : '') . $weekDiffPct . '% vs last week';

        // Average passengers per trip change
        $avgPaxYesterday = (float) Schedule::whereDate('service_date', Carbon::yesterday())->avg('passengers');
        // No synthetic fallback: report 0 when there is no historical data.
        $avgDiffPct = $avgPaxYesterday > 0 ? round((($avgPaxTrip - $avgPaxYesterday) / $avgPaxYesterday) * 100) : 0;
        $avgPaxTripChange = ($avgDiffPct >= 0 ? '+' : '') . $avgDiffPct . '% vs yesterday';

        $kpis = [
            'total_pax_today' => number_format($totalPaxToday),
            'pax_change_yesterday' => $paxChangeYesterday,
            'pax_this_week' => number_format($paxThisWeek),
            'pax_change_last_week' => $paxChangeLastWeek,
            'avg_pax_trip' => $avgPaxTrip,
            'avg_pax_trip_change' => $avgPaxTripChange,
            'trips_completed' => $tripsCompleted,
            'trips_scheduled' => $totalSchedules,
            'fleet_util' => $fleetUtil,
            'active_buses' => $activeBusesCount,
            'total_buses' => $totalBuses,
            'on_time_rate' => $onTimeRate,
            'delayed_trips' => $delayedCount,
            'insufficient_data' => $insufficientWeeklyData,
        ];

        // 2. Hourly Ridership by configured time slots
        $hourlyRidership = [];
        $routes = Route::getAllCached();
        $timeSlotConfigs = TimeSlotConfiguration::where('is_active', true)->orderBy('order')->get();

        if ($timeSlotConfigs->isEmpty()) {
            \Illuminate\Support\Facades\Log::error('TimeSlotConfiguration table is empty. Admin hourly ridership charts will not be rendered. Run time slot configuration seeder.');
            $timeSlotConfigs = collect();
        }

        foreach ($timeSlotConfigs as $slotConfig) {
            $hourlyData = [
                'hour' => $slotConfig->time_slot_display,
            ];

            foreach ($routes as $route) {
                $sum = Schedule::where('route_id', $route->id)
                    ->whereBetween('service_date', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
                    ->where('departure_time', '>=', $slotConfig->start_time)
                    ->where('departure_time', '<', $slotConfig->end_time)
                    ->sum('passengers');

                $hourlyData[$route->name] = (int) $sum;
            }
            $hourlyRidership[] = $hourlyData;
        }

        $defaultTerminalName = SystemSetting::get('default_terminal_name', Terminal::getDefaultName());

        // 3. Passengers by Route Today (Doughnut Chart & Comparison Table)
        $routeComparison = [];
        foreach ($routes as $route) {

            $tripsCount = Schedule::where('route_id', $route->id)
                ->whereBetween('service_date', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
                ->count();
            $paxSum = Schedule::where('route_id', $route->id)
                ->whereBetween('service_date', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
                ->sum('passengers');
            $avgPax = $tripsCount > 0 ? round($paxSum / $tripsCount, 1) : 0;

            // Find peak hour today
            $driverName = DB::getDriverName();
            $hourExpr = $driverName === 'sqlite' ? "strftime('%H', departure_time)" : "HOUR(departure_time)";

            $peakHourRecord = Schedule::where('route_id', $route->id)
                ->whereBetween('service_date', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
                ->selectRaw("{$hourExpr} as hr, SUM(passengers) as total")
                ->groupBy('hr')
                ->orderBy('total', 'desc')
                ->first();
            $peakHourStr = 'No data'; // Default: no data — overridden below when real schedule data exists
            if ($peakHourRecord) {
                $hr = (int) $peakHourRecord->hr;
                $slotConfig = TimeSlotConfiguration::getTimeSlotByHour($hr);
                $peakHourStr = $slotConfig
                    ? $slotConfig->time_slot_display
                    : ($hr < 12 ? "{$hr}–" . ($hr + 1) . " AM" : ($hr === 12 ? "12–1 PM" : ($hr - 12) . "–" . ($hr - 11) . " PM"));
            }

            // Find busiest stop from commuter_trips table origin counts
            $busiestStopRecord = DB::table('commuter_trips')
                ->where('route_id', $route->id)
                ->select('origin_stop_id', DB::raw('count(*) as count'))
                ->groupBy('origin_stop_id')
                ->orderByDesc('count')
                ->first();

            $busiestStopName = $defaultTerminalName;
            if ($busiestStopRecord) {
                $stop = Stop::find($busiestStopRecord->origin_stop_id);
                if ($stop) {
                    $busiestStopName = $stop->name;
                }
            } else {
                $firstStop = Stop::where('route_id', $route->id)->orderBy('sequence')->first();
                if ($firstStop) {
                    $busiestStopName = $firstStop->name;
                }
            }

            $routeComparison[] = [
                'route' => $route->name,
                'color' => $route->color,
                'trips' => $tripsCount,
                'pax' => $paxSum,
                'avgPax' => $avgPax,
                'peakHour' => $peakHourStr,
                'busiestStop' => $busiestStopName,
                'percentage' => $totalPaxToday > 0 ? round(($paxSum / $totalPaxToday) * 100) : 0,
            ];
        }

        // 4. Heatmap Patterns (last 7 days average matrix)
        $heatmap = [];
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $timeSlotConfigs = TimeSlotConfiguration::where('is_active', true)->orderBy('order')->get();
        if ($timeSlotConfigs->isEmpty()) {
            \Illuminate\Support\Facades\Log::error('TimeSlotConfiguration table is empty. Admin heatmap patterns will not be rendered. Run time slot configuration seeder.');
            $timeSlotConfigs = collect();
        }
        $defaultTimeSlot = SystemSetting::get('default_time_slot', $timeSlotConfigs->first()?->time_slot_display ?? '06:00-08:00');
        $defaultTerminalName = SystemSetting::get('default_terminal_name', Terminal::getDefaultName());

        foreach ($daysOfWeek as $dayName) {
            $dayRow = [];
            foreach ($timeSlotConfigs as $slotConfig) {
                $dbTimeSlot = $slotConfig->time_slot_display;

                $avg = DemandHistory::where('day_of_week', $dayName)
                    ->where('time_slot', $dbTimeSlot)
                    ->avg('total_commuters');

                if ($avg !== null) {
                    $paxValue = round($avg);
                } else {
                    // No data for this slot — report 0 instead of a synthetic waveform.
                    $paxValue = 0;
                }

                $dayRow[] = $paxValue;
            }
            $heatmap[$dayName] = $dayRow;
        }

        $topStopsLimit = (int) SystemSetting::get('analytics_top_stops_limit', (int) SystemSetting::get('analytics_top_stops_count', 10));

        // 5. Stop Boarding Horizontal Bars (Top Stops Flow)
        $stopBoarding = [];
        $allStops = Stop::all();
        foreach ($allStops as $stop) {
            $boarding = CommuterTrip::where('origin_stop_id', $stop->id)->where('is_simulated', false)->count();
            $alighting = CommuterTrip::where('destination_stop_id', $stop->id)->where('is_simulated', false)->count();

            $stopBoarding[] = [
                'name' => $stop->name,
                'boarding' => $boarding,
                'alighting' => $alighting,
                'net' => $boarding - $alighting,
            ];
        }

        // Sort descending by boarding
        usort($stopBoarding, fn($a, $b) => $b['boarding'] <=> $a['boarding']);
        $stopBoarding = array_slice($stopBoarding, 0, $topStopsLimit);

        // 6. Trip Pax Table Details (Schedules today)
        $tripPaxTable = [];
        $schedules = Schedule::with(['bus', 'driver', 'route'])
            ->whereBetween('service_date', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
            ->get();
        foreach ($schedules as $s) {
            $capacity = $s->bus ? $s->bus->capacity : Bus::getDefaultCapacity();
            $capacityPct = $capacity > 0 ? round(($s->passengers / $capacity) * 100) : 0;

            $scheduleDate = Carbon::parse($s->service_date)->toDateString();
            $busId = $s->bus_id;

            $boarded = 0;
            $alighted = 0;
            $peakLoad = 0;

            $tripLog = ($s->bus_id && $s->driver_id)
                ? TripLog::where('bus_id', $s->bus_id)
                    ->where('route_id', $s->route_id)
                    ->where('driver_id', $s->driver_id)
                    ->whereDate('completed_at', $scheduleDate)
                    ->first()
                : null;

            if ($tripLog) {
                $boarded = (int) $tripLog->passengers;
                $alighted = (int) $tripLog->alighted_passengers;
                $peakLoad = (int) $tripLog->peak_passengers;
            } elseif ($busId) {
                $flow = TripLogService::computePassengerFlow($busId, $s->route_id, $scheduleDate);
                $boarded = $flow['boarded'];
                $alighted = $flow['alighted'];
                $peakLoad = TripLogService::computePeakLoad($busId, $s->route_id, $scheduleDate);
            }

            $tripPaxTable[] = [
                'tripNo' => 'T-' . str_pad($s->id, 3, '0', STR_PAD_LEFT),
                'plate' => $s->bus ? $s->bus->plate_number : 'PAS-000',
                'driver' => $s->driver ? ($s->driver->first_name . ' ' . $s->driver->last_name) : 'Unassigned',
                'route' => $s->route ? $s->route->name : 'N/A',
                'depTime' => Carbon::parse($s->departure_time)->format('g:i A'),
                'arrTime' => Carbon::parse($s->arrival_time)->format('g:i A'),
                'boarded' => $boarded,
                'alighted' => $alighted,
                'peakLoad' => $peakLoad,
                'capacity' => $capacityPct,
            ];
        }

        // 7. Bus Ridership Summary Cards
        $busSummaryCards = [];
        $buses = Bus::all();
        foreach ($buses as $bus) {
            $busSchedules = Schedule::where('bus_id', $bus->id)
                ->whereBetween('service_date', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
                ->get();
            $tripsCount = $busSchedules->count();
            $busPaxSum = $busSchedules->sum('passengers');
            $busPeak = $busSchedules->max('passengers') ?: 0;
            $busAvg = $tripsCount > 0 ? round($busPaxSum / $tripsCount) : 0;

            $avgCapPct = $bus->capacity > 0 ? round(($busAvg / $bus->capacity) * 100) : 0;

            $busSummaryCards[] = [
                'plate' => $bus->plate_number,
                'status' => ucfirst($bus->status),
                'trips' => $tripsCount,
                'totalPax' => $busPaxSum,
                'avgPax' => $busAvg,
                'peakLoad' => $busPeak,
                'avgCapacity' => $avgCapPct,
                'driver' => $bus->driver_name ?: 'Unassigned',
            ];
        }

        // 8. Tomorrow's Dispatch Prediction Forecast
        $forecastTable = [];
        $dayTomorrow = Carbon::tomorrow()->format('l');

        foreach ($timeSlotConfigs as $slotConfig) {
            $dbTimeSlot = $slotConfig->time_slot_display;
            $hourInt = (int) substr($slotConfig->start_time, 0, 2);

            $histAvg = DemandHistory::where('day_of_week', $dayTomorrow)
                ->where('time_slot', $dbTimeSlot)
                ->avg('total_commuters');

            if ($histAvg !== null) {
                $predPax = round($histAvg);
            } else {
                // No historical demand data — report 0 instead of a synthetic forecast.
                $predPax = 0;
            }

            // Recommended buses based on predicted passenger load (using SystemSetting for capacity)
            $recBuses = (int) ceil($predPax / $busCapacityLimit);

            // Count actual scheduled trips in this time slot template tomorrow
            $schedBuses = Schedule::whereDate('service_date', Carbon::tomorrow()->toDateString())
                ->where('departure_time', '>=', $slotConfig->start_time)
                ->where('departure_time', '<', $slotConfig->end_time)
                ->count();

            $gap = max(0, $recBuses - $schedBuses);

            $action = 'Covered — no action needed';
            if ($gap === 1) {
                $action = 'Add 1 bus — shortage expected';
            } else if ($gap >= 2) {
                $action = 'Add ' . $gap . ' buses — peak hour deficit';
            }

            $forecastTable[] = [
                'slot' => $slotConfig->time_slot_display,
                'predPax' => $predPax,
                'recBuses' => $recBuses,
                'schedBuses' => $schedBuses,
                'gap' => $gap,
                'action' => $action,
            ];
        }

        // 9. Driver Performance Table
        $driverPerformance = [];
        $driversLimit = (int) SystemSetting::get('analytics_top_drivers_limit', 5);
        $drivers = Driver::orderBy('performance_score', 'desc')->take($driversLimit)->get();
        foreach ($drivers as $index => $driver) {
            $tripsCount = Schedule::where('driver_id', $driver->id)
                ->whereBetween('service_date', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
                ->count();

            $paxSum = Schedule::where('driver_id', $driver->id)
                ->whereBetween('service_date', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
                ->sum('passengers');

            $peakLoad = DB::table('trips')
                ->where('driver_id', $driver->id)
                ->whereBetween('created_at', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
                ->max('peak_passengers') ?: 0;

            $driverPerformance[] = [
                'rank' => '#' . ($index + 1),
                'name' => "{$driver->first_name} {$driver->last_name}",
                'bus' => $driver->assigned_bus ?: 'PAS-000',
                'route' => $driver->assigned_route ? ($routes->firstWhere('id', (int) $driver->assigned_route)?->name ?? 'N/A') : 'N/A',
                'trips' => $tripsCount,
                'pax' => $paxSum,
                'avgPax' => $tripsCount > 0 ? round($paxSum / $tripsCount, 1) : 0,
                'peakLoad' => $peakLoad,
                'incidents' => $driver->incidents_30,
            ];
        }

        // 10. Historical Ridership (Last 30 Days or dynamic range)
        $historicalTrend = [];
        $trendStart = $rangeStart;
        $trendEnd = $rangeEnd;
        if ($trendStart->toDateString() === $trendEnd->toDateString()) {
            $trendLimit = (int) SystemSetting::get('analytics_historical_trend_limit', 30);
            $trendStart = $trendEnd->copy()->subDays($trendLimit - 1);
        }

        $trendData = DemandHistory::select('date')
            ->whereBetween('date', [$trendStart->toDateString(), $trendEnd->toDateString()])
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        if ($trendData->isEmpty()) {
            // No historical data — return an empty trend rather than a synthetic waveform.
            $historicalTrend = [];
        } else {
            foreach ($trendData as $trend) {
                $dateStr = $trend->date->toDateString();
                $dateObj = Carbon::parse($trend->date);

                $total = 0;
                $dataRow = [
                    'label' => $dateObj->format('M d'),
                ];

                foreach ($routes as $route) {
                    $pax = (int) DemandHistory::where('date', $dateStr)->where('route_id', $route->id)->sum('total_commuters');
                    $dataRow[$route->name] = $pax;
                    $total += $pax;
                }

                $dataRow['total'] = $total;
                $historicalTrend[] = $dataRow;
            }
        }

        // Return combined JSON response
        return response()->json([
            'success' => true,
            'kpis' => $kpis,
            'hourlyRidership' => $hourlyRidership,
            'routeComparison' => $routeComparison,
            'heatmap' => $heatmap,
            'stopBoarding' => $stopBoarding,
            'tripPaxTable' => $tripPaxTable,
            'busSummaryCards' => $busSummaryCards,
            'forecastTable' => $forecastTable,
            'driverPerformance' => $driverPerformance,
            'historicalTrend' => $historicalTrend,
            'busCapacityLimit' => $busCapacityLimit,
        ]);
    }
}
