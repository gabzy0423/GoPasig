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

    public function dashboardData(): array
    {
        return Cache::remember('commuter_dashboard_aggregate', now()->addSeconds(self::CACHE_TTL_SECONDS), function () {
            $routes = Route::with('stops')->orderBy('id')->get();
            $activeBuses = Bus::with('route')->where('status', 'active')->orderBy('eta')->get();
            $schedules = Schedule::orderBy('departure_time')->get();
            $activeAlerts = ServiceAlert::where('status', 'active')->latest('created_at')->get();

            $activeBusesByRoute = $activeBuses->groupBy('route_id');
            $schedulesByRoute = $schedules->groupBy('route_id');
            $nowTimeString = now()->toTimeString();
            $defaultRouteColor = config('brand.route_color_default', '#003F87');
            $unassignedRouteColor = config('brand.route_color_unassigned', '#888780');
            $defaultDelayThreshold = Bus::getDelayThreshold();
            $labelFull = SystemSetting::get('label_bus_status_full', 'Full');
            $labelDelayed = SystemSetting::get('label_bus_status_delayed', 'Delayed');
            $labelOnTime = SystemSetting::get('label_bus_status_on_time', 'On Time');

            $activeRoutes = $routes->map(function ($route) use ($activeBusesByRoute, $schedulesByRoute, $nowTimeString, $activeAlerts, $defaultRouteColor, $defaultDelayThreshold) {
                $routeBuses = $activeBusesByRoute->get($route->id, collect());
                $routeSchedules = $schedulesByRoute->get($route->id, collect());
                $nextEta = null;

                if ($routeBuses->isNotEmpty()) {
                    $nextEta = $routeBuses->min('eta');
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
                    'health_status' => $this->routeHealth($route, $routeBuses, $activeAlerts, $defaultDelayThreshold),
                    'buses_on_route' => $routeBuses->count(),
                    'next_eta_minutes' => $nextEta,
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

            $nearestBuses = $activeBuses->map(function ($bus) use ($unassignedRouteColor, $labelFull, $labelDelayed, $labelOnTime, $defaultDelayThreshold) {
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

                return (object) [
                    'id' => $bus->id,
                    'plate' => $bus->plate_number,
                    'status' => $status,
                    'route_name' => $bus->route?->name ?? 'Unassigned',
                    'route_color' => $bus->route?->color ?: $unassignedRouteColor,
                    'next_stop' => $bus->next_stop ?: 'Terminal',
                    'eta_minutes' => $bus->eta,
                    'onboard' => $bus->passengers,
                    'capacity' => $capacity,
                ];
            });

            return [
                'quickStats' => [
                    'active_buses' => $activeBuses->count(),
                    'delayed_buses' => $activeBuses->filter(fn ($bus) => $bus->eta >= ($bus->route?->delay_threshold_minutes ?: $defaultDelayThreshold))->count(),
                    'passengers_today' => $schedules->sum('passengers'),
                    'open_alerts' => $activeAlerts->count(),
                ],
                'activeRoutes' => $activeRoutes,
                'latestAlerts' => $activeAlerts->take(3)->map(fn ($alert) => (object) [
                    'type' => $alert->type,
                    'title' => $alert->title,
                    'message' => $alert->message,
                    'affected_routes' => $alert->affected_routes ?: 'All Routes',
                    'created_at' => $alert->created_at->diffForHumans(),
                ]),
                'schedulePeek' => $schedulePeek,
                'nearestBuses' => $nearestBuses,
                'routesData' => $routes->map(fn ($route) => [
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
                    'route.buses' => fn ($query) => $query->where('status', 'active'),
                ])
                ->orderBy('route_id')
                ->orderBy('sequence')
                ->get();
        });
    }

    private function routeHealth(Route $route, $routeBuses, $activeAlerts, int $defaultDelayThreshold): string
    {
        if ($route->status === 'Suspended') {
            return 'Disrupted';
        }

        $routeAlerts = $activeAlerts->filter(function ($alert) use ($route) {
            return (int) ($alert->route_id ?? 0) === (int) $route->id
                || ($alert->affected_routes && stripos($alert->affected_routes, $route->name) !== false);
        });

        if ($routeAlerts->contains(fn ($alert) => $alert->type === 'suspension')) {
            return 'Disrupted';
        }

        $hasDelayAlert = $routeAlerts->contains(fn ($alert) => in_array($alert->type, ['delay', 'maintenance'], true));
        $hasDelayedBus = $routeBuses->contains(fn ($bus) => $bus->eta >= ($bus->route?->delay_threshold_minutes ?: $defaultDelayThreshold));

        return $hasDelayAlert || $hasDelayedBus ? 'Minor Delay' : 'On Track';
    }
}
