<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Driver;
use App\Models\Schedule;
use App\Models\Incident;
use App\Models\Route;
use App\Models\ColorPalette;
use App\Services\DriverPerformanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DriverPerformanceController extends Controller
{
    /**
     * Display the Driver Performance view.
     */
    public function index(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::today()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::today()->toDateString());
        $selectedRoute = $request->input('route_id', 'all');
        $selectedStatus = $request->input('status', 'all');
        $search = $request->input('search', '');

        $availableRoutes = Route::orderBy('id')->get(['id', 'name'])->toArray();

        $drivers = $this->getFilteredDriversList($startDate, $endDate, $selectedRoute, $selectedStatus, $search);
        $metrics = $this->getMetrics($drivers);
        $topDrivers = $this->getTopDrivers($drivers);

        return view('fleet.performance.drivers.index', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedRoute' => $selectedRoute,
            'selectedStatus' => $selectedStatus,
            'search' => $search,
            'availableRoutes' => $availableRoutes,
            'driverMetrics' => $metrics,
            'topDrivers' => $topDrivers,
            'driverLogs' => $drivers,
            'driverPerformance' => $drivers, // For ECharts rendering initial state
        ]);
    }

    /**
     * Get JSON data for filtering.
     */
    public function getDriversData(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::today()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::today()->toDateString());
        $selectedRoute = $request->input('route_id', 'all');
        $selectedStatus = $request->input('status', 'all');
        $search = $request->input('search', '');

        $drivers = $this->getFilteredDriversList($startDate, $endDate, $selectedRoute, $selectedStatus, $search);
        $metrics = $this->getMetrics($drivers);
        $topDrivers = $this->getTopDrivers($drivers);

        return response()->json([
            'driverMetrics' => $metrics,
            'topDrivers' => $topDrivers,
            'driverLogs' => $drivers,
            'driverPerformance' => $drivers,
        ]);
    }

    /**
     * Get details for a specific driver (trips and incidents for the drawer).
     */
    public function getDriverDetails(Request $request, $id)
    {
        // Parse DB id from string if it starts with DRV-
        $dbId = (int) ltrim(str_replace('DRV-', '', $id), '0');

        $drv = Driver::find($dbId);
        if (!$drv) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 404);
        }

        // Get driver record data
        $startDate = $request->input('start_date', Carbon::today()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::today()->toDateString());

        $allDrivers = $this->buildDriverData($startDate, $endDate);
        $driverArr = collect($allDrivers)->firstWhere('db_id', $dbId);

        if (!$driverArr) {
            return response()->json(['success' => false, 'message' => 'Driver data build failed.'], 500);
        }

        $trips = Schedule::with('route')
            ->where('driver_id', $dbId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()->map(function ($s) {
                $dep = Carbon::parse($s->departure_time);
                $arr = Carbon::parse($s->arrival_time);
                $dur = $dep->diffInMinutes($arr);
                return (object) [
                    'date' => $s->created_at ? $s->created_at->format('M d, Y') : '—',
                    'route' => $s->route ? $s->route->name : 'N/A',
                    'passengers' => (int) $s->passengers,
                    'duration' => $dur,
                    'status' => $s->status,
                    'incident' => strtolower((string) $s->status) === 'delayed',
                ];
            });

        $incidents = Incident::where('driver_id', $dbId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()->map(function ($inc) {
                $reportedAt = $inc->reported_at
                    ? Carbon::parse($inc->reported_at)->format('M d, Y')
                    : ($inc->created_at ? $inc->created_at->format('M d, Y') : '—');
                return (object) [
                    'date' => $reportedAt,
                    'type' => ucwords((string) ($inc->type ?? 'Incident')),
                    'description' => $inc->description ?: 'No description provided.',
                ];
            });

        return response()->json([
            'success' => true,
            'selectedDriver' => $driverArr,
            'selectedDriverTrips' => $trips,
            'selectedDriverIncidents' => $incidents,
        ]);
    }

    /**
     * Export Driver Performance as CSV.
     */
    public function exportCsv(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::today()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::today()->toDateString());
        $selectedRoute = $request->input('route_id', 'all');
        $selectedStatus = $request->input('status', 'all');
        $search = $request->input('search', '');

        $drivers = $this->getFilteredDriversList($startDate, $endDate, $selectedRoute, $selectedStatus, $search);
        $metrics = $this->getMetrics($drivers);

        $filename = 'driver-performance-' . $startDate . '-to-' . $endDate . '.csv';

        $rows = [];
        $rows[] = ['GoPasig Driver Performance Report'];
        $rows[] = ['Period', $startDate . ' to ' . $endDate];
        $rows[] = ['Generated At', now()->format('Y-m-d H:i:s')];
        $rows[] = [];
        $rows[] = ['=== SUMMARY ==='];
        $rows[] = ['Total Drivers', $metrics->total_drivers];
        $rows[] = ['On Duty Today', $metrics->on_duty_today];
        $rows[] = ['Avg Performance Score', $metrics->avg_performance_score];
        $rows[] = ['Incidents (Period)', $metrics->incidents_this_period];
        $rows[] = ['Avg Trips Per Driver', $metrics->avg_trips_per_driver];
        $rows[] = [];
        $rows[] = ['=== DRIVER RECORDS ==='];
        $rows[] = ['Driver ID', 'Name', 'Route', 'Status', 'Trips Done', 'Passengers', 'Incidents', 'Avg Trip (min)', 'Score'];

        foreach ($drivers as $d) {
            $rows[] = [
                $d['driver_id'],
                $d['driver_name'],
                $d['assigned_route'],
                $d['status'],
                $d['trips_completed'],
                $d['total_passengers_moved'],
                $d['incidents'],
                $d['avg_trip_time_minutes'],
                $d['performance_score'],
            ];
        }

        $csvContent = implode("\n", array_map(function ($row) {
            return implode(',', array_map(
                fn($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                $row
            ));
        }, $rows));

        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Initialize messaging.
     */
    public function messageDriver($id)
    {
        return response()->json([
            'success' => true,
            'message' => "Message thread initialized with Driver ID: {$id}"
        ]);
    }

    /**
     * Internal helper: Build driver data.
     */
    public function buildDriverData($startDate, $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $colorPalette = ColorPalette::getColors('analytics');

        $countInRange = Schedule::whereBetween('created_at', [$start, $end])->count();
        $useAllTime = $countInRange === 0;

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        $schedAgg = DB::table('schedules')
            ->when(!$useAllTime, fn($q) => $q->whereBetween('created_at', [$start, $end]))
            ->select(
                'driver_id',
                DB::raw('COUNT(*) as trips_completed'),
                DB::raw('SUM(passengers) as total_pax'),
                $isSqlite
                ? DB::raw('AVG((strftime("%s", arrival_time) - strftime("%s", departure_time)) / 60) as avg_duration')
                : DB::raw('AVG(TIMESTAMPDIFF(MINUTE, departure_time, arrival_time)) as avg_duration'),
                DB::raw('MAX(route_id) as last_route_id')
            )
            ->groupBy('driver_id')
            ->get()
            ->keyBy('driver_id');

        $routes = Route::orderBy('id')->get()->keyBy('id');
        $drivers = Driver::orderBy('last_name')->get();

        $result = [];
        foreach ($drivers as $drv) {
            $agg = $schedAgg->get($drv->id);

            $tripsCompleted = $agg ? (int) $agg->trips_completed : 0;
            $totalPax = $agg ? (int) $agg->total_pax : (int) $drv->pax_today;
            $avgDuration = $agg ? (int) round($agg->avg_duration) : 0;
            $lastRouteId = $agg ? (int) $agg->last_route_id : null;

            $assignedRouteId = null;
            if (!empty($drv->assigned_route)) {
                $r = $routes->first(fn($r) => $r->name === $drv->assigned_route);
                $assignedRouteId = $r ? $r->id : null;
            }
            if (!$assignedRouteId && $lastRouteId && $routes->has($lastRouteId)) {
                $assignedRouteId = $lastRouteId;
            }
            $assignedRouteName = $assignedRouteId && $routes->has($assignedRouteId)
                ? $routes->get($assignedRouteId)->name
                : 'Unassigned';
            $routeColor = ($assignedRouteId && count($colorPalette) > 0)
                ? ($colorPalette[($assignedRouteId - 1) % count($colorPalette)])
                : '#94a3b8';

            $uiStatus = match (strtolower((string) $drv->status)) {
                'active' => 'On duty',
                'inactive' => 'Off duty',
                'suspended' => 'Suspended',
                default => ucwords((string) $drv->status),
            };

            $incidentsInPeriod = Incident::where('driver_id', $drv->id)
                ->when(!$useAllTime, fn($q) => $q->whereBetween('created_at', [$start, $end]))
                ->count();
            $totalIncidents = max((int) $drv->incidents_30, $incidentsInPeriod);

            $result[] = [
                'driver_id' => 'DRV-' . str_pad($drv->id, 4, '0', STR_PAD_LEFT),
                'db_id' => $drv->id,
                'driver_name' => "{$drv->first_name} {$drv->last_name}",
                'initials' => $drv->initials,
                'emp_id' => $drv->emp_id,
                'assigned_route' => $assignedRouteName,
                'assigned_route_id' => $assignedRouteId,
                'route_color' => $routeColor,
                'status' => $uiStatus,
                'trips_completed' => $tripsCompleted,
                'total_passengers_moved' => $totalPax,
                'incidents' => $totalIncidents,
                'avg_trip_time_minutes' => $avgDuration,
                'performance_score' => DriverPerformanceService::calculateScore(
                    $drv->id,
                    $start,
                    $end,
                    (float) $drv->performance_score
                ),
            ];
        }

        return $result;
    }

    /**
     * Internal helper: Get filtered driver list.
     */
    public function getFilteredDriversList($startDate, $endDate, $selectedRoute, $selectedStatus, $search): array
    {
        $all = $this->buildDriverData($startDate, $endDate);

        return array_values(array_filter($all, function ($d) use ($selectedRoute, $selectedStatus, $search) {
            $matchSearch = empty($search)
                || str_contains(strtolower($d['driver_name']), strtolower($search));

            $matchRoute = $selectedRoute === 'all'
                || $d['assigned_route_id'] == $selectedRoute;

            $matchStatus = $selectedStatus === 'all'
                || strtolower($d['status']) === strtolower($selectedStatus);

            return $matchSearch && $matchRoute && $matchStatus;
        }));
    }

    /**
     * Internal helper: Get top drivers.
     */
    public function getTopDrivers(array $filteredDrivers): array
    {
        $sorted = $filteredDrivers;
        usort($sorted, fn($a, $b) => $b['performance_score'] <=> $a['performance_score']);
        $top5 = array_slice($sorted, 0, 5);
        foreach ($top5 as $idx => &$d) {
            $d['rank'] = $idx + 1;
        }
        return $top5;
    }

    /**
     * Internal helper: Compute summary metrics.
     */
    public function getMetrics(array $filteredDrivers): object
    {
        $total = count($filteredDrivers);
        if ($total === 0) {
            return (object) [
                'total_drivers' => 0,
                'on_duty_today' => 0,
                'avg_performance_score' => 0,
                'incidents_this_period' => 0,
                'avg_trips_per_driver' => 0,
            ];
        }

        $onDuty = count(array_filter($filteredDrivers, fn($d) => $d['status'] === 'On duty'));
        $avgScore = round(array_sum(array_column($filteredDrivers, 'performance_score')) / $total, 1);
        $sumIncidents = array_sum(array_column($filteredDrivers, 'incidents'));
        $sumTrips = array_sum(array_column($filteredDrivers, 'trips_completed'));
        $avgTrips = round($sumTrips / $total, 1);

        return (object) [
            'total_drivers' => $total,
            'on_duty_today' => $onDuty,
            'avg_performance_score' => $avgScore,
            'incidents_this_period' => $sumIncidents,
            'avg_trips_per_driver' => $avgTrips,
        ];
    }
}
