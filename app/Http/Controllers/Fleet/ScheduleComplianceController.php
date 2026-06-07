<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Route;
use App\Models\Driver;
use App\Models\Schedule;
use App\Models\ColorPalette;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class ScheduleComplianceController extends Controller
{
    /**
     * Display the Schedule Compliance view.
     */
    public function index(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::today()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', Carbon::today()->toDateString());
        $selectedRoute = $request->input('route_id', 'all');
        $selectedDriver = $request->input('driver', 'all');
        $selectedStatus = $request->input('status', 'all');
        $page = $request->input('page', 1);

        $availableRoutes = Route::orderBy('id')->get(['id', 'name'])->toArray();
        $availableDrivers = Driver::orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn($d) => ['id' => $d->id, 'name' => "{$d->first_name} {$d->last_name}"])
            ->toArray();

        $data = $this->getComplianceData($dateFrom, $dateTo, $selectedRoute, $selectedDriver, $selectedStatus);

        // Paginate logs for server side render
        $tripsCollection = collect($data['tripLogs']);
        $perPage = 10;
        $paginatedTrips = new LengthAwarePaginator(
            $tripsCollection->slice(($page - 1) * $perPage, $perPage)->values(),
            $tripsCollection->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('fleet.schedule.index', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'selectedRoute' => $selectedRoute,
            'selectedDriver' => $selectedDriver,
            'selectedStatus' => $selectedStatus,
            'availableRoutes' => $availableRoutes,
            'availableDrivers' => $availableDrivers,
            'complianceSummary' => $data['complianceSummary'],
            'routeCompliance' => $data['routeCompliance'],
            'delayTrend' => $data['delayTrend'],
            'tripLogs' => $paginatedTrips,
            'rawTripLogsCount' => $tripsCollection->count(),
            'delayedRoutes' => $data['delayedRoutes'],
            'lateDrivers' => $data['lateDrivers'],
            'driverList' => $data['driverList'],
        ]);
    }

    /**
     * Get JSON data for compliance dashboard AJAX refreshing.
     */
    public function getComplianceDataAjax(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::today()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', Carbon::today()->toDateString());
        $selectedRoute = $request->input('route_id', 'all');
        $selectedDriver = $request->input('driver', 'all');
        $selectedStatus = $request->input('status', 'all');

        $data = $this->getComplianceData($dateFrom, $dateTo, $selectedRoute, $selectedDriver, $selectedStatus);

        return response()->json([
            'complianceSummary' => $data['complianceSummary'],
            'routeCompliance' => $data['routeCompliance'],
            'delayTrend' => $data['delayTrend'],
            'tripLogs' => $data['tripLogs'],
            'rawTripLogsCount' => collect($data['tripLogs'])->count(),
            'delayedRoutes' => $data['delayedRoutes'],
            'lateDrivers' => $data['lateDrivers'],
        ]);
    }

    /**
     * Export compliance report as CSV.
     */
    public function exportCsv(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::today()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', Carbon::today()->toDateString());
        $selectedRoute = $request->input('route_id', 'all');
        $selectedDriver = $request->input('driver', 'all');
        $selectedStatus = $request->input('status', 'all');

        $data = $this->getComplianceData($dateFrom, $dateTo, $selectedRoute, $selectedDriver, $selectedStatus);
        $summary = $data['complianceSummary'];
        $trips = collect($data['tripLogs']);
        $lateDrivers = $data['lateDrivers'];

        $availableRoutes = Route::orderBy('id')->get(['id', 'name'])->toArray();
        $routeLabel = $selectedRoute === 'all'
            ? 'All Routes'
            : (collect($availableRoutes)->firstWhere('id', (int) $selectedRoute)['name'] ?? 'Route ' . $selectedRoute);
        $driverLabel = $selectedDriver === 'all' ? 'All Drivers' : $selectedDriver;

        $filename = 'schedule-compliance-'
            . str_replace(' ', '-', strtolower($routeLabel))
            . '-' . $dateFrom . '-to-' . $dateTo . '.csv';

        $rows = [];
        $rows[] = ['GoPasig Schedule Compliance Report'];
        $rows[] = ['Period', $dateFrom . ' to ' . $dateTo];
        $rows[] = ['Route Filter', $routeLabel];
        $rows[] = ['Driver Filter', $driverLabel];
        $rows[] = ['Generated At', now()->format('Y-m-d H:i:s')];
        $rows[] = [];

        $rows[] = ['=== SUMMARY ==='];
        $rows[] = ['On-time Rate', $summary->on_time_rate . '%'];
        $rows[] = ['Trips Completed', $summary->trips_completed];
        $rows[] = ['On Time / Early', $summary->on_time_count];
        $rows[] = ['Late Departures', $summary->late_count];
        $rows[] = ['Missed Trips', $summary->missed_count];
        $rows[] = [];

        $rows[] = ['=== TRIP LOG ==='];
        $rows[] = ['Trip ID', 'Bus Plate', 'Driver', 'Route', 'Sched. Departure', 'Actual Departure', 'Variance', 'Status'];
        foreach ($trips as $t) {
            $rows[] = [
                $t['trip_id'],
                $t['bus_id'],
                $t['driver_name'],
                $t['route_name'],
                $t['scheduled_departure'],
                $t['actual_departure'],
                $t['variance_minutes'] >= 0 ? '+' . $t['variance_minutes'] . ' min' : $t['variance_minutes'] . ' min',
                $t['status'],
            ];
        }
        $rows[] = [];

        $rows[] = ['=== DRIVERS WITH LATE DEPARTURES ==='];
        if (empty($lateDrivers)) {
            $rows[] = ['No drivers with late departures.'];
        } else {
            $rows[] = ['Driver', 'Route', 'Late Trips', 'Avg Delay (min)'];
            foreach ($lateDrivers as $ld) {
                $rows[] = [
                    $ld['driver_name'],
                    $ld['assigned_route'],
                    $ld['late_count'],
                    $ld['avg_delay_minutes'],
                ];
            }
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
     * Core compliance data calculator (called by index, AJAX, and CSV export).
     */
    public function getComplianceData($dateFrom, $dateTo, $selectedRoute, $selectedDriver, $selectedStatus): array
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $start = Carbon::parse($dateFrom)->startOfDay();
        $end = Carbon::parse($dateTo)->endOfDay();

        $colorPalette = ColorPalette::getColors('analytics');

        $countInRange = Schedule::whereBetween('created_at', [$start, $end])->count();
        $useAllTime = $countInRange === 0;
        $baseQ = fn() => $useAllTime ? Schedule::query() : Schedule::whereBetween('created_at', [$start, $end]);

        $routes = Route::orderBy('id')->get();
        $routeColorMap = [];
        foreach ($routes as $idx => $r) {
            $routeColorMap[$r->id] = $colorPalette[$idx % count($colorPalette)];
        }

        // Dropdown drivers options lookup helper
        $availableDrivers = Driver::orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn($d) => ['id' => $d->id, 'name' => "{$d->first_name} {$d->last_name}"])
            ->toArray();

        // 1. Build Trip Log from schedules table
        $schedQuery = $baseQ()->with(['route', 'bus', 'driver'])->orderBy('departure_time');

        if ($selectedRoute !== 'all') {
            $schedQuery->where('route_id', (int) $selectedRoute);
        }
        if ($selectedDriver !== 'all') {
            $schedQuery->whereHas('driver', function ($q) use ($isSqlite, $selectedDriver) {
                if ($isSqlite) {
                    $q->whereRaw("(first_name || ' ' || last_name) = ?", [$selectedDriver]);
                } else {
                    $q->whereRaw("CONCAT(first_name, ' ', last_name) = ?", [$selectedDriver]);
                }
            });
        }
        if ($selectedStatus !== 'all') {
            $dbStatus = match ($selectedStatus) {
                'On Time' => 'On time',
                'Late' => 'Delayed',
                'Missed' => 'Cancelled',
                'Early' => 'On time',
                default => $selectedStatus,
            };
            $schedQuery->where('status', $dbStatus);
        }

        $schedules = $schedQuery->get();

        $tripLogs = $schedules->map(function ($s) use ($routeColorMap) {
            $routeName = $s->route ? $s->route->name : 'N/A';
            $busPlate = $s->bus ? $s->bus->plate_number : 'N/A';
            $driverName = $s->driver
                ? "{$s->driver->first_name} {$s->driver->last_name}"
                : 'Unknown';
            $routeColor = $s->route ? ($routeColorMap[$s->route_id] ?? '#64748b') : '#64748b';

            $uiStatus = match (strtolower((string) $s->status)) {
                'on time' => 'On Time',
                'delayed' => 'Late',
                'cancelled' => 'Missed',
                default => ucwords((string) $s->status),
            };

            $depTime = Carbon::parse($s->departure_time);
            $arrTime = Carbon::parse($s->arrival_time);
            $duration = $depTime->diffInMinutes($arrTime);

            $varianceMin = match (strtolower((string) $s->status)) {
                'delayed' => max(1, (int) round($duration * 0.1)),
                'cancelled' => 0,
                default => 0,
            };

            $departureFormatted = Carbon::parse($s->departure_time)->format('g:i A');
            $actualDep = $uiStatus === 'Missed' ? '--' : $departureFormatted;

            return [
                'trip_id' => 'SCH-' . str_pad($s->id, 4, '0', STR_PAD_LEFT),
                'bus_id' => $busPlate,
                'driver_name' => $driverName,
                'route_name' => $routeName,
                'route_color' => $routeColor,
                'scheduled_departure' => $departureFormatted,
                'actual_departure' => $actualDep,
                'variance_minutes' => $varianceMin,
                'status' => $uiStatus,
                'schedule_id' => $s->id,
            ];
        })->toArray();

        // 2. Summary Metrics
        $tripLogsCollection = collect($tripLogs);
        $totalTrips = $tripLogsCollection->count();
        $onTimeCount = $tripLogsCollection->filter(fn($t) => in_array($t['status'], ['On Time', 'Early']))->count();
        $lateCount = $tripLogsCollection->filter(fn($t) => $t['status'] === 'Late')->count();
        $missedCount = $tripLogsCollection->filter(fn($t) => $t['status'] === 'Missed')->count();
        $tripsCompleted = $tripLogsCollection->filter(fn($t) => $t['status'] !== 'Missed')->count();
        $onTimeRate = $totalTrips > 0 ? round(($onTimeCount / $totalTrips) * 100) : 0;

        $complianceSummary = (object) [
            'on_time_rate' => $onTimeRate,
            'trips_completed' => $tripsCompleted,
            'on_time_count' => $onTimeCount,
            'late_count' => $lateCount,
            'missed_count' => $missedCount,
        ];

        // 3. On-time rate per route (left chart)
        $routeCompliance = [];
        foreach ($routes as $idx => $route) {
            if ($selectedRoute !== 'all' && $route->id != $selectedRoute) {
                continue;
            }
            $rScheds = $baseQ()->where('route_id', $route->id);
            if ($selectedDriver !== 'all') {
                $rScheds->whereHas(
                    'driver',
                    fn($q) =>
                    $isSqlite
                    ? $q->whereRaw("(first_name || ' ' || last_name) = ?", [$selectedDriver])
                    : $q->whereRaw("CONCAT(first_name, ' ', last_name) = ?", [$selectedDriver])
                );
            }
            $rAll = $rScheds->count();
            $rOnTime = (clone $rScheds)->whereIn('status', ['On time'])->count();
            $rate = $rAll > 0 ? round(($rOnTime / $rAll) * 100) : 0;

            $routeCompliance[] = [
                'route_name' => $route->name,
                'on_time_rate' => $rate,
                'color' => $colorPalette[$idx % count($colorPalette)],
            ];
        }

        // 4. Delay trend by hour (right chart)
        $hours = ['05:00', '07:00', '09:00', '11:00', '13:00', '15:00', '17:00'];
        $hourBlocks = [5, 7, 9, 11, 13, 15, 17];

        $delayTrend = [];
        $trendRoutes = $selectedRoute !== 'all'
            ? $routes->where('id', (int) $selectedRoute)
            : $routes;

        foreach ($trendRoutes as $route) {
            foreach ($hourBlocks as $hidx => $block) {
                $nextBlock = $hourBlocks[$hidx + 1] ?? 24;
                $delayedCount = $baseQ()
                    ->where('route_id', $route->id)
                    ->where('status', 'Delayed')
                    ->when($isSqlite, function ($query) use ($block, $nextBlock) {
                        $query->whereRaw("CAST(strftime('%H', departure_time) AS INTEGER) >= ? AND CAST(strftime('%H', departure_time) AS INTEGER) < ?", [$block, $nextBlock]);
                    }, function ($query) use ($block, $nextBlock) {
                        $query->whereRaw("HOUR(departure_time) >= ? AND HOUR(departure_time) < ?", [$block, $nextBlock]);
                    })
                    ->count();

                $delayTrend[] = [
                    'route' => $route->name,
                    'label' => $hours[$hidx],
                    'delayed_count' => $delayedCount,
                    'color' => $colorPalette[($route->id - 1) % count($colorPalette)],
                ];
            }
        }

        // 5. Most delayed routes
        $delayedRoutes = [];
        foreach ($routes as $idx => $route) {
            if ($selectedRoute !== 'all' && $route->id != $selectedRoute) {
                continue;
            }
            $rDelayedScheds = $baseQ()->where('route_id', $route->id)->where('status', 'Delayed')->get();
            $totalDelayMin = 0;
            foreach ($rDelayedScheds as $ds) {
                $dur = Carbon::parse($ds->departure_time)->diffInMinutes(Carbon::parse($ds->arrival_time));
                $totalDelayMin += max(1, (int) round($dur * 0.1));
            }
            if ($totalDelayMin > 0) {
                $delayedRoutes[] = [
                    'route_name' => $route->name,
                    'route_color' => $colorPalette[$idx % count($colorPalette)],
                    'total_delay_minutes' => $totalDelayMin,
                ];
            }
        }
        usort($delayedRoutes, fn($a, $b) => $b['total_delay_minutes'] <=> $a['total_delay_minutes']);

        // 6. Drivers with most late departures
        $driverLateStats = DB::table('schedules')
            ->join('drivers', 'schedules.driver_id', '=', 'drivers.id')
            ->join('routes', 'schedules.route_id', '=', 'routes.id')
            ->where('schedules.status', 'Delayed')
            ->when(!$useAllTime, fn($q) => $q->whereBetween('schedules.created_at', [$start, $end]))
            ->when($selectedRoute !== 'all', fn($q) => $q->where('schedules.route_id', (int) $selectedRoute))
            ->when($selectedDriver !== 'all', fn($q) => $q->whereRaw(
                $isSqlite
                ? "(drivers.first_name || ' ' || drivers.last_name) = ?"
                : "CONCAT(drivers.first_name, ' ', drivers.last_name) = ?",
                [$selectedDriver]
            ))
            ->select(
                'drivers.id as driver_id',
                'drivers.first_name',
                'drivers.last_name',
                'routes.id as route_id',
                'routes.name as route_name',
                DB::raw('COUNT(*) as late_count'),
                $isSqlite
                ? DB::raw('SUM((strftime("%s", schedules.arrival_time) - strftime("%s", schedules.departure_time)) / 60) as total_duration')
                : DB::raw('SUM(TIMESTAMPDIFF(MINUTE, schedules.departure_time, schedules.arrival_time)) as total_duration')
            )
            ->groupBy('drivers.id', 'drivers.first_name', 'drivers.last_name', 'routes.id', 'routes.name')
            ->orderByDesc('late_count')
            ->limit(5)
            ->get();

        $lateDrivers = $driverLateStats->map(function ($row) use ($colorPalette) {
            $avgDelay = $row->late_count > 0
                ? max(1, (int) round(($row->total_duration / $row->late_count) * 0.1))
                : 0;
            return [
                'driver_name' => "{$row->first_name} {$row->last_name}",
                'late_count' => (int) $row->late_count,
                'assigned_route' => $row->route_name,
                'route_color' => $colorPalette[($row->route_id - 1) % count($colorPalette)],
                'avg_delay_minutes' => $avgDelay,
            ];
        })->toArray();

        return [
            'driverList' => collect($availableDrivers)->pluck('name')->toArray(),
            'complianceSummary' => $complianceSummary,
            'routeCompliance' => $routeCompliance,
            'delayTrend' => $delayTrend,
            'tripLogs' => $tripLogs,
            'delayedRoutes' => $delayedRoutes,
            'lateDrivers' => $lateDrivers,
        ];
    }
}
