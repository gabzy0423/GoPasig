<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Route;
use App\Models\Bus;
use App\Models\Schedule;
use App\Models\Trip;
use App\Models\SystemSetting;
use App\Models\ColorPalette;
use App\Models\TimeSlotConfiguration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Display the Analytics & Reports view.
     */
    public function index(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::today()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::today()->toDateString());
        $selectedRoute = $request->input('route_id', 'all');
        $reportType = $request->input('report_type', 'daily');

        $availableRoutes = Route::orderBy('id')->get(['id', 'name'])->toArray();

        // Fetch initial data to render server-side
        $analyticsData = $this->fetchSummaryData($startDate, $endDate, $selectedRoute);

        return view('fleet.analytics.index', array_merge([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedRoute' => $selectedRoute,
            'reportType' => $reportType,
            'availableRoutes' => $availableRoutes,
            'lastUpdatedTime' => now()->format('g:i A'),
        ], $analyticsData));
    }

    /**
     * API Endpoint to fetch analytics data via JSON.
     */
    public function getAnalyticsData(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::today()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::today()->toDateString());
        $selectedRoute = $request->input('route_id', 'all');

        $data = $this->fetchSummaryData($startDate, $endDate, $selectedRoute);
        $data['lastUpdatedTime'] = now()->format('g:i A');

        return response()->json($data);
    }

    /**
     * API Endpoint to trigger CSV download.
     */
    public function exportCsv(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::today()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::today()->toDateString());
        $selectedRoute = $request->input('route_id', 'all');

        $data = $this->fetchSummaryData($startDate, $endDate, $selectedRoute);
        $busLogs = $data['busLogs'];
        $metric = $data['metricSummary'];

        $filename = 'analytics-report-' . $startDate . '-to-' . $endDate . '.csv';

        $rows = [];
        $rows[] = ['GoPasig Analytics Report'];
        $rows[] = ['Period', $startDate . ' to ' . $endDate];
        $rows[] = ['Generated At', now()->format('Y-m-d H:i:s')];
        $rows[] = [];

        // Metric summary
        $rows[] = ['=== SUMMARY METRICS ==='];
        $rows[] = ['Total Passengers', $metric->total_passengers];
        $rows[] = ['Trips Completed', $metric->trips_completed];
        $rows[] = ['Avg Passengers/Trip', $metric->avg_per_trip];
        $rows[] = ['Fleet Utilization', $metric->utilization_rate];
        $rows[] = ['Busiest Route', $metric->busiest_route];
        $rows[] = ['Peak Hour', $metric->peak_hour];
        $rows[] = [];

        // Route summary
        $rows[] = ['=== ROUTE SUMMARY ==='];
        $rows[] = ['Route Name', 'Total Passengers'];
        foreach ($data['routeSummary'] as $r) {
            $rows[] = [$r->route_name, $r->total_passengers];
        }
        $rows[] = [];

        // Bus utilization log
        $rows[] = ['=== BUS UTILIZATION LOG ==='];
        $rows[] = ['Bus ID / Plate', 'Assigned Route', 'Trips Done', 'Passengers', 'Capacity', 'Utilization %', 'Status'];
        foreach ($busLogs as $b) {
            $rows[] = [
                $b->bus_id,
                $b->assigned_route,
                $b->trips_completed,
                $b->total_passengers,
                $b->capacity,
                $b->utilization_rate . '%',
                $b->status,
            ];
        }

        $csvContent = implode("\n", array_map(function ($row) {
            return implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row));
        }, $rows));

        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Compute analytics summary data from DB.
     */
    public function fetchSummaryData($startDate, $endDate, $selectedRoute): array
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $colorPalette = ColorPalette::getColors('analytics');

        $routeQuery = Route::orderBy('id');
        if ($selectedRoute !== 'all') {
            $routeQuery->where('id', $selectedRoute);
        }
        $routes = $routeQuery->get();

        $baseQuery = fn() => Schedule::whereBetween('service_date', [$start->toDateString(), $end->toDateString()]);
        $countInRange = $baseQuery()->count();
        $useAllTime = $countInRange === 0;
        $effectiveQuery = fn() => $useAllTime ? Schedule::query() : Schedule::whereBetween('service_date', [$start->toDateString(), $end->toDateString()]);

        $routeFilteredQuery = fn() => $selectedRoute !== 'all'
            ? $effectiveQuery()->where('route_id', $selectedRoute)
            : $effectiveQuery();

        // 1. Metric Summary
        $totalPassengers = (int) $routeFilteredQuery()->sum('passengers');
        $tripsCompleted = (int) $routeFilteredQuery()->count();
        $avgPerTrip = $tripsCompleted > 0 ? round($totalPassengers / $tripsCompleted, 1) : 0;

        $totalBuses = Bus::count();
        $activeBusCount = Bus::where('status', 'active')->count();
        $fleetUtil = $totalBuses > 0 ? round(($activeBusCount / $totalBuses) * 100) : 0;

        $busiestRoute = $effectiveQuery()
            ->select('route_id', DB::raw('SUM(passengers) as total'))
            ->groupBy('route_id')
            ->orderByDesc('total')
            ->with('route:id,name')
            ->first();

        $busiestRouteName = $busiestRoute && $busiestRoute->route
            ? $busiestRoute->route->name
            : ($routes->isNotEmpty() ? $routes->first()->name : 'N/A');
        $busiestRouteCount = $busiestRoute ? number_format((int) $busiestRoute->total) : '0';

        $peakHourRecord = $routeFilteredQuery()
            ->selectRaw($isSqlite ? "CAST(strftime('%H', departure_time) AS INTEGER) as hr, SUM(passengers) as total" : "HOUR(departure_time) as hr, SUM(passengers) as total")
            ->groupBy('hr')
            ->orderByDesc('total')
            ->first();

        if ($peakHourRecord) {
            $hr = (int) $peakHourRecord->hr;
            $config = TimeSlotConfiguration::getTimeSlotByHour($hr);
            $peakHour = $config
                ? $config->time_slot_display
                : ($hr < 12
                    ? "{$hr}:00 – " . ($hr + 1) . ":00 AM"
                    : ($hr === 12 ? "12:00 – 1:00 PM" : ($hr - 12) . ":00 – " . ($hr - 11) . ":00 PM"));
        } else {
            $peakHour = 'N/A';
        }

        $metricSummary = (object) [
            'total_passengers' => number_format($totalPassengers),
            'trips_completed' => $tripsCompleted,
            'avg_per_trip' => $avgPerTrip,
            'utilization_rate' => $fleetUtil . '%',
            'busiest_route' => $busiestRouteName,
            'busiest_route_count' => $busiestRouteCount,
            'peak_hour' => $peakHour,
        ];

        // 2. Route Summary
        $routeSummary = [];
        foreach ($routes as $idx => $route) {
            $pax = (int) $effectiveQuery()->where('route_id', $route->id)->sum('passengers');
            $routeSummary[] = (object) [
                'route_name' => $route->name,
                'total_passengers' => $pax,
                'color' => $colorPalette[$idx % count($colorPalette)],
            ];
        }
        usort($routeSummary, fn($a, $b) => $b->total_passengers <=> $a->total_passengers);

        // 3. Hourly Ridership by configured time slots
        $hourlyRidership = [];
        $timeSlotConfigs = TimeSlotConfiguration::where('is_active', true)->orderBy('order')->get();

        if ($timeSlotConfigs->isEmpty()) {
            \Illuminate\Support\Facades\Log::error('TimeSlotConfiguration table is empty. Hourly ridership charts will not be rendered. Run time slot configuration seeder.');
            $timeSlotConfigs = collect();
        }

        foreach ($timeSlotConfigs as $slotConfig) {
            foreach ($routes as $idx => $route) {
                $count = (int) $effectiveQuery()
                    ->where('route_id', $route->id)
                    ->where('departure_time', '>=', $slotConfig->start_time)
                    ->where('departure_time', '<', $slotConfig->end_time)
                    ->sum('passengers');

                $hourlyRidership[] = [
                    'route' => $route->name,
                    'hour' => $slotConfig->time_slot_display,
                    'count' => $count,
                    'color' => $colorPalette[$idx % count($colorPalette)],
                ];
            }
        }

        // 4. Bus Logs
        $busRouteMap = DB::table('schedules')
            ->select('bus_id', 'route_id', DB::raw('SUM(passengers) as total_pax'), DB::raw('COUNT(*) as trip_count'))
            ->groupBy('bus_id', 'route_id')
            ->orderByDesc('total_pax')
            ->get()
            ->keyBy('bus_id');

        $busQuery = Bus::orderBy('id');
        if ($selectedRoute !== 'all') {
            $busIds = DB::table('schedules')
                ->where('route_id', $selectedRoute)
                ->distinct()
                ->pluck('bus_id');
            $busQuery->whereIn('id', $busIds);
        }
        $buses = $busQuery->get();

        $busLogs = [];
        foreach ($buses as $bus) {
            $busData = $busRouteMap->get($bus->id);
            $busTrips = $busData ? (int) $busData->trip_count : 0;
            $busPax = $busData ? (int) $busData->total_pax : 0;
            $routeId = $busData ? (int) $busData->route_id : null;

            $cap = $bus->capacity;
            $avgLoad = $busTrips > 0 ? round($busPax / $busTrips) : 0;
            $utilizationPct = $cap > 0 ? min(100, round(($avgLoad / $cap) * 100)) : 0;

            $routeObj = $routeId ? $routes->firstWhere('id', $routeId) : null;
            $routeName = $routeObj ? $routeObj->name : 'Unassigned';
            $routeColor = $routeId ? ($colorPalette[($routeId - 1) % count($colorPalette)] ?? '#64748b') : '#64748b';

            $busStatus = match (strtolower((string) $bus->status)) {
                'active' => 'Active',
                'idle' => 'Idle',
                'maintenance' => 'Maintenance',
                'breakdown' => 'Breakdown',
                'inactive' => 'Inactive',
                default => ucfirst((string) $bus->status),
            };

            $busLogs[] = (object) [
                'bus_id' => $bus->plate_number,
                'assigned_route' => $routeName,
                'route_color' => $routeColor,
                'trips_completed' => $busTrips,
                'total_passengers' => $busPax,
                'capacity' => $cap,
                'utilization_rate' => $utilizationPct,
                'status' => $busStatus,
            ];
        }

        usort($busLogs, fn($a, $b) => $b->total_passengers <=> $a->total_passengers);

        // 5. Dispatch Recommendations
        $dispatchRecommendations = [];
        foreach ($routes as $route) {
            $peakRec = $effectiveQuery()
                ->where('route_id', $route->id)
                ->selectRaw($isSqlite ? "CAST(strftime('%H', departure_time) AS INTEGER) as hr, SUM(passengers) as total" : "HOUR(departure_time) as hr, SUM(passengers) as total")
                ->groupBy('hr')
                ->orderByDesc('total')
                ->first();

            $peakPassengers = $peakRec ? (int) $peakRec->total : 0;
            $peakHr = $peakRec ? (int) $peakRec->hr : 8;
            $slotConfig = TimeSlotConfiguration::getTimeSlotByHour($peakHr);
            $peakHrLabel = $slotConfig
                ? $slotConfig->time_slot_display
                : ($peakHr < 12
                    ? "{$peakHr}:00 AM – " . ($peakHr + 1) . ":00 AM"
                    : ($peakHr === 12 ? "12:00 PM – 1:00 PM" : ($peakHr - 12) . ":00 PM – " . ($peakHr - 11) . ":00 PM"));

            // Count only buses currently assigned to this route that are active now.
            // The old approach (joining schedules) incorrectly counted buses from past
            // schedules that may now be active on a different route.
            $activeBusesOnRoute = Bus::where('route_id', $route->id)->where('status', 'active')->count();
            $totalBusesOnRoute = Bus::where('route_id', $route->id)->count();

            $cap = Bus::getDefaultCapacity();
            $recBuses = max(1, (int) ceil($peakPassengers / $cap));

            if ($activeBusesOnRoute < $recBuses) {
                $status = 'Underserved';
                $insight = "Peak demand of {$peakPassengers} pax in the busiest hour. {$activeBusesOnRoute} of {$totalBusesOnRoute} assigned buses are active. Recommend dispatching {$recBuses} buses.";
            } elseif ($activeBusesOnRoute > $recBuses + 1) {
                $status = 'Surplus';
                $insight = "Current active buses ({$activeBusesOnRoute}) exceed demand. Consider reducing to {$recBuses} buses during off-peak hours.";
            } else {
                $status = 'Adequate';
                $insight = "Demand of {$peakPassengers} pax/hr is well-served by {$activeBusesOnRoute} active buses. No action needed.";
            }

            $dispatchRecommendations[] = (object) [
                'route' => $route->name,
                'recommended_dispatch' => $recBuses . ' bus' . ($recBuses > 1 ? 'es' : ''),
                'peak_window' => $peakHrLabel,
                'status' => $status,
                'insight_blurb' => $insight,
            ];
        }

        return [
            'metricSummary' => $metricSummary,
            'routeSummary' => $routeSummary,
            'hourlyRidership' => $hourlyRidership,
            'busLogs' => $busLogs,
            'dispatchRecommendations' => $dispatchRecommendations,
        ];
    }
}
