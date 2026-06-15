<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Route;
use App\Models\Stop;
use App\Models\Schedule;
use App\Models\Incident;
use App\Models\ColorPalette;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class RoutePerformanceController extends Controller
{
    /**
     * Display the Route Performance view.
     */
    public function index(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::today()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::today()->toDateString());
        $selectedRoute = $request->input('route_id', 'all');
        $page = $request->input('page', 1);

        $availableRoutes = Route::orderBy('id')->get(['id', 'name'])->toArray();

        $data = $this->getRoutePerformanceData($startDate, $endDate, $selectedRoute);

        // Paginate stops for initial server-side render
        $stopsCollection = collect($data['stops']);
        $perPage = 10;
        $paginatedStops = new LengthAwarePaginator(
            $stopsCollection->slice(($page - 1) * $perPage, $perPage)->values(),
            $stopsCollection->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('fleet.performance.routes.index', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedRoute' => $selectedRoute,
            'availableRoutes' => $availableRoutes,
            'routePerformanceSummary' => $data['summary'],
            'headwayData' => $data['headway'],
            'scheduleCompliance' => $data['schedule'],
            'stopAdherence' => $paginatedStops,
            'deviationLog' => $data['deviations'],
            'routeHealthScore' => $data['health'],
        ]);
    }

    /**
     * Get JSON data for filtering.
     */
    public function getRoutesData(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::today()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::today()->toDateString());
        $selectedRoute = $request->input('route_id', 'all');
        $selectedDeviationTypes = $request->input('deviation_types', []);

        $data = $this->getRoutePerformanceData($startDate, $endDate, $selectedRoute);

        // Filter deviations if filter is active
        $filteredDeviations = collect($data['deviations']);
        if (!empty($selectedDeviationTypes)) {
            $filteredDeviations = $filteredDeviations->filter(
                fn($dev) => in_array($dev['deviation_type'], $selectedDeviationTypes)
            );
        }

        return response()->json([
            'routePerformanceSummary' => $data['summary'],
            'headwayData' => $data['headway'],
            'scheduleCompliance' => $data['schedule'],
            'stops' => $data['stops'], // Send all stops so client JS can paginate or sort locally
            'deviationLog' => $filteredDeviations->values()->toArray(),
            'routeHealthScore' => $data['health'],
        ]);
    }

    /**
     * Export Route Performance Report as CSV.
     */
    public function exportCsv(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::today()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::today()->toDateString());
        $selectedRoute = $request->input('route_id', 'all');

        $data = $this->getRoutePerformanceData($startDate, $endDate, $selectedRoute);
        $summary = $data['summary'];
        $stops = $data['stops'];
        $incidentList = $data['deviations'];

        $routeLabel = $selectedRoute === 'all' ? 'All Routes' : ('Route ' . $selectedRoute);
        $filename = 'route-performance-' . str_replace(' ', '-', strtolower($routeLabel))
            . '-' . $startDate . '-to-' . $endDate . '.csv';

        $rows = [];
        $rows[] = ['GoPasig Route Performance Report'];
        $rows[] = ['Route', $routeLabel];
        $rows[] = ['Period', $startDate . ' to ' . $endDate];
        $rows[] = ['Generated At', now()->format('Y-m-d H:i:s')];
        $rows[] = [];

        $rows[] = ['=== SUMMARY ==='];
        $rows[] = ['Trips Completed', $summary->trips_completed];
        $rows[] = ['On-time Rate', $summary->on_time_rate . '%'];
        $rows[] = ['Avg Headway', $summary->avg_headway . ' min'];
        $rows[] = ['Stop Adherence Rate', $summary->stop_adherence_rate . '%'];
        $rows[] = ['Incidents Recorded', $summary->deviations_count];
        $rows[] = [];

        $rows[] = ['=== STOP ADHERENCE ==='];
        $rows[] = ['Stop Name', 'Route', 'Seq', 'Buses w/ Schedule'];
        foreach ($stops as $s) {
            $rows[] = [
                $s['stop_name'],
                $s['route_name'],
                $s['sequence'],
                $s['buses_passed'],
            ];
        }
        $rows[] = [];

        $rows[] = ['=== INCIDENTS ==='];
        if (count($incidentList) === 0) {
            $rows[] = ['No incidents recorded for this period.'];
        } else {
            $rows[] = ['Type', 'Severity', 'Bus', 'Driver', 'Route', 'Reported At', 'Description'];
            foreach ($incidentList as $inc) {
                $rows[] = [
                    $inc['deviation_type'],
                    $inc['severity'],
                    $inc['bus_id'],
                    $inc['driver_name'],
                    $inc['route'],
                    $inc['recorded_at'],
                    $inc['description'],
                ];
            }
        }

        $csvContent = implode("\n", array_map(function ($row) {
            return implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string) $v) . '"', $row));
        }, $rows));

        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Internal helper: Fetch and compute route performance records.
     */
    public function getRoutePerformanceData($startDate, $endDate, $selectedRoute): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $colorPalette = ColorPalette::getColors('analytics');

        $countInRange = Schedule::whereBetween('created_at', [$start, $end])->count();
        $useAllTime = $countInRange === 0;

        $effectiveQuery = fn() => $useAllTime ? Schedule::query() : Schedule::whereBetween('created_at', [$start, $end]);

        $routeIds = $selectedRoute !== 'all' ? [(int) $selectedRoute] : null;

        $baseQuery = fn() => $routeIds
            ? $effectiveQuery()->whereIn('route_id', $routeIds)
            : $effectiveQuery();

        // 1. Summary metrics
        $totalTrips = (int) $baseQuery()->count();
        $onTimeCount = (int) $baseQuery()->where('status', 'On time')->count();
        $onTimeRate = $totalTrips > 0 ? round(($onTimeCount / $totalTrips) * 100) : 100;

        $allHeadways = [];
        $routeScope = $routeIds ?? Route::pluck('id')->toArray();
        foreach ($routeScope as $routeId) {
            $departures = $effectiveQuery()
                ->where('route_id', $routeId)
                ->orderBy('departure_time')
                ->pluck('departure_time')
                ->toArray();

            for ($i = 1; $i < count($departures); $i++) {
                $prev = strtotime($departures[$i - 1]);
                $curr = strtotime($departures[$i]);
                // Guard against midnight-crossing: if the stored value is a time-only
                // string, strtotime() anchors both to today. A negative diff means the
                // sequence wrapped past 00:00, so add one full day to the later value.
                $diffSec = $curr - $prev;
                if ($diffSec < 0) {
                    $diffSec += 86400;
                }
                $diffMin = $diffSec / 60;
                if ($diffMin > 0 && $diffMin < 200) {
                    $allHeadways[] = $diffMin;
                }
            }
        }
        $avgHeadway = count($allHeadways) > 0 ? round(array_sum($allHeadways) / count($allHeadways)) : 0;

        $totalStops = Stop::when($routeIds, fn($q) => $q->whereIn('route_id', $routeIds))->count();
        $stopsWithBuses = Stop::when($routeIds, fn($q) => $q->whereIn('route_id', $routeIds))
            ->whereHas('route', fn($q) => $q->whereIn('id', $effectiveQuery()->distinct()->pluck('route_id')))
            ->count();
        $stopAdherenceRate = $totalStops > 0 ? round(($stopsWithBuses / $totalStops) * 100) : 0;

        $incidentQuery = Incident::with(['trip.route', 'trip.bus', 'driver']);
        if (!$useAllTime) {
            $incidentQuery->whereBetween('created_at', [$start, $end]);
        }
        if ($routeIds) {
            $incidentQuery->whereHas('trip', fn($q) => $q->whereIn('route_id', $routeIds));
        }
        $incidentsAll = $incidentQuery->orderByDesc('created_at')->get();
        $deviationsCount = $incidentsAll->count();

        // Resolve KPI targets dynamically from the routes table or system settings
        $defaultOnTimeTarget = (int) SystemSetting::get('default_on_time_target', 85);
        $defaultHeadwayTarget = (int) SystemSetting::get('default_headway_target', 15);

        if ($selectedRoute !== 'all') {
            $kpiRoute = Route::find($selectedRoute);
            $onTimeTarget = $kpiRoute?->target_on_time_rate ?? $defaultOnTimeTarget;
            $headwayTarget = $kpiRoute?->target_headway_minutes ?? $defaultHeadwayTarget;
        } else {
            $onTimeTarget = (int) round(Route::avg('target_on_time_rate') ?? $defaultOnTimeTarget);
            $headwayTarget = (int) round(Route::avg('target_headway_minutes') ?? $defaultHeadwayTarget);
        }

        $summary = (object) [
            'trips_completed' => $totalTrips,
            'on_time_rate' => $onTimeRate,
            'on_time_target' => $onTimeTarget,
            'avg_headway' => $avgHeadway,
            'headway_target' => $headwayTarget,
            'deviations_count' => $deviationsCount,
            'stop_adherence_rate' => $stopAdherenceRate,
        ];

        // 2. Headway chart data
        $headwayData = [];
        foreach ($routeScope as $ridx => $routeId) {
            $departures = $effectiveQuery()
                ->where('route_id', $routeId)
                ->orderBy('departure_time')
                ->pluck('departure_time')
                ->toArray();

            $routeForHeadway = Route::find($routeId);
            $targetHeadway = $routeForHeadway?->target_headway_minutes ?? 15;
            for ($i = 1; $i < count($departures); $i++) {
                $prev = strtotime($departures[$i - 1]);
                $curr = strtotime($departures[$i]);
                // Guard against midnight-crossing wrap-around on time-only strings.
                $diffSec = $curr - $prev;
                if ($diffSec < 0) {
                    $diffSec += 86400;
                }
                $gap = round($diffSec / 60);
                if ($gap > 0 && $gap < 200) {
                    $headwayData[] = [
                        'trip_sequence' => 'Trip ' . $i . ($selectedRoute === 'all' ? ' (' . ($routeForHeadway ? $routeForHeadway->name : 'R' . $routeId) . ')' : ''),
                        'actual_headway' => $gap,
                        'target_headway' => $targetHeadway,
                    ];
                }
            }
        }

        // 3. Schedule compliance
        $scheduleData = $baseQuery()
            ->orderBy('departure_time')
            ->get(['id', 'route_id', 'departure_time', 'arrival_time', 'status', 'passengers', 'delay_minutes']);

        $scheduleCompliance = [];
        foreach ($scheduleData as $idx => $s) {
            $depTime = Carbon::parse($s->departure_time);
            $arrTime = Carbon::parse($s->arrival_time);
            $scheduledDuration = $arrTime->diffInMinutes($depTime);

            $variance = match (strtolower((string) $s->status)) {
                'on time' => 0,
                'delayed' => $s->delay_minutes ?: rand(3, 12),
                'cancelled' => 20,
                default => 0,
            };

            $label = 'Trip ' . ($idx + 1) . ' — ' . Carbon::parse($s->departure_time)->format('g:i A');
            $scheduleCompliance[] = [
                'trip_label' => $label,
                'variance_minutes' => $variance,
            ];
        }

        // 4. Stop adherence log
        $stopsQuery = Stop::with('route')
            ->when($routeIds, fn($q) => $q->whereIn('route_id', $routeIds))
            ->orderBy('route_id')
            ->orderBy('sequence');

        $stopRows = [];
        foreach ($stopsQuery->get() as $stop) {
            $routeObj = $stop->route;
            $routeColor = $colorPalette[(($routeObj ? $routeObj->id : 1) - 1) % count($colorPalette)];

            $busesOnRoute = $effectiveQuery()
                ->where('route_id', $stop->route_id)
                ->distinct()
                ->count('bus_id');

            $hasSchedules = $effectiveQuery()->where('route_id', $stop->route_id)->exists();
            $status = $hasSchedules ? 'Served' : 'No Service';

            $firstDep = $effectiveQuery()
                ->where('route_id', $stop->route_id)
                ->orderBy('departure_time')
                ->value('departure_time');

            $schedTimeStr = $firstDep ? Carbon::parse($firstDep)->format('g:i A') : '--';

            $stopRows[] = [
                'stop_name' => $stop->name,
                'route_name' => $routeObj ? $routeObj->name : 'N/A',
                'route_color' => $routeColor,
                'sequence' => $stop->sequence,
                'scheduled_time' => $schedTimeStr,
                'actual_time' => $hasSchedules ? $schedTimeStr : '--',
                'variance_minutes' => 0,
                'status' => $status,
                'buses_passed' => $busesOnRoute,
                'avg_dwell_seconds' => 0,
            ];
        }

        // 5. Deviation log from incidents
        $deviationLog = [];
        foreach ($incidentsAll as $inc) {
            $busPlate = $inc->trip && $inc->trip->bus ? $inc->trip->bus->plate_number : 'N/A';
            $driverName = $inc->driver
                ? "{$inc->driver->first_name} {$inc->driver->last_name}"
                : 'Unknown Driver';
            $routeObj = $inc->trip && $inc->trip->route ? $inc->trip->route : null;
            $routeName = $routeObj ? $routeObj->name : 'N/A';
            $routeColor = $routeObj
                ? ($colorPalette[($routeObj->id - 1) % count($colorPalette)])
                : '#64748b';

            $devType = match (strtolower((string) $inc->type)) {
                'off-route', 'route deviation' => 'Off-Route',
                'long dwell', 'dwell' => 'Long Dwell',
                'early departure' => 'Early Departure',
                'route skip', 'skip' => 'Route Skip',
                'speed', 'speeding' => 'Speed Anomaly',
                default => ucwords((string) $inc->type),
            };

            $severity = match (strtolower((string) $inc->status)) {
                'reported', 'under_review' => 'High',
                'resolved' => 'Medium',
                default => 'Low',
            };

            $deviationLog[] = [
                'deviation_type' => $devType,
                'severity' => $severity,
                'bus_id' => $busPlate,
                'driver_name' => $driverName,
                'route' => $routeName,
                'route_color' => $routeColor,
                'description' => $inc->description ?: 'No description provided.',
                'recorded_at' => $inc->reported_at
                    ? $inc->reported_at->format('g:i A')
                    : ($inc->created_at ? $inc->created_at->format('g:i A') : '--'),
            ];
        }

        // 6. Route health score — use dynamic headway target resolved above
        $onTimeScore = min(25, round($onTimeRate / 100 * 25));
        $headwayScore = $avgHeadway > 0 && $avgHeadway <= $headwayTarget
            ? 25
            : ($avgHeadway > 0 ? max(0, round(25 - (($avgHeadway - $headwayTarget) / $headwayTarget) * 25)) : 20);
        $stopAdhScore = min(25, round($stopAdherenceRate / 100 * 25));
        $devScore = max(0, 25 - ($deviationsCount * 5));
        $overallScore = $onTimeScore + $headwayScore + $stopAdhScore + $devScore;

        $scoreLabel = $overallScore >= 80 ? 'Good' : ($overallScore >= 60 ? 'Fair' : 'Critical');

        $routeHealthScore = (object) [
            'overall_score' => $overallScore,
            'score_label' => $scoreLabel,
            'on_time_score' => $onTimeScore,
            'headway_score' => $headwayScore,
            'stop_adherence_score' => $stopAdhScore,
            'deviation_score' => $devScore,
        ];

        return [
            'summary' => $summary,
            'headway' => $headwayData,
            'schedule' => $scheduleCompliance,
            'stops' => $stopRows,
            'deviations' => $deviationLog,
            'health' => $routeHealthScore,
        ];
    }
}
