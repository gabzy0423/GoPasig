<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Route;
use App\Models\Driver;
use App\Models\Schedule;
use App\Models\ColorPalette;
use App\Models\SystemSetting;
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

        $countInRange = Schedule::whereBetween('service_date', [$start->toDateString(), $end->toDateString()])->count();
        $useAllTime = $countInRange === 0;
        $baseQ = fn() => $useAllTime ? Schedule::query() : Schedule::whereBetween('service_date', [$start->toDateString(), $end->toDateString()]);

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
            if ($selectedStatus === 'Early') {
                $schedQuery->whereNotNull('actual_departure_time')
                    ->whereRaw('actual_departure_time < departure_time');
            } else {
                $dbStatus = match ($selectedStatus) {
                    'On Time' => 'On time',
                    'Late' => 'Delayed',
                    'Missed' => 'Cancelled',
                    default => $selectedStatus,
                };
                $schedQuery->where('status', $dbStatus);
                if ($dbStatus === 'On time') {
                    $schedQuery->where(function ($q) {
                        $q->whereNull('actual_departure_time')
                          ->orWhereRaw('actual_departure_time >= departure_time');
                    });
                }
            }
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
                'on time' => ($s->actual_departure_time && $s->actual_departure_time < $s->departure_time) ? 'Early' : 'On Time',
                'delayed' => 'Late',
                'cancelled' => 'Missed',
                default => ucwords((string) $s->status),
            };

            $depTime = Carbon::parse($s->departure_time);
            $arrTime = Carbon::parse($s->arrival_time);
            $duration = $depTime->diffInMinutes($arrTime);

            $departureFormatted = Carbon::parse($s->departure_time)->format('g:i A');
            if ($s->actual_departure_time) {
                $actualDep = Carbon::parse($s->actual_departure_time)->format('g:i A');
                $varianceMin = (int) Carbon::parse($s->departure_time)->diffInMinutes(Carbon::parse($s->actual_departure_time), false);
                $isEstimated = false;
            } else {
                $isEstimated = (strtolower((string) $s->status) === 'delayed');
                if (strtolower((string) $s->status) === 'delayed') {
                    $varianceMin = $s->delay_minutes ?? 0;
                    $actualDep = Carbon::parse($s->departure_time)->addMinutes($varianceMin)->format('g:i A');
                } else {
                    $varianceMin = 0;
                    $actualDep = $uiStatus === 'Missed' ? '--' : $departureFormatted;
                }
            }

            return [
                'trip_id' => 'SCH-' . str_pad($s->id, 4, '0', STR_PAD_LEFT),
                'bus_id' => $busPlate,
                'driver_name' => $driverName,
                'route_name' => $routeName,
                'route_color' => $routeColor,
                'scheduled_departure' => $departureFormatted,
                'actual_departure' => $actualDep,
                'variance_minutes' => $varianceMin,
                'is_estimated' => $isEstimated,
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
        $timeSlotConfigs = \App\Models\TimeSlotConfiguration::where('is_active', true)->orderBy('order')->get();
        if ($timeSlotConfigs->isEmpty()) {
            \Illuminate\Support\Facades\Log::error('TimeSlotConfiguration table is empty. Delay trend hourly chart will not be rendered. Run time slot configuration seeder.');
            $timeSlotConfigs = collect();
        }

        $delayTrend = [];
        $trendRoutes = $selectedRoute !== 'all'
            ? $routes->where('id', (int) $selectedRoute)
            : $routes;

        foreach ($trendRoutes as $route) {
            foreach ($timeSlotConfigs as $slotConfig) {
                $startHour = (int) substr($slotConfig->start_time, 0, 2);
                $endHour = (int) substr($slotConfig->end_time, 0, 2);

                $delayedCount = $baseQ()
                    ->where('route_id', $route->id)
                    ->where('status', 'Delayed')
                    ->when($isSqlite, function ($query) use ($startHour, $endHour) {
                        $query->whereRaw("CAST(strftime('%H', departure_time) AS INTEGER) >= ? AND CAST(strftime('%H', departure_time) AS INTEGER) < ?", [$startHour, $endHour]);
                    }, function ($query) use ($startHour, $endHour) {
                        $query->whereRaw("HOUR(departure_time) >= ? AND HOUR(departure_time) < ?", [$startHour, $endHour]);
                    })
                    ->count();

                $delayTrend[] = [
                    'route' => $route->name,
                    'label' => $slotConfig->time_slot_display,
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
                if ($ds->actual_departure_time) {
                    $totalDelayMin += max(0, (int) Carbon::parse($ds->departure_time)->diffInMinutes(Carbon::parse($ds->actual_departure_time), false));
                } elseif ($ds->delay_minutes > 0) {
                    $totalDelayMin += $ds->delay_minutes;
                } else {
                    $totalDelayMin += $ds->delay_minutes ?? 0;
                }
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
                DB::raw('SUM(schedules.delay_minutes) as total_delay_minutes'),
                $isSqlite
                ? DB::raw('SUM(CASE WHEN schedules.actual_departure_time IS NOT NULL THEN (strftime("%s", schedules.actual_departure_time) - strftime("%s", schedules.departure_time)) / 60 ELSE 0 END) as total_actual_delay_minutes')
                : DB::raw('SUM(CASE WHEN schedules.actual_departure_time IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, schedules.departure_time, schedules.actual_departure_time) ELSE 0 END) as total_actual_delay_minutes'),
                $isSqlite
                ? DB::raw('SUM((strftime("%s", schedules.arrival_time) - strftime("%s", schedules.departure_time)) / 60) as total_duration')
                : DB::raw('SUM(TIMESTAMPDIFF(MINUTE, schedules.departure_time, schedules.arrival_time)) as total_duration')
            )
            ->groupBy('drivers.id', 'drivers.first_name', 'drivers.last_name', 'routes.id', 'routes.name')
            ->orderByDesc('late_count')
            ->limit(5)
            ->get();

        $lateDrivers = $driverLateStats->map(function ($row) use ($colorPalette) {
            $avgDelay = 0;
            if ($row->late_count > 0) {
                if ($row->total_actual_delay_minutes > 0) {
                    $avgDelay = (int) round($row->total_actual_delay_minutes / $row->late_count);
                } elseif ($row->total_delay_minutes > 0) {
                    $avgDelay = (int) round($row->total_delay_minutes / $row->late_count);
                } else {
                    $avgDelay = 0;
                }
            }
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
