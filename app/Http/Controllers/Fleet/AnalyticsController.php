<?php

namespace App\Http\Controllers\Fleet;

use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\ColorPalette;
use App\Models\Route;
use App\Models\TimeSlotConfiguration;
use App\Models\Trip;
use App\Models\TripPassengerEvent;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AnalyticsController extends Controller
{
    private const OPERATIONS_TIMEZONE = 'Asia/Manila';

    public function index(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::today(self::OPERATIONS_TIMEZONE)->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::today(self::OPERATIONS_TIMEZONE)->toDateString());
        $selectedRoute = $request->input('route_id', 'all');
        $reportType = $request->input('report_type', 'daily');
        $availableRoutes = $this->availableRoutes();
        $selectedRoute = $this->resolveSelectedRoute($selectedRoute, $availableRoutes);

        $analyticsData = $this->fetchSummaryData($startDate, $endDate, $selectedRoute);

        return view('fleet.analytics.index', array_merge([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedRoute' => $selectedRoute,
            'reportType' => $reportType,
            'availableRoutes' => $availableRoutes->values()->all(),
            'lastUpdatedTime' => now(self::OPERATIONS_TIMEZONE)->format('g:i A'),
        ], $analyticsData));
    }

    public function getAnalyticsData(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::today(self::OPERATIONS_TIMEZONE)->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::today(self::OPERATIONS_TIMEZONE)->toDateString());
        $selectedRoute = $this->resolveSelectedRoute($request->input('route_id', 'all'), $this->availableRoutes());

        $data = $this->fetchSummaryData($startDate, $endDate, $selectedRoute);
        $data['lastUpdatedTime'] = now(self::OPERATIONS_TIMEZONE)->format('g:i A');

        return response()->json($data);
    }

    public function exportCsv(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::today(self::OPERATIONS_TIMEZONE)->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::today(self::OPERATIONS_TIMEZONE)->toDateString());
        $selectedRoute = $this->resolveSelectedRoute($request->input('route_id', 'all'), $this->availableRoutes());

        $data = $this->fetchSummaryData($startDate, $endDate, $selectedRoute);
        $metric = $data['metricSummary'];

        $rows = [];
        $rows[] = ['GoPasig Fleet Actual Operations Report'];
        $rows[] = ['Period', $startDate . ' to ' . $endDate];
        $rows[] = ['Generated At', now(self::OPERATIONS_TIMEZONE)->format('Y-m-d H:i:s') . ' Asia/Manila'];
        $rows[] = [];

        $rows[] = ['=== SUMMARY METRICS ==='];
        $rows[] = ['Recorded Boarded', $metric->total_passengers];
        $rows[] = ['Completed Trips', $metric->trips_completed];
        $rows[] = ['Avg Boarded / Completed Trip', $metric->avg_per_trip];
        $rows[] = ['Fleet Utilization', $metric->utilization_rate];
        $rows[] = ['Busiest Official Route', $metric->busiest_route];
        $rows[] = ['Peak Boarding Slot', $metric->peak_hour];
        $rows[] = [];

        $rows[] = ['=== ROUTE SUMMARY ==='];
        $rows[] = ['Route Name', 'Recorded Boarded'];
        foreach ($data['routeSummary'] as $route) {
            $rows[] = [$route->route_name, $route->total_passengers];
        }
        $rows[] = [];

        $rows[] = ['=== BUS ACTUAL OPERATIONS LOG ==='];
        $rows[] = ['Bus ID / Plate', 'Route', 'Trips Run', 'Recorded Boarded', 'Peak Load', 'Capacity', 'Utilization %', 'Status'];
        foreach ($data['busLogs'] as $bus) {
            $rows[] = [
                $bus->bus_id,
                $bus->assigned_route,
                $bus->trips_completed,
                $bus->total_passengers,
                $bus->peak_load,
                $bus->capacity,
                $bus->utilization_rate . '%',
                $bus->status,
            ];
        }
        $rows[] = [];
        $rows[] = ['=== DISPATCH RECOMMENDATIONS ==='];
        $rows[] = ['Status', 'Standby'];
        $rows[] = ['Reason', 'Dispatch recommendations are deferred until Dispatch Intelligence is fully aligned.'];

        $csvContent = implode("\n", array_map(function (array $row): string {
            return implode(',', array_map(fn ($value) => '"' . str_replace('"', '""', (string) $value) . '"', $row));
        }, $rows));

        return response()->streamDownload(function () use ($csvContent): void {
            echo $csvContent;
        }, 'fleet-actual-operations-report-' . $startDate . '-to-' . $endDate . '.csv', [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="fleet-actual-operations-report-' . $startDate . '-to-' . $endDate . '.csv"',
        ]);
    }

    public function fetchSummaryData($startDate, $endDate, $selectedRoute): array
    {
        [$periodStart, $periodEnd] = $this->periodBounds($startDate, $endDate);
        $routes = $this->availableRoutes();
        $selectedRoute = $this->resolveSelectedRoute($selectedRoute, $routes);
        $routeIds = $selectedRoute === 'all'
            ? $routes->pluck('id')->all()
            : [(int) $selectedRoute];
        $visibleRoutes = $selectedRoute === 'all'
            ? $routes
            : $routes->where('id', (int) $selectedRoute)->values();
        $colorPalette = ColorPalette::getColors('analytics');

        $completedTrips = $this->completedTrips($routeIds, $periodStart, $periodEnd)->get();
        $operationalTrips = $this->operationalTrips($routeIds, $periodStart, $periodEnd)->get();
        $boardedEvents = $this->boardedEvents($routeIds, $periodStart, $periodEnd)->get();

        $totalPassengers = (int) $boardedEvents->sum('passenger_delta');
        $tripsCompleted = $completedTrips->count();
        $avgPerTrip = $tripsCompleted > 0 ? round($totalPassengers / $tripsCompleted, 1) : 0;
        $totalBuses = Bus::count();
        $deployedBuses = $operationalTrips->pluck('bus_id')->filter()->unique()->count();
        $fleetUtil = $totalBuses > 0 ? round(($deployedBuses / $totalBuses) * 100) : 0;

        $routeSummary = $this->routeSummary($visibleRoutes, $boardedEvents, $colorPalette);
        $busiestRoute = collect($routeSummary)->sortByDesc('total_passengers')->first();
        $busiestRouteName = $busiestRoute && $busiestRoute->total_passengers > 0 ? $busiestRoute->route_name : 'No data';
        $busiestRouteCount = $busiestRoute ? number_format((int) $busiestRoute->total_passengers) : '0';

        $hourlyRidership = $this->hourlyRidership($visibleRoutes, $boardedEvents, $colorPalette);

        return [
            'metricSummary' => (object) [
                'total_passengers' => number_format($totalPassengers),
                'trips_completed' => $tripsCompleted,
                'avg_per_trip' => $avgPerTrip,
                'utilization_rate' => $fleetUtil . '%',
                'busiest_route' => $busiestRouteName,
                'busiest_route_count' => $busiestRouteCount,
                'peak_hour' => $this->peakBoardingSlot($boardedEvents),
            ],
            'routeSummary' => $routeSummary,
            'hourlyRidership' => $hourlyRidership,
            'busLogs' => $this->busLogs($operationalTrips, $boardedEvents, $visibleRoutes, $colorPalette, $selectedRoute),
            'dispatchRecommendations' => [],
            'dispatchRecommendationStatus' => [
                'status' => 'standby',
                'message' => 'Dispatch recommendations are on standby until Dispatch Intelligence is fully aligned.',
            ],
        ];
    }

    private function availableRoutes(): Collection
    {
        return Route::query()
            ->publicCommuterActiveService()
            ->get(['id', 'name', 'color'])
            ->map(fn (Route $route): array => [
                'id' => (int) $route->id,
                'name' => $route->name,
                'color' => $route->color,
            ])
            ->values();
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

    private function completedTrips(array $routeIds, Carbon $periodStart, Carbon $periodEnd): Builder
    {
        return Trip::query()
            ->whereIn('route_id', $routeIds)
            ->where('status', TripStatus::COMPLETED->value)
            ->whereBetween('ended_at', [$periodStart, $periodEnd]);
    }

    private function operationalTrips(array $routeIds, Carbon $periodStart, Carbon $periodEnd): Builder
    {
        return Trip::query()
            ->whereIn('route_id', $routeIds)
            ->whereIn('status', [TripStatus::COMPLETED->value, TripStatus::ONGOING->value])
            ->whereNotNull('bus_id')
            ->where(function ($query) use ($periodStart, $periodEnd): void {
                $query->where(function ($completed) use ($periodStart, $periodEnd): void {
                    $completed->where('status', TripStatus::COMPLETED->value)
                        ->whereBetween('ended_at', [$periodStart, $periodEnd]);
                })->orWhere(function ($ongoing) use ($periodStart, $periodEnd): void {
                    $ongoing->where('status', TripStatus::ONGOING->value)
                        ->whereBetween('started_at', [$periodStart, $periodEnd]);
                });
            });
    }

    private function boardedEvents(array $routeIds, Carbon $periodStart, Carbon $periodEnd): Builder
    {
        return TripPassengerEvent::query()
            ->whereIn('route_id', $routeIds)
            ->where('event_type', TripPassengerEvent::TYPE_BOARDED)
            ->whereBetween('recorded_at', [$periodStart, $periodEnd]);
    }

    private function routeSummary(Collection $routes, Collection $boardedEvents, array $colorPalette): array
    {
        return $routes->values()
            ->map(function (array $route, int $index) use ($boardedEvents, $colorPalette): object {
                return (object) [
                    'route_name' => $route['name'],
                    'total_passengers' => (int) $boardedEvents
                        ->where('route_id', $route['id'])
                        ->sum('passenger_delta'),
                    'color' => $route['color'] ?: ($colorPalette[$index % count($colorPalette)] ?? '#64748b'),
                ];
            })
            ->sortByDesc('total_passengers')
            ->values()
            ->all();
    }

    private function hourlyRidership(Collection $routes, Collection $boardedEvents, array $colorPalette): array
    {
        $slots = TimeSlotConfiguration::where('is_active', true)->orderBy('order')->get();
        if ($slots->isEmpty()) {
            return [];
        }

        return $slots->flatMap(function (TimeSlotConfiguration $slot) use ($routes, $boardedEvents, $colorPalette): array {
            return $routes->values()->map(function (array $route, int $index) use ($slot, $boardedEvents, $colorPalette): array {
                $count = $boardedEvents
                    ->where('route_id', $route['id'])
                    ->filter(fn (TripPassengerEvent $event): bool => $this->eventInSlot($event, $slot))
                    ->sum('passenger_delta');

                return [
                    'route' => $route['name'],
                    'hour' => $slot->time_slot_display,
                    'count' => (int) $count,
                    'color' => $route['color'] ?: ($colorPalette[$index % count($colorPalette)] ?? '#64748b'),
                ];
            })->all();
        })->values()->all();
    }

    private function eventInSlot(TripPassengerEvent $event, TimeSlotConfiguration $slot): bool
    {
        if (! $event->recorded_at) {
            return false;
        }

        $minute = Carbon::instance($event->recorded_at)->setTimezone(self::OPERATIONS_TIMEZONE)->hour * 60
            + Carbon::instance($event->recorded_at)->setTimezone(self::OPERATIONS_TIMEZONE)->minute;
        $start = $this->minutesFromTime((string) $slot->start_time);
        $end = $this->minutesFromTime((string) $slot->end_time);

        return $minute >= $start && $minute < $end;
    }

    private function peakBoardingSlot(Collection $boardedEvents): string
    {
        if ($boardedEvents->isEmpty()) {
            return 'No data';
        }

        $slots = TimeSlotConfiguration::where('is_active', true)->orderBy('order')->get();
        $peak = $slots
            ->map(fn (TimeSlotConfiguration $slot): array => [
                'label' => $slot->time_slot_display,
                'count' => (int) $boardedEvents
                    ->filter(fn (TripPassengerEvent $event): bool => $this->eventInSlot($event, $slot))
                    ->sum('passenger_delta'),
            ])
            ->sortByDesc('count')
            ->first();

        return $peak && $peak['count'] > 0 ? $peak['label'] : 'No data';
    }

    private function busLogs(Collection $operationalTrips, Collection $boardedEvents, Collection $routes, array $colorPalette, string $selectedRoute): array
    {
        $busIds = $operationalTrips->pluck('bus_id')
            ->merge($boardedEvents->pluck('bus_id'))
            ->filter()
            ->unique()
            ->values();

        if ($busIds->isEmpty()) {
            return [];
        }

        $buses = Bus::with('route')->whereIn('id', $busIds)->orderBy('plate_number')->get();
        $routesById = $routes->keyBy('id');

        return $buses->map(function (Bus $bus) use ($operationalTrips, $boardedEvents, $routesById, $colorPalette): object {
            $busTrips = $operationalTrips->where('bus_id', $bus->id);
            $latestTrip = $busTrips->sortByDesc(fn (Trip $trip): int => $this->tripActivityTime($trip)?->timestamp ?? 0)->first();
            $routeId = $latestTrip?->route_id;
            $route = $routeId ? $routesById->get((int) $routeId) : null;
            $recordedBoarded = (int) $boardedEvents->where('bus_id', $bus->id)->sum('passenger_delta');
            $peakLoad = (int) ($busTrips->max('peak_passengers') ?? 0);
            $capacity = (int) $bus->capacity;
            $utilization = $capacity > 0 ? min(100, (int) round(($peakLoad / $capacity) * 100)) : 0;
            $ongoingTrip = $busTrips->firstWhere('status', TripStatus::ONGOING->value);

            return (object) [
                'bus_id' => $bus->plate_number,
                'assigned_route' => $route['name'] ?? 'Unassigned',
                'route_color' => $route['color'] ?? ($colorPalette[0] ?? '#64748b'),
                'trips_completed' => $busTrips->count(),
                'total_passengers' => $recordedBoarded,
                'peak_load' => $peakLoad,
                'capacity' => $capacity,
                'utilization_rate' => $utilization,
                'status' => $this->busStatusLabel($bus, $ongoingTrip),
            ];
        })
            ->sort(function (object $left, object $right): int {
                return [$right->trips_completed, $right->total_passengers, $right->peak_load, $left->bus_id]
                    <=> [$left->trips_completed, $left->total_passengers, $left->peak_load, $right->bus_id];
            })
            ->values()
            ->all();
    }

    private function tripActivityTime(Trip $trip): ?CarbonInterface
    {
        return $trip->ended_at ?? $trip->started_at ?? $trip->dispatched_at;
    }

    private function busStatusLabel(Bus $bus, ?Trip $ongoingTrip): string
    {
        if ($ongoingTrip && in_array((string) $bus->status, ['active', 'operating'], true)) {
            return 'Operating';
        }

        return match ((string) $bus->status) {
            'ready' => 'Ready',
            'active', 'operating' => 'Standby',
            'maintenance' => 'Maintenance',
            'breakdown' => 'Breakdown',
            'inactive' => 'Inactive',
            default => ucfirst((string) $bus->status),
        };
    }

    private function minutesFromTime(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return ($hour * 60) + $minute;
    }
}
