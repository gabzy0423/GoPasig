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
use App\Services\CommuterDashboardCacheService;

class CommuterController extends Controller
{
    /**
     * Show the commuter dashboard.
     */
    public function dashboard(
        DashboardService $dashboardService,
        RouteStatusService $routeStatusService,
        SchedulePeekService $schedulePeekService,
        CommuterDashboardCacheService $commuterDashboardCache
    ) {
        $dashboardData = $commuterDashboardCache->dashboardData();
        $quickStatsArray = $dashboardData['quickStats'];
        $quickStats = (object) $quickStatsArray;

        return view('commuter.dashboard', [
            'activeBuses' => $quickStatsArray['active_buses'],
            'delayedBuses' => $quickStatsArray['delayed_buses'],
            'passengersToday' => $quickStatsArray['passengers_today'],
            'openAlerts' => $quickStatsArray['open_alerts'],
            'quickStats' => $quickStats,
            'activeRoutes' => $dashboardData['activeRoutes'],
            'latestAlerts' => $dashboardData['latestAlerts'],
            'schedulepeek' => $dashboardData['schedulePeek'],
            'routesData' => $dashboardData['routesData'],
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
        $canonicalActiveBusIds = \App\Models\Trip::where('status', 'ongoing')
            ->whereHas('route', fn ($query) => $query->publicCommuterActiveService())
            ->pluck('bus_id')
            ->filter()
            ->unique();

        $buses = Bus::where('status', 'active')
            ->whereIn('id', $canonicalActiveBusIds)
            ->get(['plate_number', 'lat', 'lng', 'status', 'next_stop', 'eta']);

        return response()->json($buses);
    }
}
