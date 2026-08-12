<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\ServiceAlert;
use App\Models\Stop;
use App\Models\SystemSetting;
use App\Models\Trip;
use App\Enums\TripStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

class CommuterDashboardCacheService
{
    private const CACHE_TTL_SECONDS = 30;

    public static function getActiveBuses()
    {
        return Cache::remember('commuter_active_buses_list', 15, function () {
            return Bus::with('route')
                ->whereIn('status', Bus::commuterServiceStatuses())
                ->whereHas('route', fn ($query) => $query->publicCommuterActiveService())
                ->whereHas('trips', fn ($query) => $query->where('status', TripStatus::ONGOING->value))
                ->get();
        });
    }

    public function dashboardData(): array
    {
        return Cache::remember('commuter_dashboard_aggregate', now()->addSeconds(self::CACHE_TTL_SECONDS), function () {
            $today = Carbon::today('Asia/Manila')->toDateString();
            $routes = Route::publicCommuterVisible()->with('stops')->orderBy('id')->get();
            $activeBuses = self::getActiveBuses()->sortBy('eta')->values();
            $schedules = Schedule::whereDate('service_date', $today)
                ->orderBy('departure_time')
                ->get();
            $completedTripsByRoute = Trip::where('status', TripStatus::COMPLETED->value)
                ->whereDate('ended_at', $today)
                ->selectRaw('route_id, COUNT(*) as completed_count')
                ->groupBy('route_id')
                ->pluck('completed_count', 'route_id');
            $activeAlerts = ServiceAlert::activeAlerts()->publicCommuterVisible()->latest('created_at')->get();

            $activeBusesByRoute = $activeBuses->groupBy('route_id');
            $schedulesByRoute = $schedules->groupBy('route_id');
            $defaultRouteColor = config('brand.route_color_default', '#003F87');
            $unassignedRouteColor = config('brand.route_color_unassigned', '#888780');
            $defaultDelayThreshold = Bus::getDelayThreshold();
            $labelFull = SystemSetting::get('label_bus_status_full', 'Full');
            $labelDelayed = SystemSetting::get('label_bus_status_delayed', 'Delayed');
            $labelOnTime = SystemSetting::get('label_bus_status_on_time', 'On Time');
            $etaProvenanceService = app(CommuterEtaProvenanceService::class);
            $routeStatusService = app(RouteStatusService::class);
            $routeServiceScheduleEvaluator = app(RouteServiceScheduleEvaluator::class);

            $activeRoutes = $routes->map(function ($route) use ($activeBusesByRoute, $schedulesByRoute, $completedTripsByRoute, $activeAlerts, $defaultRouteColor, $defaultDelayThreshold, $etaProvenanceService, $routeStatusService) {
                $routeBuses = $activeBusesByRoute->get($route->id, collect());
                $routeSchedules = $schedulesByRoute->get($route->id, collect());
                $nextEta = null;
                $nextEtaProvenance = null;

                if ($routeBuses->isNotEmpty()) {
                    $nextBus = $routeBuses->sortBy('eta')->first();
                    $nextEtaProvenance = $nextBus ? $etaProvenanceService->forBus($nextBus) : null;
                    $nextEta = $nextEtaProvenance?->minutes;
                }

                return (object) [
                    'route_name' => $route->name,
                    'route_color' => $route->color ?: $defaultRouteColor,
                    'health_status' => $routeStatusService->getCommuterRouteHealth($route, $routeBuses, $activeAlerts, $defaultDelayThreshold),
                    'buses_on_route' => $routeBuses->count(),
                    'next_eta_minutes' => $nextEta,
                    'next_eta_provenance_state' => $nextEtaProvenance?->state,
                    'next_eta_label' => $nextEtaProvenance?->label ?: ($nextEta !== null ? $nextEta . 'm' : 'TBA'),
                    'completed_trips' => (int) ($completedTripsByRoute[$route->id] ?? 0),
                    'scheduled_trips' => $routeSchedules->count(),
                ];
            });

            $schedulePeek = $routes->map(function ($route) use ($defaultRouteColor, $routeServiceScheduleEvaluator) {
                $currentTime = Carbon::now('Asia/Manila');
                $serviceStatus = $route->status === 'Suspended'
                    ? [
                        'is_operating' => false,
                        'status_label' => 'Suspended',
                        'current_window' => null,
                        'next_window' => null,
                    ]
                    : $routeServiceScheduleEvaluator->statusForRoute($route, $currentTime);
                $serviceWindows = $routeServiceScheduleEvaluator->activeWindowsForRouteOn($route, $currentTime);
                $firstWindow = $serviceWindows->sortBy('first_trip_time')->first();
                $lastWindow = $serviceWindows->sortByDesc('last_trip_time')->first();
                $nextWindow = $serviceStatus['next_window'] ?? null;
                $minsUntilStart = $nextWindow
                    ? max(1, (int) ceil($currentTime->diffInMinutes($this->timeOnDate($currentTime, $nextWindow->first_trip_time), false)))
                    : 0;

                return (object) [
                    'route_name' => $route->name,
                    'route_color' => $route->color ?: $defaultRouteColor,
                    'first_trip' => $firstWindow ? $this->formatRouteServiceTime($firstWindow->first_trip_time) : 'No schedules',
                    'last_trip' => $lastWindow ? $this->formatRouteServiceTime($lastWindow->last_trip_time) : 'No schedules',
                    'service_status' => $serviceStatus['status_label'],
                    'mins_until_start' => $minsUntilStart,
                ];
            });

            $nearestBuses = $activeBuses->map(function ($bus) use ($unassignedRouteColor, $labelFull, $labelDelayed, $labelOnTime, $defaultDelayThreshold, $etaProvenanceService) {
                $capacity = max(1, (int) $bus->capacity);
                $fillRatio = $bus->passengers / $capacity;
                $threshold = $bus->route?->delay_threshold_minutes ?: $defaultDelayThreshold;

                if ($fillRatio > 0.8) {
                    $status = $labelFull;
                } elseif ($bus->eta >= $threshold) {
                    $status = $labelDelayed;
                } else {
                    $status = $labelOnTime;
                }

                $etaProvenance = $etaProvenanceService->forBus($bus);

                return (object) [
                    'id' => $bus->id,
                    'plate' => $bus->plate_number,
                    'status' => $status,
                    'route_name' => $bus->route?->name ?? 'Unassigned',
                    'route_color' => $bus->route?->color ?: $unassignedRouteColor,
                    'next_stop' => $bus->next_stop ?: 'Terminal',
                    'eta_minutes' => $etaProvenance->minutes,
                    'eta_provenance_state' => $etaProvenance->state,
                    'eta_label' => $etaProvenance->label,
                    'eta_description' => $etaProvenance->description,
                    'eta_is_authoritative' => $etaProvenance->is_authoritative,
                    'onboard' => $bus->passengers,
                    'capacity' => $capacity,
                ];
            });

            return [
                'quickStats' => [
                    'active_buses' => $activeBuses->count(),
                    'delayed_buses' => $activeBuses->filter(fn($bus) => $bus->eta >= ($bus->route?->delay_threshold_minutes ?: $defaultDelayThreshold))->count(),
                    'passengers_today' => Schedule::whereHas('route', fn ($query) => $query->publicCommuterVisible())
                        ->whereDate('service_date', Carbon::today('Asia/Manila'))
                        ->sum('passengers'),
                    'open_alerts' => $activeAlerts->count(),
                ],
                'activeRoutes' => $activeRoutes,
                'latestAlerts' => $activeAlerts->take(3)->map(fn($alert) => (object) [
                    'type' => $alert->type,
                    'title' => $alert->title,
                    'message' => $alert->message,
                    'affected_routes' => $alert->affected_routes ?: 'All Routes',
                    'created_at' => $alert->created_at->diffForHumans(),
                ]),
                'schedulePeek' => $schedulePeek,
                'nearestBuses' => $nearestBuses,
                'routesData' => $routes->map(fn($route) => [
                    'name' => $route->name,
                    'color' => $route->color ?: $defaultRouteColor,
                    'coords' => $route->polyline_coordinates,
                ]),
            ];
        });
    }

    public function routeStops()
    {
        return Cache::remember('commuter_route_stops_aggregate', now()->addSeconds(self::CACHE_TTL_SECONDS), function () {
            return Stop::with([
                'route.stops',
                'route.schedules',
                'route.durations',
                'route.buses' => fn($query) => $query->whereIn('status', Bus::commuterServiceStatuses()),
            ])
                ->whereHas('route', fn ($query) => $query->publicCommuterVisible())
                ->orderBy('route_id')
                ->orderBy('sequence')
                ->get();
        });
    }

    private function formatRouteServiceTime(?string $time): string
    {
        if (! $time) {
            return 'No schedules';
        }

        return Carbon::createFromFormat('H:i:s', strlen($time) === 5 ? $time . ':00' : $time, 'Asia/Manila')
            ->format('g:i A');
    }

    private function timeOnDate(CarbonInterface $date, ?string $time): Carbon
    {
        $normalized = $time ? (strlen($time) === 5 ? $time . ':00' : substr($time, 0, 8)) : '00:00:00';

        return Carbon::instance($date)->copy()->timezone('Asia/Manila')->setTimeFromTimeString($normalized);
    }

}
