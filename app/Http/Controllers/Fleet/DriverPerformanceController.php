<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverMessage;
use App\Models\Incident;
use App\Models\Route;
use App\Models\Trip;
use App\Models\TripLog;
use App\Models\TripPassengerEvent;
use App\Services\DriverPerformanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DriverPerformanceController extends Controller
{
    /**
     * Display the Driver Performance view.
     */
    public function index(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::today('Asia/Manila')->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::today('Asia/Manila')->toDateString());
        $selectedRoute = $request->input('route_id', 'all');
        $selectedStatus = $request->input('status', 'all');
        $search = $request->input('search', '');

        $availableRoutes = Route::query()
            ->publicCommuterActiveService()
            ->orderBy('id')
            ->get(['id', 'name'])
            ->toArray();

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
            'driverPerformance' => $drivers,
        ]);
    }

    /**
     * Get JSON data for filtering.
     */
    public function getDriversData(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::today('Asia/Manila')->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::today('Asia/Manila')->toDateString());
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
     * Get details for a specific driver from actual Trip records.
     */
    public function getDriverDetails(Request $request, $id)
    {
        $dbId = (int) ltrim(str_replace('DRV-', '', $id), '0');
        $driver = Driver::find($dbId);

        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 404);
        }

        $startDate = $request->input('start_date', Carbon::today('Asia/Manila')->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::today('Asia/Manila')->toDateString());
        $driverRows = $this->buildDriverData($startDate, $endDate);
        $driverRow = collect($driverRows)->firstWhere('db_id', $dbId);

        if (!$driverRow) {
            return response()->json(['success' => false, 'message' => 'Driver data build failed.'], 500);
        }

        [$periodStart, $periodEnd] = $this->periodBounds($startDate, $endDate);
        $trips = $this->operationalTrips($periodStart, $periodEnd, $dbId)
            ->sortByDesc(fn (Trip $trip) => $this->tripActivityTimestamp($trip)?->timestamp ?? 0)
            ->values();
        $tripIds = $trips->pluck('id');
        $tripLogsByTripId = $this->tripLogsByTripId($tripIds);
        $eventSumsByTrip = $this->eventSumsByTrip($tripIds);
        $incidents = $this->incidentQuery($periodStart, $periodEnd, $dbId)
            ->get()
            ->sortByDesc(fn (Incident $incident) => $this->incidentTimestamp($incident)?->timestamp ?? 0)
            ->take(5)
            ->values();
        $incidentTripIds = $incidents->pluck('trip_id')->filter()->values();

        $tripRows = $trips->take(5)
            ->map(fn (Trip $trip) => $this->mapTripRecord($trip, $tripLogsByTripId, $eventSumsByTrip, $incidentTripIds))
            ->values()
            ->all();

        $incidentRows = $incidents->map(function (Incident $incident) {
            return [
                'date' => $this->incidentTimestamp($incident)?->copy()->timezone('Asia/Manila')->format('M d, Y') ?? 'No timestamp',
                'type' => ucwords((string) ($incident->type ?? 'Incident')),
                'description' => $incident->description ?: 'No description provided.',
            ];
        })->all();

        return response()->json([
            'success' => true,
            'selectedDriver' => $driverRow,
            'selectedDriverTrips' => $tripRows,
            'selectedDriverIncidents' => $incidentRows,
        ]);
    }

    /**
     * Export Driver Performance as CSV.
     */
    public function exportCsv(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::today('Asia/Manila')->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::today('Asia/Manila')->toDateString());
        $selectedRoute = $request->input('route_id', 'all');
        $selectedStatus = $request->input('status', 'all');
        $search = $request->input('search', '');

        $drivers = $this->getFilteredDriversList($startDate, $endDate, $selectedRoute, $selectedStatus, $search);
        $metrics = $this->getMetrics($drivers);

        $filename = 'driver-performance-' . $startDate . '-to-' . $endDate . '.csv';
        $rows = [
            ['GoPasig Driver Performance Report'],
            ['Period', $startDate . ' to ' . $endDate],
            ['Generated At', now()->format('Y-m-d H:i:s')],
            [],
            ['=== SUMMARY ==='],
            ['Total Drivers', $metrics->total_drivers],
            ['Drivers With Trips', $metrics->drivers_with_trips],
            ['Avg Performance Score', $metrics->avg_performance_score ?? 'No data'],
            ['Incidents (Period)', $metrics->incidents_this_period],
            ['Avg Trips Per Driver', $metrics->avg_trips_per_driver],
            [],
            ['=== DRIVER RECORDS ==='],
            ['Driver ID', 'Name', 'Route', 'Status', 'Trips Run', 'Recorded Boarded', 'Recorded Alighted', 'Incidents', 'Avg Trip (min)', 'Score'],
        ];

        foreach ($drivers as $driver) {
            $rows[] = [
                $driver['driver_id'],
                $driver['driver_name'],
                $driver['assigned_route'],
                $driver['status'],
                $driver['trips_run'],
                $driver['recorded_boarded'],
                $driver['recorded_alighted'],
                $driver['incidents'],
                $driver['avg_trip_time_minutes'] ?: 'No data',
                $driver['performance_score'] ?? 'No data',
            ];
        }

        $csvContent = implode("\n", array_map(function ($row) {
            return implode(',', array_map(
                fn ($value) => '"' . str_replace('"', '""', (string) $value) . '"',
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
    public function messageDriver(Request $request, $id)
    {
        $dbId = (int) ltrim(str_replace('DRV-', '', $id), '0');
        $driver = Driver::find($dbId);

        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 404);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        DriverMessage::create([
            'driver_id' => $driver->id,
            'sender_id' => auth()->id(),
            'message' => $validated['message'],
            'is_read' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Message sent to {$driver->first_name}",
        ]);
    }

    /**
     * Build one driver row from actual operational records in the selected period.
     */
    public function buildDriverData($startDate, $endDate): array
    {
        [$periodStart, $periodEnd] = $this->periodBounds($startDate, $endDate);
        $routes = Route::query()
            ->publicCommuterActiveService()
            ->get(['id', 'name', 'color'])
            ->keyBy('id');
        $drivers = Driver::query()->orderBy('last_name')->get();
        $tripsByDriver = $this->operationalTrips($periodStart, $periodEnd)->groupBy('driver_id');
        $tripIds = $tripsByDriver->flatten(1)->pluck('id');
        $tripLogsByTripId = $this->tripLogsByTripId($tripIds);
        $eventSumsByTrip = $this->eventSumsByTrip($tripIds);
        $incidentsByDriver = $this->incidentQuery($periodStart, $periodEnd)
            ->get()
            ->groupBy('driver_id');

        $result = [];
        foreach ($drivers as $driver) {
            /** @var Collection<int, Trip> $driverTrips */
            $driverTrips = $tripsByDriver->get($driver->id, collect());
            $completedTrips = $driverTrips->where('status', 'completed')->count();
            $ongoingTrips = $driverTrips->where('status', 'ongoing')->count();
            $dispatchedTrips = $driverTrips->where('status', 'dispatched')->count();
            $cancelledTrips = $driverTrips->where('status', 'cancelled')->count();
            $tripsRun = $completedTrips + $ongoingTrips;

            $recordedBoarded = 0;
            $recordedAlighted = 0;
            foreach ($driverTrips as $trip) {
                $totals = $this->passengerTotalsForTrip($trip, $tripLogsByTripId, $eventSumsByTrip);
                $recordedBoarded += $totals['boarded'];
                $recordedAlighted += $totals['alighted'];
            }

            $durationValues = $driverTrips
                ->where('status', 'completed')
                ->filter(fn (Trip $trip) => $trip->started_at && $trip->ended_at)
                ->map(fn (Trip $trip) => $trip->started_at->diffInMinutes($trip->ended_at));
            $avgDuration = $durationValues->isEmpty() ? 0 : (int) round($durationValues->avg());
            $peakLoad = (int) ($driverTrips->whereIn('status', ['completed', 'ongoing'])->max('peak_passengers') ?? 0);

            $driverIncidents = $incidentsByDriver->get($driver->id, collect());
            $qualifyingIncidents = $driverIncidents
                ->filter(fn (Incident $incident) => Incident::isBreakdown($incident->type) || Incident::isAccident($incident->type))
                ->count();
            $performanceScore = DriverPerformanceService::calculateOperationalScore($tripsRun, $qualifyingIncidents);

            $latestTrip = $driverTrips
                ->sortByDesc(fn (Trip $trip) => $this->tripActivityTimestamp($trip)?->timestamp ?? 0)
                ->first();
            $assignedRoute = $latestTrip?->route;
            if (!$assignedRoute) {
                $assignedRoute = $this->resolveOfficialRoute($driver->assigned_route, $routes);
            }

            $routeIds = $driverTrips->pluck('route_id')->filter()->unique()->values()->all();
            if ($assignedRoute && !in_array($assignedRoute->id, $routeIds, true)) {
                $routeIds[] = $assignedRoute->id;
            }

            $uiStatus = match (strtolower((string) $driver->status)) {
                'active' => 'On duty',
                'inactive' => 'Off duty',
                'suspended' => 'Suspended',
                default => ucwords((string) $driver->status),
            };

            $result[] = [
                'driver_id' => 'DRV-' . str_pad($driver->id, 4, '0', STR_PAD_LEFT),
                'db_id' => $driver->id,
                'driver_name' => "{$driver->first_name} {$driver->last_name}",
                'initials' => $driver->initials,
                'emp_id' => $driver->emp_id,
                'assigned_route' => $assignedRoute?->name ?? 'Unassigned',
                'assigned_route_id' => $assignedRoute?->id,
                'route_ids' => array_values($routeIds),
                'route_color' => $assignedRoute?->color ?: '#94a3b8',
                'status' => $uiStatus,
                'trips_run' => $tripsRun,
                'completed_trips' => $completedTrips,
                'ongoing_trips' => $ongoingTrips,
                'dispatched_trips' => $dispatchedTrips,
                'cancelled_trips' => $cancelledTrips,
                'trips_completed' => $completedTrips,
                'recorded_boarded' => $recordedBoarded,
                'recorded_alighted' => $recordedAlighted,
                'total_passengers_moved' => $recordedBoarded,
                'peak_load' => $peakLoad,
                'incidents' => $driverIncidents->count(),
                'qualifying_incidents' => $qualifyingIncidents,
                'avg_trip_time_minutes' => $avgDuration,
                'performance_score' => $performanceScore,
                'performance_score_basis' => 'actual_operations',
                'performance_score_trips_run' => $tripsRun,
                'performance_score_qualifying_incidents' => $qualifyingIncidents,
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
        $mappedStatus = strtolower($selectedStatus);
        if ($mappedStatus === 'active') {
            $mappedStatus = 'on duty';
        } elseif ($mappedStatus === 'inactive') {
            $mappedStatus = 'off duty';
        }

        return array_values(array_filter($all, function ($driver) use ($selectedRoute, $mappedStatus, $search) {
            $matchSearch = empty($search)
                || str_contains(strtolower($driver['driver_name']), strtolower($search));
            $matchRoute = $selectedRoute === 'all'
                || in_array((int) $selectedRoute, $driver['route_ids'], true);
            $matchStatus = $mappedStatus === 'all'
                || strtolower($driver['status']) === $mappedStatus;

            return $matchSearch && $matchRoute && $matchStatus;
        }));
    }

    /**
     * Internal helper: Get top drivers.
     */
    public function getTopDrivers(array $filteredDrivers): array
    {
        $sorted = $filteredDrivers;
        usort($sorted, function ($left, $right) {
            return [
                $right['performance_score'] ?? -1,
                $right['trips_run'],
                $right['completed_trips'],
                $right['peak_load'],
                $left['driver_name'],
            ] <=> [
                $left['performance_score'] ?? -1,
                $left['trips_run'],
                $left['completed_trips'],
                $left['peak_load'],
                $right['driver_name'],
            ];
        });

        $top5 = array_slice($sorted, 0, 5);
        foreach ($top5 as $index => &$driver) {
            $driver['rank'] = $index + 1;
        }

        return $top5;
    }

    /**
     * Internal helper: Compute summary metrics.
     */
    public function getMetrics(array $filteredDrivers): object
    {
        $total = count($filteredDrivers);
        $scores = collect($filteredDrivers)
            ->pluck('performance_score')
            ->filter(fn ($score) => $score !== null)
            ->values();
        $driversWithTrips = count(array_filter($filteredDrivers, fn ($driver) => $driver['trips_run'] > 0));

        return (object) [
            'total_drivers' => $total,
            'drivers_with_trips' => $driversWithTrips,
            'on_duty_today' => $driversWithTrips,
            'avg_performance_score' => $scores->isEmpty() ? null : round($scores->avg(), 1),
            'incidents_this_period' => array_sum(array_column($filteredDrivers, 'incidents')),
            'avg_trips_per_driver' => $total > 0
                ? round(array_sum(array_column($filteredDrivers, 'trips_run')) / $total, 1)
                : 0,
        ];
    }

    private function periodBounds($startDate, $endDate): array
    {
        return [
            Carbon::parse($startDate, 'Asia/Manila')->startOfDay()->utc(),
            Carbon::parse($endDate, 'Asia/Manila')->endOfDay()->utc(),
        ];
    }

    private function operationalTrips(Carbon $periodStart, Carbon $periodEnd, $driverId = null): Collection
    {
        return Trip::query()
            ->with(['route', 'bus'])
            ->when($driverId !== null, fn ($query) => $query->where('driver_id', $driverId))
            ->whereHas('route', fn ($query) => $query->publicCommuterActiveService())
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->where(function ($completed) use ($periodStart, $periodEnd) {
                    $completed->where('status', 'completed')
                        ->whereBetween('ended_at', [$periodStart, $periodEnd]);
                })->orWhere(function ($ongoing) use ($periodStart, $periodEnd) {
                    $ongoing->where('status', 'ongoing')
                        ->whereBetween('started_at', [$periodStart, $periodEnd]);
                })->orWhere(function ($dispatched) use ($periodStart, $periodEnd) {
                    $dispatched->where('status', 'dispatched')
                        ->whereBetween('dispatched_at', [$periodStart, $periodEnd]);
                })->orWhere(function ($cancelled) use ($periodStart, $periodEnd) {
                    $cancelled->where('status', 'cancelled')
                        ->whereBetween('ended_at', [$periodStart, $periodEnd]);
                });
            })
            ->get();
    }

    private function incidentQuery(Carbon $periodStart, Carbon $periodEnd, $driverId = null)
    {
        return Incident::query()
            ->with(['trip.route'])
            ->when($driverId !== null, fn ($query) => $query->where('driver_id', $driverId))
            ->whereHas('trip.route', fn ($query) => $query->publicCommuterActiveService())
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->whereBetween('reported_at', [$periodStart, $periodEnd])
                    ->orWhere(function ($fallback) use ($periodStart, $periodEnd) {
                        $fallback->whereNull('reported_at')
                            ->whereBetween('created_at', [$periodStart, $periodEnd]);
                    });
            });
    }

    private function tripLogsByTripId(Collection $tripIds): Collection
    {
        if ($tripIds->isEmpty()) {
            return collect();
        }

        return TripLog::query()
            ->whereIn('trip_id', $tripIds)
            ->get()
            ->keyBy('trip_id');
    }

    private function eventSumsByTrip(Collection $tripIds): Collection
    {
        if ($tripIds->isEmpty()) {
            return collect();
        }

        return TripPassengerEvent::query()
            ->whereIn('trip_id', $tripIds)
            ->select('trip_id', 'event_type', DB::raw('SUM(passenger_delta) as total'))
            ->groupBy('trip_id', 'event_type')
            ->get()
            ->groupBy('trip_id');
    }

    private function passengerTotalsForTrip(Trip $trip, Collection $tripLogsByTripId, Collection $eventSumsByTrip): array
    {
        $eventSums = $eventSumsByTrip->get($trip->id, collect());
        $boardedRow = $eventSums->firstWhere('event_type', TripPassengerEvent::TYPE_BOARDED);
        $alightedRow = $eventSums->firstWhere('event_type', TripPassengerEvent::TYPE_ALIGHTED);
        $eventBoarded = $boardedRow ? (int) $boardedRow->total : 0;
        $eventAlighted = $alightedRow ? (int) $alightedRow->total : 0;

        $tripLog = $tripLogsByTripId->get($trip->id);
        $isFinalized = in_array((string) $trip->status, ['completed', 'cancelled'], true);

        return [
            'boarded' => $isFinalized && $tripLog ? (int) $tripLog->passengers : $eventBoarded,
            'alighted' => $isFinalized && $tripLog ? (int) $tripLog->alighted_passengers : $eventAlighted,
        ];
    }

    private function mapTripRecord(Trip $trip, Collection $tripLogsByTripId, Collection $eventSumsByTrip, Collection $incidentTripIds): array
    {
        $totals = $this->passengerTotalsForTrip($trip, $tripLogsByTripId, $eventSumsByTrip);
        $timestamp = $this->tripActivityTimestamp($trip);
        $duration = $trip->started_at && $trip->ended_at
            ? (int) $trip->started_at->diffInMinutes($trip->ended_at)
            : 0;

        return [
            'trip_no' => 'TRIP-' . str_pad((string) $trip->id, 4, '0', STR_PAD_LEFT),
            'date' => $timestamp?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') ?? 'No timestamp',
            'route' => $trip->route?->name ?? 'No route',
            'status' => ucfirst((string) $trip->status),
            'recorded_boarded' => $totals['boarded'],
            'recorded_alighted' => $totals['alighted'],
            'peak_load' => (int) ($trip->peak_passengers ?? 0),
            'duration' => $duration,
            'incident' => $incidentTripIds->contains($trip->id),
        ];
    }

    private function tripActivityTimestamp(Trip $trip): ?Carbon
    {
        return match ((string) $trip->status) {
            'completed', 'cancelled' => $trip->ended_at ?? $trip->updated_at,
            'ongoing' => $trip->started_at ?? $trip->updated_at,
            'dispatched' => $trip->dispatched_at ?? $trip->updated_at,
            default => $trip->updated_at ?? $trip->created_at,
        };
    }

    private function incidentTimestamp(Incident $incident): ?Carbon
    {
        return $incident->reported_at ?? $incident->created_at;
    }

    private function resolveOfficialRoute($assignedRoute, Collection $routes): ?Route
    {
        $value = trim((string) $assignedRoute);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && $routes->has((int) $value)) {
            return $routes->get((int) $value);
        }

        return $routes->first(fn (Route $route) => $route->name === $value);
    }
}
