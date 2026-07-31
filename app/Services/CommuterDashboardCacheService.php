<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\ServiceAlert;
use App\Models\Stop;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class CommuterDashboardCacheService
{
    private const CACHE_TTL_SECONDS = 30;

    public static function getActiveBuses()
    {
        return Cache::remember('commuter_active_buses_list', 15, function () {
            return Bus::with('route')
                ->where('status', 'active')
                ->whereHas('route', fn ($query) => $query->publicCommuterActiveService())
                ->get();
        });
    }

    public function dashboardData(): array
    {
        return Cache::remember('commuter_dashboard_aggregate', now()->addSeconds(self::CACHE_TTL_SECONDS), function () {
            $routes = Route::publicCommuterVisible()->with('stops')->orderBy('id')->get();
            $activeBuses = Bus::with('route')
                ->where('status', 'active')
                ->whereHas('route', fn ($query) => $query->publicCommuterActiveService())
                ->orderBy('eta')
                ->get();
            $schedules = Schedule::orderBy('departure_time')->get();
            $activeAlerts = ServiceAlert::activeAlerts()->publicCommuterVisible()->latest('created_at')->get();

            $activeBusesByRoute = $activeBuses->groupBy('route_id');
            $schedulesByRoute = $schedules->groupBy('route_id');
            $nowTimeString = now()->toTimeString();
            $defaultRouteColor = config('brand.route_color_default', '#003F87');
            $unassignedRouteColor = config('brand.route_color_unassigned', '#888780');
            $defaultDelayThreshold = Bus::getDelayThreshold();
            $labelFull = SystemSetting::get('label_bus_status_full', 'Full');
            $labelDelayed = SystemSetting::get('label_bus_status_delayed', 'Delayed');
            $labelOnTime = SystemSetting::get('label_bus_status_on_time', 'On Time');
            $etaProvenanceService = app(CommuterEtaProvenanceService::class);
            $routeStatusService = app(RouteStatusService::class);

            $activeRoutes = $routes->map(function ($route) use ($activeBusesByRoute, $schedulesByRoute, $nowTimeString, $activeAlerts, $defaultRouteColor, $defaultDelayThreshold, $etaProvenanceService, $routeStatusService) {
                $routeBuses = $activeBusesByRoute->get($route->id, collect());
                $routeSchedules = $schedulesByRoute->get($route->id, collect());
                $nextEta = null;
                $nextEtaProvenance = null;

                if ($routeBuses->isNotEmpty()) {
                    $nextBus = $routeBuses->sortBy('eta')->first();
                    $nextEtaProvenance = $nextBus ? $etaProvenanceService->forBus($nextBus) : null;
                    $nextEta = $nextEtaProvenance?->minutes;
                } else {
                    $nextSched = $routeSchedules
                        ->where('departure_time', '>', $nowTimeString)
                        ->sortBy('departure_time')
                        ->first();

                    if ($nextSched) {
                        $nextEta = max(1, now()->diffInMinutes(Carbon::parse($nextSched->departure_time)));
                    }
                }

                return (object) [
                    'route_name' => $route->name,
                    'route_color' => $route->color ?: $defaultRouteColor,
                    'health_status' => $routeStatusService->getCommuterRouteHealth($route, $routeBuses, $activeAlerts, $defaultDelayThreshold),
                    'buses_on_route' => $routeBuses->count(),
                    'next_eta_minutes' => $nextEta,
                    'next_eta_provenance_state' => $nextEtaProvenance?->state,
                    'next_eta_label' => $nextEtaProvenance?->label ?: ($nextEta !== null ? $nextEta . 'm' : 'TBA'),
                    'completed_trips' => $routeSchedules->where('departure_time', '<=', $nowTimeString)->count(),
                    'scheduled_trips' => $routeSchedules->count(),
                ];
            });

            $schedulePeek = $routes->map(function ($route) use ($schedulesByRoute, $defaultRouteColor) {
                $routeSchedules = $schedulesByRoute->get($route->id, collect());
                $firstSchedule = $routeSchedules->first();
                $lastSchedule = $routeSchedules->last();
                $serviceStatus = 'In service';
                $minsUntilStart = 0;

                if ($route->status === 'Suspended') {
                    $serviceStatus = 'Suspended';
                } elseif ($firstSchedule && $lastSchedule) {
                    $firstTripTime = Carbon::parse($firstSchedule->departure_time);
                    $lastTripTime = Carbon::parse($lastSchedule->departure_time);
                    $currentTime = Carbon::now();

                    if ($currentTime->lessThan($firstTripTime)) {
                        $minsUntilStart = max(1, $currentTime->diffInMinutes($firstTripTime));
                        $serviceStatus = "Starts in {$minsUntilStart} min";
                    } elseif ($currentTime->greaterThan($lastTripTime)) {
                        $serviceStatus = 'Service ended';
                    }
                } else {
                    $serviceStatus = 'No service';
                }

                return (object) [
                    'route_name' => $route->name,
                    'route_color' => $route->color ?: $defaultRouteColor,
                    'first_trip' => $firstSchedule ? Carbon::parse($firstSchedule->departure_time)->format('g:i A') : 'No schedules',
                    'last_trip' => $lastSchedule ? Carbon::parse($lastSchedule->departure_time)->format('g:i A') : 'No schedules',
                    'service_status' => $serviceStatus,
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
                'route.buses' => fn($query) => $query->where('status', 'active'),
            ])
                ->whereHas('route', fn ($query) => $query->publicCommuterVisible())
                ->orderBy('route_id')
                ->orderBy('sequence')
                ->get();
        });
    }

}
