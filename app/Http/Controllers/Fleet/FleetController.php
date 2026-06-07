<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Bus;
use App\Models\Trip;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\ServiceAlert;
use App\Models\Incident;
use App\Models\Driver;
use App\Models\ColorPalette;
use App\Services\DashboardService;
use App\Services\RouteStatusService;

class FleetController extends Controller
{
    protected $dashboardService;
    protected $routeStatusService;

    public function __construct(DashboardService $dashboardService, RouteStatusService $routeStatusService)
    {
        $this->dashboardService = $dashboardService;
        $this->routeStatusService = $routeStatusService;
    }
    /**
     * Show the main Fleet Ops Dashboard.
     */
    public function dashboard()
    {
        $operator = Auth::user() ?? (object) ['name' => 'Dispatcher'];
        $now = Carbon::now('Asia/Manila');
        $start = Carbon::createFromTime(5, 0, 0, 'Asia/Manila');
        $end = Carbon::createFromTime(21, 0, 0, 'Asia/Manila');
        $inService = $now->between($start, $end);

        // Fetch initial data to render server-side for fast loading
        $data = $this->getOverviewDataArray();

        return view('fleet.dashboard', array_merge([
            'operator' => $operator,
            'inService' => $inService,
        ], $data));
    }

    /**
     * API endpoint to get refreshed overview data.
     */
    public function getOverviewData()
    {
        $data = $this->getOverviewDataArray();
        return response()->json($data);
    }

    /**
     * API endpoint to log a new incident.
     */
    public function submitIncident(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|min:3|max:255',
            'location' => 'required|string|max:255',
            'route_id' => 'required|integer|exists:routes,id',
            'severity' => 'required|string|in:Low,Medium,High',
        ]);

        $routeId = (int) $validated['route_id'];

        // Find an ongoing trip on this route to associate with the incident
        $trip = Trip::where('route_id', $routeId)->where('status', 'ongoing')->first();
        if (!$trip) {
            return response()->json([
                'success' => false,
                'message' => 'Walang aktibong biyahe (ongoing trip) sa rutang ito sa kasalukuyan.'
            ], 422);
        }

        // Determine incident type based on severity
        $type = 'Delay';
        if ($validated['severity'] === 'High') {
            $type = 'Breakdown';
        } elseif ($validated['severity'] === 'Low') {
            $type = 'Route Issue';
        }

        // Create incident in DB
        $incident = Incident::create([
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'type' => $type,
            'description' => $validated['title'] . ' at ' . $validated['location'],
            'status' => 'reported',
            'reported_at' => now(),
        ]);

        // If type is Breakdown, update bus status to maintenance
        if ($type === 'Breakdown' && $trip->bus) {
            $trip->bus->update(['status' => 'maintenance']);
        }

        // Log recent activity
        $user = Auth::user();
        $this->logActivity('Incident', 'Reported: ' . ($trip->bus ? $trip->bus->plate_number : 'Bus') . ' — ' . $type . ' (' . $incident->description . ') by ' . ($user->name ?? 'Dispatcher'));

        return response()->json([
            'success' => true,
            'message' => 'Incident successfully logged!',
            'incident' => $incident
        ]);
    }

    /**
     * API endpoint to resolve an incident.
     */
    public function resolveIncident(Request $request, $id)
    {
        // Strip custom prefix if sent from client-side
        if (is_string($id) && str_starts_with($id, 'db-')) {
            $id = (int) substr($id, 3);
        }

        $incident = Incident::with('trip.bus')->find((int) $id);
        if (!$incident) {
            return response()->json([
                'success' => false,
                'message' => 'Incident record not found.'
            ], 404);
        }

        $incident->update(['status' => 'resolved']);

        // Log activity
        $user = Auth::user();
        $plate = ($incident->trip && $incident->trip->bus) ? $incident->trip->bus->plate_number : 'Bus';
        $this->logActivity('Incident', 'Incident resolved: ' . $plate . ' — ' . $incident->type . ' by ' . ($user->name ?? 'Dispatcher'));

        return response()->json([
            'success' => true,
            'message' => 'Incident resolved successfully!'
        ]);
    }

    /**
     * API endpoint to post a new announcement.
     */
    public function submitAnnouncement(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|min:3|max:255',
            'message' => 'required|string',
        ]);

        // Create announcement / service alert
        $alert = ServiceAlert::create([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'severity' => 'info',
            'type' => 'Announcement',
            'affected_routes' => 'All Routes',
            'status' => 'active',
        ]);

        // Log activity
        $this->logActivity('Announcement', 'Announcement posted: "' . $validated['title'] . '"');

        return response()->json([
            'success' => true,
            'message' => 'Announcement successfully posted!',
            'alert' => $alert
        ]);
    }

    /**
     * Helper to log recent activity in the session or db if needed.
     * For now, we will let AppServiceProvider or local logs capture it.
     */
    protected function logActivity($type, $description)
    {
        // In Laravel, activities are dynamic from DB query. We insert them or let DB query read them.
        // Since getOverviewDataArray reads from dispatch_logs, incidents, maintenance_records, and service_alerts,
        // inserting to DB tables will automatically make them appear in the feed.
    }

    /**
     * Compile Overview Data Array (KPIs, active incidents, route health, compliance, activity).
     */
    protected function getOverviewDataArray()
    {
        $today = Carbon::today('Asia/Manila');

        $overviewKpi = $this->dashboardService->getFleetOverviewKpi();

        // ─── 2. ACTIVE INCIDENTS ──────────────────────────────────────────────
        $dbIncidents = DB::table('incidents')
            ->leftJoin('drivers', 'incidents.driver_id', '=', 'drivers.id')
            ->leftJoin('trips', 'incidents.trip_id', '=', 'trips.id')
            ->leftJoin('buses', 'trips.bus_id', '=', 'buses.id')
            ->leftJoin('routes', 'trips.route_id', '=', 'routes.id')
            ->whereIn('incidents.status', ['reported', 'under_review'])
            ->orderByDesc('incidents.reported_at')
            ->select(
                'incidents.id',
                'incidents.type',
                'incidents.description',
                'incidents.status',
                'incidents.reported_at',
                'buses.plate_number',
                'drivers.first_name as driver_first_name',
                'drivers.last_name as driver_last_name',
                'routes.name as route_name'
            )
            ->get()
            ->map(function ($incident) {
                $highSeverityTypes = ['Breakdown', 'Accident'];
                $mediumSeverityTypes = ['Delay', 'Route Issue'];
                if (in_array($incident->type, $highSeverityTypes)) {
                    $severity = 'High';
                } elseif (in_array($incident->type, $mediumSeverityTypes)) {
                    $severity = 'Medium';
                } else {
                    $severity = 'Low';
                }

                $plateNumber = $incident->plate_number ?? 'Unknown Bus';
                $driverName = trim(($incident->driver_first_name ?? '') . ' ' . ($incident->driver_last_name ?? ''));
                if (empty($driverName)) {
                    $driverName = 'Unknown Driver';
                }
                $routeName = $incident->route_name ?? 'Unknown Route';

                return [
                    'id' => 'db-' . $incident->id,
                    'severity' => $severity,
                    'title' => trim($plateNumber) . ' — ' . $incident->type . ': ' . $incident->description,
                    'location' => 'Active Route',
                    'affected_route' => $routeName,
                    'reported_at' => Carbon::parse($incident->reported_at)->toIso8601String(),
                ];
            })
            ->toArray();

        $activeIncidents = $dbIncidents;
        $overviewKpi['open_incidents'] = count($activeIncidents);

        // ─── 3. ROUTE HEALTH ──────────────────────────────────────────────────
        // Get route colors from database
        $routeColorsList = ColorPalette::where('usage', 'routes')->orderBy('order')->get();
        $routeColors = [];
        foreach ($routeColorsList as $idx => $colorEntry) {
            $routeColors[$idx + 1] = $colorEntry->hex_color;
        }

        $routes = Route::orderBy('id')->get();
        $routeHealth = $routes->map(function ($route) use ($today, $routeColors) {
            $routeBusIds = Trip::where('status', 'ongoing')
                ->where('route_id', $route->id)
                ->pluck('bus_id')
                ->toArray();
            $busesOnRoute = count($routeBusIds);

            $completedToday = Trip::where('status', 'completed')
                ->where('route_id', $route->id)
                ->whereDate('ended_at', $today)
                ->count();
            if ($completedToday === 0) {
                $completedToday = Trip::where('status', 'completed')
                    ->where('route_id', $route->id)
                    ->count();
            }

            $scheduledTrips = Schedule::where('route_id', $route->id)->count();
            $avgHeadway = $busesOnRoute > 0 ? round((16 * 60) / max($busesOnRoute * 4, 1), 1) : 0;

            $healthStatus = $this->routeStatusService->getFleetRouteHealth($route, $busesOnRoute);

            return [
                'route_id' => $route->id,
                'route_name' => $route->name . ' — ' . $route->description,
                'route_color' => $routeColors[$route->id] ?? '#888780',
                'health_status' => $healthStatus,
                'buses_on_route' => $busesOnRoute,
                'completed_trips' => $completedToday,
                'scheduled_trips' => $scheduledTrips,
                'avg_headway' => $avgHeadway,
            ];
        })->toArray();

        // ─── 4. SCHEDULE COMPLIANCE ───────────────────────────────────────────
        $totalSchedules = Schedule::count();
        $onTimeCount = Schedule::where('status', 'like', '%On time%')->count();
        $delayedCount = Schedule::where('status', 'like', '%delayed%')
            ->orWhere('status', 'like', '%Delayed%')
            ->count();
        $cancelledCount = Schedule::where('status', 'like', '%cancel%')->count();
        $compliancePct = $totalSchedules > 0 ? (int) round(($onTimeCount / $totalSchedules) * 100) : 100;

        $scheduleCompliance = [
            'compliance_pct' => $compliancePct,
            'on_time' => $onTimeCount,
            'delayed' => $delayedCount,
            'cancelled' => $cancelledCount,
            'trips_evaluated' => $totalSchedules,
            'as_of' => Carbon::now('Asia/Manila')->format('h:i A'),
        ];

        // ─── 5. RECENT ACTIVITY FEED ──────────────────────────────────────────
        $activities = collect();

        $dispatchLogs = DB::table('dispatch_logs')
            ->leftJoin('trips', 'dispatch_logs.trip_id', '=', 'trips.id')
            ->leftJoin('buses', 'trips.bus_id', '=', 'buses.id')
            ->leftJoin('routes', 'trips.route_id', '=', 'routes.id')
            ->leftJoin('users', 'dispatch_logs.dispatched_by', '=', 'users.id')
            ->orderByDesc('dispatch_logs.dispatched_at')
            ->limit(5)
            ->select(
                'dispatch_logs.dispatched_at as event_time',
                DB::raw("COALESCE(buses.plate_number, 'Bus') as plate_number"),
                DB::raw("COALESCE(routes.name, 'Route') as route_name"),
                DB::raw("COALESCE(users.name, 'Dispatcher') as dispatcher_name")
            )
            ->get()
            ->map(function ($log) {
                return [
                    'type' => 'Dispatch',
                    'description' => $log->plate_number . ' dispatched on ' . $log->route_name . ' by ' . $log->dispatcher_name,
                    'timestamp' => Carbon::parse($log->event_time)->diffForHumans(),
                    'sort_time' => $log->event_time,
                ];
            });
        $activities = $activities->merge($dispatchLogs);

        $incidentLogs = DB::table('incidents')
            ->leftJoin('trips', 'incidents.trip_id', '=', 'trips.id')
            ->leftJoin('buses', 'trips.bus_id', '=', 'buses.id')
            ->orderByDesc('incidents.reported_at')
            ->limit(5)
            ->select(
                'incidents.reported_at as event_time',
                'incidents.type',
                'incidents.description',
                'incidents.status',
                DB::raw("COALESCE(buses.plate_number, 'Bus') as plate_number")
            )
            ->get()
            ->map(function ($inc) {
                $prefix = $inc->status === 'resolved' ? 'Resolved' : 'Reported';
                return [
                    'type' => 'Incident',
                    'description' => $prefix . ': ' . $inc->plate_number . ' — ' . $inc->type . ' (' . $inc->description . ')',
                    'timestamp' => Carbon::parse($inc->event_time)->diffForHumans(),
                    'sort_time' => $inc->event_time,
                ];
            });
        $activities = $activities->merge($incidentLogs);

        $maintenanceLogs = DB::table('maintenance_records')
            ->leftJoin('buses', 'maintenance_records.bus_id', '=', 'buses.id')
            ->orderByDesc('maintenance_records.scheduled_at')
            ->limit(5)
            ->select(
                'maintenance_records.scheduled_at as event_time',
                'maintenance_records.type',
                'maintenance_records.status',
                DB::raw("COALESCE(buses.plate_number, 'Bus') as plate_number")
            )
            ->get()
            ->map(function ($rec) {
                return [
                    'type' => 'Maintenance',
                    'description' => $rec->plate_number . ' — ' . $rec->type . ' (' . $rec->status . ')',
                    'timestamp' => Carbon::parse($rec->event_time)->diffForHumans(),
                    'sort_time' => $rec->event_time,
                ];
            });
        $activities = $activities->merge($maintenanceLogs);

        $alertLogs = ServiceAlert::orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function ($alert) {
                return [
                    'type' => 'Announcement',
                    'description' => 'Alert: "' . $alert->title . '" (' . $alert->status . ')',
                    'timestamp' => Carbon::parse($alert->created_at)->diffForHumans(),
                    'sort_time' => $alert->created_at,
                ];
            });
        $activities = $activities->merge($alertLogs);

        $recentActivity = $activities
            ->sortByDesc('sort_time')
            ->values()
            ->take(10)
            ->map(function ($item) {
                unset($item['sort_time']);
                return $item;
            })
            ->toArray();

        if (empty($recentActivity)) {
            $recentActivity = [
                [
                    'type' => 'System',
                    'description' => 'No recent activity. Dispatch a bus to get started.',
                    'timestamp' => 'Just now',
                ]
            ];
        }

        // Ongoing Trips (for Log Incident dropdown)
        $ongoingTrips = Trip::where('status', 'ongoing')->with(['bus', 'driver'])->get()->map(function ($trip) {
            return [
                'id' => $trip->id,
                'plate_number' => $trip->bus ? $trip->bus->plate_number : 'Unknown Bus',
                'driver_name' => $trip->driver ? ($trip->driver->first_name . ' ' . $trip->driver->last_name) : 'Unknown Driver',
                'route_name' => $trip->route ? $trip->route->name : 'Unknown Route',
            ];
        })->toArray();

        // All Routes
        $allRoutes = Route::orderBy('id')->get()->map(function ($route) {
            return [
                'id' => $route->id,
                'name' => $route->name,
                'description' => $route->description,
                'polyline_coordinates' => $route->polyline_coordinates,
            ];
        })->toArray();

        // All Buses
        $allBuses = Bus::all()->map(function ($bus) {
            return [
                'id' => $bus->id,
                'plate_number' => $bus->plate_number,
                'status' => $bus->status,
                'lat' => $bus->lat,
                'lng' => $bus->lng,
                'eta' => $bus->eta,
            ];
        })->toArray();

        return [
            'overviewKpi' => $overviewKpi,
            'activeIncidents' => $activeIncidents,
            'routeHealth' => $routeHealth,
            'scheduleCompliance' => $scheduleCompliance,
            'recentActivity' => $recentActivity,
            'activeCount' => $overviewKpi['active_buses'],
            'openIncidents' => $overviewKpi['open_incidents'],
            'ongoingTrips' => $ongoingTrips,
            'routes' => $allRoutes,
            'buses' => $allBuses,
        ];
    }
}
