<?php

namespace App\Http\Controllers\Fleet;

use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\Trip;
use App\Models\TripPassengerEvent;
use App\Services\Analytics\ActualRouteHealthService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoutePerformanceController extends Controller
{
    private const OPERATIONS_TIMEZONE = 'Asia/Manila';

    public function __construct(private ActualRouteHealthService $actualRouteHealthService)
    {
    }

    /**
     * Display the Route Performance view.
     */
    public function index(Request $request)
    {
        $startDate = $request->input(
            'start_date',
            Carbon::today(self::OPERATIONS_TIMEZONE)->subDays(30)->toDateString()
        );
        $endDate = $request->input(
            'end_date',
            Carbon::today(self::OPERATIONS_TIMEZONE)->toDateString()
        );
        $page = (int) $request->input('page', 1);

        $data = $this->getRoutePerformanceData(
            $startDate,
            $endDate,
            $request->input('route_id', 'all')
        );

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
            'selectedRoute' => $data['selected_route'],
            'availableRoutes' => $data['available_routes'],
            'routePerformanceSummary' => $data['summary'],
            'headwayData' => $data['headway'],
            'tripDurationData' => $data['trip_durations'],
            'stopActivityData' => $data['stops'],
            'stopAdherence' => $paginatedStops,
            'incidentLog' => $data['incidents'],
            'routeHealthScore' => $data['health'],
        ]);
    }

    /**
     * Get JSON data for filtering.
     */
    public function getRoutesData(Request $request)
    {
        $startDate = $request->input(
            'start_date',
            Carbon::today(self::OPERATIONS_TIMEZONE)->subDays(30)->toDateString()
        );
        $endDate = $request->input(
            'end_date',
            Carbon::today(self::OPERATIONS_TIMEZONE)->toDateString()
        );
        $data = $this->getRoutePerformanceData(
            $startDate,
            $endDate,
            $request->input('route_id', 'all')
        );

        return response()->json([
            'availableRoutes' => $data['available_routes'],
            'selectedRoute' => $data['selected_route'],
            'routePerformanceSummary' => $data['summary'],
            'headwayData' => $data['headway'],
            'tripDurationData' => $data['trip_durations'],
            'stops' => $data['stops'],
            'incidentLog' => $data['incidents'],
            'routeHealthScore' => $data['health'],
        ]);
    }

    /**
     * Export Route Performance Report as CSV.
     */
    public function exportCsv(Request $request)
    {
        $startDate = $request->input(
            'start_date',
            Carbon::today(self::OPERATIONS_TIMEZONE)->subDays(30)->toDateString()
        );
        $endDate = $request->input(
            'end_date',
            Carbon::today(self::OPERATIONS_TIMEZONE)->toDateString()
        );

        $data = $this->getRoutePerformanceData(
            $startDate,
            $endDate,
            $request->input('route_id', 'all')
        );
        $summary = $data['summary'];
        $routeLabel = $data['route_label'];
        $filename = 'route-performance-' . str_replace(' ', '-', strtolower($routeLabel))
            . '-' . $startDate . '-to-' . $endDate . '.csv';

        $rows = [];
        $rows[] = ['GoPasig Route Performance Report'];
        $rows[] = ['Route', $routeLabel];
        $rows[] = ['Period', $startDate . ' to ' . $endDate];
        $rows[] = ['Generated At', Carbon::now(self::OPERATIONS_TIMEZONE)->format('Y-m-d H:i:s')];
        $rows[] = [];

        $rows[] = ['=== ACTUAL TRIP SUMMARY ==='];
        $rows[] = ['Trips Run', $summary->trips_run];
        $rows[] = ['Completed', $summary->completed_trips];
        $rows[] = ['Ongoing', $summary->ongoing_trips];
        $rows[] = ['Dispatched', $summary->dispatched_trips];
        $rows[] = ['Cancelled', $summary->cancelled_trips];
        $rows[] = ['Avg Trip Duration', $summary->avg_trip_duration_label];
        $rows[] = ['Avg Actual Headway', $summary->avg_actual_headway_label];
        $rows[] = ['Observed Headway Gaps', $summary->headway_observations];
        $rows[] = ['Data Source', 'Actual Trip lifecycle records'];
        $rows[] = [];

        $rows[] = ['=== DIRECTION-AWARE ACTUAL HEADWAY ==='];
        if ($data['headway'] === []) {
            $rows[] = ['No direction has at least two actual Trip starts on the same Manila service day.'];
        } else {
            $rows[] = ['Route', 'Direction', 'Origin', 'Destination', 'Trip Starts', 'Observed Gaps', 'Avg Headway', 'Minimum', 'Maximum'];
            foreach ($data['headway'] as $headway) {
                $rows[] = [
                    $headway['route_name'],
                    $headway['direction_label'],
                    $headway['origin_name'],
                    $headway['destination_name'],
                    $headway['trip_starts'],
                    $headway['observed_intervals'],
                    $headway['average_headway_minutes'] . ' min',
                    $headway['minimum_headway_minutes'] . ' min',
                    $headway['maximum_headway_minutes'] . ' min',
                ];
            }
        }
        $rows[] = [];

        $rows[] = ['=== ACTUAL TRIP DURATION BY DIRECTION ==='];
        if ($data['trip_durations'] === []) {
            $rows[] = ['No valid completed Trip durations are available by direction for this period.'];
        } else {
            $rows[] = ['Route', 'Direction', 'Origin', 'Destination', 'Completed Trips', 'Valid Durations', 'Avg Duration', 'Minimum', 'Maximum'];
            foreach ($data['trip_durations'] as $duration) {
                $rows[] = [
                    $duration['route_name'],
                    $duration['direction_label'],
                    $duration['origin_name'],
                    $duration['destination_name'],
                    $duration['completed_trips'],
                    $duration['valid_duration_trips'],
                    $duration['average_duration_minutes'] . ' min',
                    $duration['minimum_duration_minutes'] . ' min',
                    $duration['maximum_duration_minutes'] . ' min',
                ];
            }
        }
        $rows[] = [];

        $rows[] = ['=== RECORDED STOP ACTIVITY ==='];
        if ($data['stops'] === []) {
            $rows[] = ['No stop-attributed passenger activity was recorded for this period.'];
        } else {
            $rows[] = ['Route', 'Direction', 'Stop', 'Sequence', 'Recorded Boarded', 'Recorded Alighted', 'Trips Recorded', 'Latest Activity'];
            foreach ($data['stops'] as $stop) {
                $rows[] = [
                    $stop['route_name'],
                    $stop['direction_label'],
                    $stop['stop_name'],
                    $stop['sequence_label'],
                    $stop['recorded_boarded'],
                    $stop['recorded_alighted'],
                    $stop['trips_recorded'],
                    $stop['latest_activity_label'],
                ];
            }
        }
        $rows[] = [];

        $rows[] = ['=== ACTUAL ROUTE HEALTH ==='];
        $health = $data['health'];
        $rows[] = ['Overall Score', $health['overall_score'] === null ? 'Insufficient evidence' : $health['overall_score'] . '%'];
        $rows[] = ['Status', $health['score_label']];
        $rows[] = ['Trip Completion Reliability', $this->scoreCsvLabel($health['completion_score']), $health['completion_evidence']];
        $rows[] = ['Headway Consistency', $this->scoreCsvLabel($health['headway_score']), $health['headway_evidence']];
        $rows[] = ['Recorded Incident-Free Trips', $this->scoreCsvLabel($health['incident_free_score']), $health['incident_evidence']];
        foreach ($health['missing_evidence'] as $missingEvidence) {
            $rows[] = ['Missing Evidence', $missingEvidence];
        }
        $rows[] = ['Method', 'Equal-weight average of three actual-operation component percentages; all evidence is required.'];
        $rows[] = [];

        $rows[] = ['=== DEFERRED METRICS ==='];
        $rows[] = ['On-time performance and stop adherence remain deferred.'];
        $rows[] = [];

        $rows[] = ['=== OPERATIONAL INCIDENTS ==='];
        if ($data['incidents'] === []) {
            $rows[] = ['No operational incidents were recorded for this period.'];
        } else {
            $rows[] = ['Type', 'Status', 'Bus', 'Driver', 'Route', 'Recorded At', 'Description'];
            foreach ($data['incidents'] as $event) {
                $rows[] = [
                    $event['event_type'],
                    $event['status_label'],
                    $event['bus_id'],
                    $event['driver_name'],
                    $event['route'],
                    $event['recorded_at'],
                    $event['description'],
                ];
            }
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
     * Fetch the actual-operation Route Performance payload.
     */
    public function getRoutePerformanceData($startDate, $endDate, $selectedRoute): array
    {
        [$periodStart, $periodEnd] = $this->periodBounds((string) $startDate, (string) $endDate);
        $availableRoutes = $this->availableRoutes();
        $resolvedRoute = $this->resolveSelectedRoute($selectedRoute, $availableRoutes);
        $routeIds = $resolvedRoute === 'all'
            ? $availableRoutes->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [(int) $resolvedRoute];

        $trips = $this->operationalTrips($routeIds, $periodStart, $periodEnd);
        $completedTrips = $trips->where('status', TripStatus::COMPLETED->value);
        $ongoingTrips = $trips->where('status', TripStatus::ONGOING->value);
        $dispatchedTrips = $trips->where('status', TripStatus::DISPATCHED->value);
        $cancelledTrips = $trips->where('status', TripStatus::CANCELLED->value);

        $durations = $completedTrips
            ->map(fn (Trip $trip) => $this->validDurationMinutes($trip))
            ->filter(fn ($duration) => $duration !== null)
            ->values();
        $averageDuration = $durations->isEmpty() ? null : (int) round($durations->avg());

        $variantDescriptors = $this->variantDescriptors($routeIds, $availableRoutes);
        $headwayAnalysis = $this->directionalHeadwayAnalysis(
            $routeIds,
            $periodStart,
            $periodEnd,
            $variantDescriptors
        );
        $durationAnalysis = $this->directionalDurationAnalysis($completedTrips, $variantDescriptors);
        $stopActivity = $this->recordedStopActivity(
            $routeIds,
            $periodStart,
            $periodEnd,
            $variantDescriptors
        );
        $averageHeadway = $headwayAnalysis['intervals']->isEmpty()
            ? null
            : round((float) $headwayAnalysis['intervals']->avg(), 1);

        $incidents = $this->incidents($routeIds, $periodStart, $periodEnd);
        $operationalIncidents = $this->formatOperationalIncidents($incidents);
        $routeHealth = $this->actualRouteHealthService->calculate(
            $trips,
            $headwayAnalysis['interval_rows'],
            $incidents
        );
        $summary = (object) [
            'trips_run' => $completedTrips->count() + $ongoingTrips->count(),
            'completed_trips' => $completedTrips->count(),
            'ongoing_trips' => $ongoingTrips->count(),
            'dispatched_trips' => $dispatchedTrips->count(),
            'cancelled_trips' => $cancelledTrips->count(),
            'avg_trip_duration_minutes' => $averageDuration,
            'avg_trip_duration_label' => $averageDuration === null ? 'No data' : $averageDuration . ' min',
            'avg_actual_headway_minutes' => $averageHeadway,
            'avg_actual_headway_label' => $averageHeadway === null ? 'No data' : $this->minuteLabel($averageHeadway),
            'headway_observations' => $headwayAnalysis['intervals']->count(),
            'recorded_boarded' => collect($stopActivity)->sum('recorded_boarded'),
            'recorded_alighted' => collect($stopActivity)->sum('recorded_alighted'),
            'unattributed_boarded' => collect($stopActivity)
                ->where('is_attributed', false)
                ->sum('recorded_boarded'),
            'unattributed_alighted' => collect($stopActivity)
                ->where('is_attributed', false)
                ->sum('recorded_alighted'),
            // Compatibility aliases remain truthful while older consumers migrate.
            'trips_completed' => $completedTrips->count(),
            'incidents_count' => $incidents->count(),
            'open_incidents_count' => $incidents->where('status', '!=', 'resolved')->count(),
            'on_time_rate' => null,
            'on_time_target' => null,
            'avg_headway' => $averageHeadway,
            'headway_target' => null,
            'stop_adherence_rate' => null,
        ];

        $route = $resolvedRoute === 'all'
            ? null
            : $availableRoutes->firstWhere('id', (int) $resolvedRoute);

        return [
            'available_routes' => $availableRoutes->values()->all(),
            'selected_route' => $resolvedRoute,
            'route_label' => $route['name'] ?? 'All Routes',
            'summary' => $summary,
            'headway' => $headwayAnalysis['rows'],
            'trip_durations' => $durationAnalysis,
            'stops' => $stopActivity,
            'incidents' => $operationalIncidents,
            'health' => $routeHealth,
        ];
    }

    private function availableRoutes(): Collection
    {
        return Route::query()
            ->publicCommuterActiveService()
            ->get(['id', 'name', 'color'])
            ->map(fn (Route $route) => [
                'id' => (int) $route->id,
                'name' => $route->name,
                'color' => $route->color,
            ]);
    }

    private function resolveSelectedRoute($selectedRoute, Collection $availableRoutes): string
    {
        if ((string) $selectedRoute === 'all') {
            return 'all';
        }

        $routeId = filter_var($selectedRoute, FILTER_VALIDATE_INT);

        return $routeId !== false && $availableRoutes->contains('id', (int) $routeId)
            ? (string) $routeId
            : 'all';
    }

    private function periodBounds(string $startDate, string $endDate): array
    {
        $startDay = Carbon::parse($startDate, self::OPERATIONS_TIMEZONE)->startOfDay();
        $endDay = Carbon::parse($endDate, self::OPERATIONS_TIMEZONE)->startOfDay();

        if ($startDay->gt($endDay)) {
            [$startDay, $endDay] = [$endDay, $startDay];
        }

        return [
            $startDay->copy()->startOfDay()->utc(),
            $endDay->copy()->endOfDay()->utc(),
        ];
    }

    private function operationalTrips(array $routeIds, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        if ($routeIds === []) {
            return collect();
        }

        return Trip::query()
            ->whereIn('route_id', $routeIds)
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->where(function ($completed) use ($periodStart, $periodEnd) {
                    $completed->where('status', TripStatus::COMPLETED->value)
                        ->whereBetween('ended_at', [$periodStart, $periodEnd]);
                })->orWhere(function ($ongoing) use ($periodStart, $periodEnd) {
                    $ongoing->where('status', TripStatus::ONGOING->value)
                        ->whereBetween('started_at', [$periodStart, $periodEnd]);
                })->orWhere(function ($dispatched) use ($periodStart, $periodEnd) {
                    $dispatched->where('status', TripStatus::DISPATCHED->value)
                        ->whereBetween('dispatched_at', [$periodStart, $periodEnd]);
                })->orWhere(function ($cancelled) use ($periodStart, $periodEnd) {
                    $cancelled->where('status', TripStatus::CANCELLED->value)
                        ->whereBetween('ended_at', [$periodStart, $periodEnd]);
                });
            })
            ->get([
                'id',
                'route_id',
                'route_variant_id',
                'status',
                'dispatched_at',
                'started_at',
                'ended_at',
            ]);
    }

    private function variantDescriptors(array $routeIds, Collection $availableRoutes): Collection
    {
        if ($routeIds === []) {
            return collect();
        }

        $routesById = $availableRoutes->keyBy('id');
        $routeOrder = $availableRoutes->values()->mapWithKeys(
            fn (array $route, int $index) => [(int) $route['id'] => $index]
        );

        return RouteVariant::query()
            ->whereIn('route_id', $routeIds)
            ->whereIn('direction', ['outbound', 'inbound'])
            ->get(['id', 'route_id', 'direction', 'origin_name', 'destination_name'])
            ->map(function (RouteVariant $variant) use ($routesById, $routeOrder): array {
                $route = $routesById->get((int) $variant->route_id);
                $direction = strtolower((string) $variant->direction);

                return [
                    'id' => (int) $variant->id,
                    'route_id' => (int) $variant->route_id,
                    'route_name' => $route['name'],
                    'route_color' => $route['color'],
                    'route_order' => $routeOrder->get((int) $variant->route_id, PHP_INT_MAX),
                    'direction' => $direction,
                    'direction_label' => ucfirst($direction),
                    'display_label' => $route['name'] . ' ' . strtoupper($direction === 'outbound' ? 'OUT' : 'IN'),
                    'origin_name' => $variant->origin_name ?: 'Unknown origin',
                    'destination_name' => $variant->destination_name ?: 'Unknown destination',
                ];
            })
            ->sortBy(fn (array $variant) => sprintf(
                '%010d-%d',
                $variant['route_order'],
                $variant['direction'] === 'outbound' ? 0 : 1
            ))
            ->keyBy('id');
    }

    private function directionalHeadwayAnalysis(
        array $routeIds,
        Carbon $periodStart,
        Carbon $periodEnd,
        Collection $variantDescriptors
    ): array {
        if ($routeIds === [] || $variantDescriptors->isEmpty()) {
            return ['rows' => [], 'intervals' => collect(), 'interval_rows' => collect()];
        }

        $startedTrips = Trip::query()
            ->whereIn('route_id', $routeIds)
            ->whereIn('status', [TripStatus::COMPLETED->value, TripStatus::ONGOING->value])
            ->whereNotNull('route_variant_id')
            ->whereBetween('started_at', [$periodStart, $periodEnd])
            ->orderBy('started_at')
            ->orderBy('id')
            ->get(['id', 'route_id', 'route_variant_id', 'started_at'])
            ->filter(function (Trip $trip) use ($variantDescriptors): bool {
                $variant = $variantDescriptors->get((int) $trip->route_variant_id);

                return $variant !== null && $variant['route_id'] === (int) $trip->route_id;
            })
            ->values();

        $intervals = collect();
        $startedTrips
            ->groupBy(function (Trip $trip): string {
                $serviceDate = $trip->started_at
                    ->copy()
                    ->setTimezone(self::OPERATIONS_TIMEZONE)
                    ->toDateString();

                return $trip->route_variant_id . '|' . $serviceDate;
            })
            ->each(function (Collection $serviceDayTrips) use ($intervals): void {
                $orderedTrips = $serviceDayTrips
                    ->sortBy(fn (Trip $trip) => sprintf('%020d-%020d', $trip->started_at->getTimestamp(), $trip->id))
                    ->values();

                for ($index = 1; $index < $orderedTrips->count(); $index++) {
                    $previous = $orderedTrips[$index - 1];
                    $current = $orderedTrips[$index];
                    $seconds = $current->started_at->getTimestamp() - $previous->started_at->getTimestamp();

                    if ($seconds >= 0) {
                        $intervals->push([
                            'route_variant_id' => (int) $current->route_variant_id,
                            'minutes' => $seconds / 60,
                        ]);
                    }
                }
            });

        $tripStartsByVariant = $startedTrips->countBy(fn (Trip $trip) => (int) $trip->route_variant_id);
        $rows = $intervals
            ->groupBy('route_variant_id')
            ->map(function (Collection $variantIntervals, int $variantId) use ($variantDescriptors, $tripStartsByVariant): array {
                $variant = $variantDescriptors->get($variantId);
                $minutes = $variantIntervals->pluck('minutes');

                return array_merge($this->publicVariantDescriptor($variant), [
                    'trip_starts' => (int) $tripStartsByVariant->get($variantId, 0),
                    'observed_intervals' => $minutes->count(),
                    'average_headway_minutes' => round((float) $minutes->avg(), 1),
                    'minimum_headway_minutes' => round((float) $minutes->min(), 1),
                    'maximum_headway_minutes' => round((float) $minutes->max(), 1),
                ]);
            })
            ->sortBy(fn (array $row) => sprintf(
                '%010d-%d',
                $variantDescriptors->get($row['route_variant_id'])['route_order'],
                $row['direction'] === 'outbound' ? 0 : 1
            ))
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'intervals' => $intervals->pluck('minutes')->values(),
            'interval_rows' => $intervals->values(),
        ];
    }

    private function directionalDurationAnalysis(Collection $completedTrips, Collection $variantDescriptors): array
    {
        if ($variantDescriptors->isEmpty()) {
            return [];
        }

        return $completedTrips
            ->filter(function (Trip $trip) use ($variantDescriptors): bool {
                $variant = $variantDescriptors->get((int) $trip->route_variant_id);

                return $variant !== null && $variant['route_id'] === (int) $trip->route_id;
            })
            ->groupBy(fn (Trip $trip) => (int) $trip->route_variant_id)
            ->map(function (Collection $variantTrips, int $variantId) use ($variantDescriptors): ?array {
                $durations = $variantTrips
                    ->map(fn (Trip $trip) => $this->validDurationMinutes($trip))
                    ->filter(fn ($duration) => $duration !== null)
                    ->values();

                if ($durations->isEmpty()) {
                    return null;
                }

                return array_merge($this->publicVariantDescriptor($variantDescriptors->get($variantId)), [
                    'completed_trips' => $variantTrips->count(),
                    'valid_duration_trips' => $durations->count(),
                    'average_duration_minutes' => round((float) $durations->avg(), 1),
                    'minimum_duration_minutes' => round((float) $durations->min(), 1),
                    'maximum_duration_minutes' => round((float) $durations->max(), 1),
                ]);
            })
            ->filter()
            ->sortBy(fn (array $row) => sprintf(
                '%010d-%d',
                $variantDescriptors->get($row['route_variant_id'])['route_order'],
                $row['direction'] === 'outbound' ? 0 : 1
            ))
            ->values()
            ->all();
    }

    private function recordedStopActivity(
        array $routeIds,
        Carbon $periodStart,
        Carbon $periodEnd,
        Collection $variantDescriptors
    ): array {
        if ($routeIds === [] || $variantDescriptors->isEmpty()) {
            return [];
        }

        return TripPassengerEvent::query()
            ->from('trip_passenger_events as events')
            ->join('trips as trips', function ($join): void {
                $join->on('trips.id', '=', 'events.trip_id')
                    ->on('trips.route_id', '=', 'events.route_id');
            })
            ->join('route_variants as variants', function ($join): void {
                $join->on('variants.id', '=', 'trips.route_variant_id')
                    ->on('variants.route_id', '=', 'trips.route_id');
            })
            ->leftJoin('route_variant_stops as stops', function ($join): void {
                $join->on('stops.id', '=', 'events.route_variant_stop_id')
                    ->on('stops.route_variant_id', '=', 'variants.id');
            })
            ->whereIn('events.route_id', $routeIds)
            ->whereIn('variants.id', $variantDescriptors->keys()->all())
            ->whereIn('trips.status', [
                TripStatus::ONGOING->value,
                TripStatus::COMPLETED->value,
                TripStatus::CANCELLED->value,
            ])
            ->whereIn('events.event_type', [
                TripPassengerEvent::TYPE_BOARDED,
                TripPassengerEvent::TYPE_ALIGHTED,
            ])
            ->whereBetween('events.recorded_at', [$periodStart, $periodEnd])
            ->groupBy([
                'events.route_id',
                'variants.id',
                'stops.id',
                'stops.name',
                'stops.sequence',
            ])
            ->get([
                'events.route_id',
                'variants.id as route_variant_id',
                'stops.id as route_variant_stop_id',
                'stops.name as stop_name',
                'stops.sequence as stop_sequence',
                DB::raw("SUM(CASE WHEN events.event_type = 'boarded' THEN events.passenger_delta ELSE 0 END) as recorded_boarded"),
                DB::raw("SUM(CASE WHEN events.event_type = 'alighted' THEN events.passenger_delta ELSE 0 END) as recorded_alighted"),
                DB::raw('COUNT(DISTINCT events.trip_id) as trips_recorded'),
                DB::raw('MAX(events.recorded_at) as latest_recorded_at'),
            ])
            ->map(function ($activity) use ($variantDescriptors): array {
                $variant = $variantDescriptors->get((int) $activity->route_variant_id);
                $isAttributed = $activity->route_variant_stop_id !== null;
                $latestActivity = $activity->latest_recorded_at
                    ? Carbon::parse($activity->latest_recorded_at, 'UTC')
                        ->setTimezone(self::OPERATIONS_TIMEZONE)
                    : null;

                return array_merge($this->publicVariantDescriptor($variant), [
                    'route_variant_stop_id' => $isAttributed
                        ? (int) $activity->route_variant_stop_id
                        : null,
                    'stop_name' => $isAttributed ? $activity->stop_name : 'Unattributed',
                    'sequence' => $isAttributed ? (int) $activity->stop_sequence : null,
                    'sequence_label' => $isAttributed ? (string) $activity->stop_sequence : '--',
                    'is_attributed' => $isAttributed,
                    'recorded_boarded' => (int) $activity->recorded_boarded,
                    'recorded_alighted' => (int) $activity->recorded_alighted,
                    'passenger_movements' => (int) $activity->recorded_boarded
                        + (int) $activity->recorded_alighted,
                    'trips_recorded' => (int) $activity->trips_recorded,
                    'latest_activity_at' => $latestActivity?->toIso8601String(),
                    'latest_activity_label' => $latestActivity?->format('M j, g:i A') ?? 'No data',
                ]);
            })
            ->sortBy(function (array $row) use ($variantDescriptors): string {
                $variant = $variantDescriptors->get($row['route_variant_id']);
                $sequence = $row['sequence'] ?? 999999;

                return sprintf(
                    '%010d-%d-%06d',
                    $variant['route_order'],
                    $row['direction'] === 'outbound' ? 0 : 1,
                    $sequence
                );
            })
            ->values()
            ->all();
    }

    private function publicVariantDescriptor(array $variant): array
    {
        return [
            'route_variant_id' => $variant['id'],
            'route_id' => $variant['route_id'],
            'route_name' => $variant['route_name'],
            'route_color' => $variant['route_color'],
            'direction' => $variant['direction'],
            'direction_label' => $variant['direction_label'],
            'display_label' => $variant['display_label'],
            'origin_name' => $variant['origin_name'],
            'destination_name' => $variant['destination_name'],
        ];
    }

    private function validDurationMinutes(Trip $trip): ?float
    {
        if (! $trip->started_at || ! $trip->ended_at) {
            return null;
        }

        $durationSeconds = $trip->ended_at->getTimestamp() - $trip->started_at->getTimestamp();

        return $durationSeconds >= 0 ? $durationSeconds / 60 : null;
    }

    private function minuteLabel(float $minutes): string
    {
        $formatted = fmod($minutes, 1.0) === 0.0
            ? (string) (int) $minutes
            : number_format($minutes, 1, '.', '');

        return $formatted . ' min';
    }

    private function incidents(array $routeIds, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        if ($routeIds === []) {
            return collect();
        }

        return Incident::query()
            ->with(['trip.route', 'trip.bus', 'driver'])
            ->whereHas('trip', fn ($query) => $query->whereIn('route_id', $routeIds))
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->whereBetween('reported_at', [$periodStart, $periodEnd])
                    ->orWhere(function ($fallback) use ($periodStart, $periodEnd) {
                        $fallback->whereNull('reported_at')
                            ->whereBetween('created_at', [$periodStart, $periodEnd]);
                    });
            })
            ->orderByDesc('reported_at')
            ->orderByDesc('created_at')
            ->get();
    }

    private function formatOperationalIncidents(Collection $incidents): array
    {
        return $incidents->map(function (Incident $incident): array {
            $route = $incident->trip?->route;
            $timestamp = $incident->reported_at ?? $incident->created_at;

            return [
                'source' => 'incident',
                'event_type' => ucwords((string) $incident->type),
                'status' => strtolower((string) $incident->status),
                'status_label' => $this->workflowStatusLabel((string) $incident->status),
                'bus_id' => $incident->trip?->bus?->plate_number ?? 'N/A',
                'driver_name' => $incident->driver
                    ? trim($incident->driver->first_name . ' ' . $incident->driver->last_name)
                    : 'Unknown Driver',
                'route' => $route?->name ?? 'N/A',
                'route_color' => $route?->color ?? '#64748b',
                'description' => $this->cleanDisplayText($incident->description ?: 'No description provided.'),
                'recorded_at_iso' => $timestamp?->copy()->utc()->toIso8601String(),
                'recorded_at' => $timestamp
                    ? $timestamp->copy()->setTimezone(self::OPERATIONS_TIMEZONE)->format('M j, g:i A')
                    : '--',
            ];
        })
            ->sortByDesc('recorded_at_iso')
            ->values()
            ->all();
    }

    private function workflowStatusLabel(string $status): string
    {
        return match (strtolower($status)) {
            'under_review' => 'Under review',
            'resolved' => 'Resolved',
            'reported' => 'Reported',
            default => 'Unknown',
        };
    }

    private function cleanDisplayText(string $value): string
    {
        $cleaned = str_replace(
            [
                'Ã¢â‚¬â€',
                'Ã¢â‚¬â€œ',
                'ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â',
                'â€”',
                'â€“',
                'Â·',
                'ï¿½',
            ],
            '-',
            $value
        );

        return trim(preg_replace('/\s+-\s+/', ' - ', $cleaned) ?? $cleaned);
    }

    private function scoreCsvLabel(?int $score): string
    {
        return $score === null ? 'Insufficient evidence' : $score . '%';
    }
}
