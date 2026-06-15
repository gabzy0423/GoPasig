<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bus;
use App\Models\Schedule;
use App\Models\ServiceAlert;
use App\Models\Route;
use Carbon\Carbon;
use App\Services\DashboardService;
use App\Services\RouteStatusService;
use App\Services\SchedulePeekService;

class CommuterController extends Controller
{
    /**
     * Show the commuter dashboard.
     */
    public function dashboard(
        DashboardService $dashboardService,
        RouteStatusService $routeStatusService,
        SchedulePeekService $schedulePeekService
    ) {
        $quickStatsArray = $dashboardService->getCommuterStats();
        $quickStats = (object) $quickStatsArray;

        // Active Routes Data
        $routes = Route::getAllCached();
        $activeBusesByRoute = Bus::where('status', 'active')->get()->groupBy('route_id');
        $schedulesByRoute = Schedule::get()->groupBy('route_id');
        $nowTimeString = now()->toTimeString();

        $activeRoutes = $routes->map(function ($route) use ($routeStatusService, $activeBusesByRoute, $schedulesByRoute, $nowTimeString) {
            $activeBusesOnRoute = $activeBusesByRoute->get($route->id, collect());
            $busesCount = $activeBusesOnRoute->count();
            
            // Calculate next ETA:
            // 1. If active buses exist, find the minimum ETA
            // 2. Otherwise, look up the next upcoming schedule today and compute minutes to departure
            $nextEta = 0;
            if ($activeBusesOnRoute->isNotEmpty()) {
                $nextEta = $activeBusesOnRoute->min('eta');
            } else {
                $routeSchedules = $schedulesByRoute->get($route->id, collect());
                $nextSched = $routeSchedules
                    ->where('departure_time', '>', $nowTimeString)
                    ->sortBy('departure_time')
                    ->first();
                if ($nextSched) {
                    $departure = Carbon::parse($nextSched->departure_time);
                    $nextEta = max(1, now()->diffInMinutes($departure));
                } else {
                    $nextEta = null; // default fallback if no schedules or active buses
                }
            }

            // Health Status: dynamic calculation via RouteStatusService
            $healthStatus = $routeStatusService->getCommuterRouteHealth($route, $activeBusesOnRoute);

            $routeSchedules = $schedulesByRoute->get($route->id, collect());
            $scheduledTrips = $routeSchedules->count();
            $completedTrips = $routeSchedules
                ->where('departure_time', '<=', $nowTimeString)
                ->count();

            return (object) [
                'route_name' => $route->name,
                'route_color' => $route->color ?: \App\Models\SystemSetting::get('default_route_color', '#003F87'),
                'health_status' => $healthStatus,
                'buses_on_route' => $busesCount,
                'next_eta_minutes' => $nextEta,
                'completed_trips' => $completedTrips,
                'scheduled_trips' => $scheduledTrips,
            ];
        });

        // Latest alerts: show 3 alerts
        $latestAlerts = ServiceAlert::where('status', 'active')
            ->latest('created_at')
            ->take(3)
            ->get()
            ->map(function ($alert) {
                return (object) [
                    'type' => $alert->type,
                    'title' => $alert->title,
                    'message' => $alert->message,
                    'affected_routes' => $alert->affected_routes ?: 'All Routes',
                    'created_at' => $alert->created_at->diffForHumans(),
                ];
            });

        // Schedule peek: via SchedulePeekService
        $schedulepeek = $schedulePeekService->getSchedulePeek();

        $routesData = Route::getAllCached()->map(function($r) {
            return [
                'name' => $r->name,
                'color' => $r->color ?: \App\Models\SystemSetting::get('default_route_color', '#003F87'),
                'coords' => $r->polyline_coordinates
            ];
        });

        return view('commuter.dashboard', [
            'activeBuses' => $quickStatsArray['active_buses'],
            'delayedBuses' => $quickStatsArray['delayed_buses'],
            'passengersToday' => $quickStatsArray['passengers_today'],
            'openAlerts' => $quickStatsArray['open_alerts'],
            'quickStats' => $quickStats,
            'activeRoutes' => $activeRoutes,
            'latestAlerts' => $latestAlerts,
            'schedulepeek' => $schedulepeek,
            'routesData' => $routesData,
        ]);
    }

    /**
     * Show tracker.
     */
    public function tracker()
    {
        return view('commuter.tracker.index');
    }

    /**
     * Show alerts.
     */
    public function alerts()
    {
        return view('commuter.alert.index');
    }

    /**
     * Show routes.
     */
    public function routes()
    {
        return view('commuter.routes.index');
    }

    /**
     * Show schedule.
     */
    public function schedule()
    {
        return view('commuter.schedule.index');
    }

    /**
     * Show stops.
     */
    public function stops()
    {
        return view('commuter.stops.index');
    }

    /**
     * Get active buses for commuter API.
     */
    public function busesApi()
    {
        $buses = Bus::where('status', 'active')->get(['plate_number', 'lat', 'lng', 'status', 'next_stop', 'eta']);
        return response()->json($buses);
    }
}
