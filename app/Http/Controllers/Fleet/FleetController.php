<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
use App\Models\SystemSetting;
use App\Models\IncidentType;
use App\Services\DashboardService;
use App\Services\RouteStatusService;
use App\Models\CommuterTrip;
use App\Models\CommuterSession;

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

        // Load operating hours from database settings (fallback: 05:00 – 21:00)
        $startTime = SystemSetting::get('service_start_time', '05:00');
        $endTime   = SystemSetting::get('service_end_time',   '21:00');
        [$startH, $startM] = array_map('intval', explode(':', $startTime));
        [$endH,   $endM]   = array_map('intval', explode(':', $endTime));
        $start = Carbon::createFromTime($startH, $startM, 0, 'Asia/Manila');
        $end   = Carbon::createFromTime($endH,   $endM,   0, 'Asia/Manila');
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

        // Determine incident type from configurable severity map
        $severityMap   = json_decode(SystemSetting::get('incident_severity_map', '{"Low":"Route Issue","Medium":"Delay","High":"Breakdown"}'), true);
        $type          = $severityMap[$validated['severity']] ?? 'Delay';

        // Create incident in DB
        $incident = Incident::create([
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'type' => $type,
            'description' => $validated['title'] . ' at ' . $validated['location'],
            'status' => 'reported',
            'reported_at' => now(),
        ]);

        // If the incident type is the configured breakdown type, flag bus for maintenance
        $breakdownType = SystemSetting::get('incident_breakdown_type', 'Breakdown');
        if ($type === $breakdownType && $trip->bus) {
            $trip->bus->lockToMaintenance();
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
     * Log a fleet activity with type, description, actor, and timestamp.
     *
     * ISSUE-024 fix: previously an empty stub that silently dropped all activity
     * calls. Now writes a structured log entry to Laravel's application log
     * (storage/logs/laravel.log) so incidents, announcements, and resolutions
     * are permanently recorded with the authenticated user context.
     */
    protected function logActivity(string $type, string $description): void
    {
        $userId = Auth::id();
        $userName = Auth::user()?->name ?? 'System';

        Log::info('[FleetActivity] ' . $type, [
            'type'        => $type,
            'description' => $description,
            'user_id'     => $userId,
            'user_name'   => $userName,
            'logged_at'   => now()->toDateTimeString(),
        ]);
    }

    /**
     * Compile Overview Data Array (KPIs, active incidents, route health, compliance, activity).
     */
    protected function getOverviewDataArray()
    {
        $today = Carbon::today('Asia/Manila');

        // Auto-detect offline buses (configurable threshold, default 2 minutes without GPS ping)
        $gpsOfflineThreshold = (int) SystemSetting::get('bus_gps_offline_threshold_minutes', 2);
        $activeTripBuses = Trip::where('status', 'ongoing')->pluck('bus_id')->toArray();
        $offlineBusesCheck = Bus::whereIn('id', $activeTripBuses)
            ->where('updated_at', '<', now()->subMinutes($gpsOfflineThreshold))
            ->get();

        foreach ($offlineBusesCheck as $busCheck) {
            $ongoingTrip = Trip::where('bus_id', $busCheck->id)->where('status', 'ongoing')->first();
            if ($ongoingTrip) {
                // Check if a signal lost incident already exists (including resolved ones within last hour)
                $alreadyLogged = Incident::where('trip_id', $ongoingTrip->id)
                    ->where('type', 'Delay')
                    ->where('description', 'like', '%signal lost%')
                    ->where('reported_at', '>=', now()->subHour())
                    ->exists();

                if (!$alreadyLogged) {
                    $location = $busCheck->next_stop ?: "Lat {$busCheck->lat}, Lng {$busCheck->lng}";
                    Incident::create([
                        'trip_id' => $ongoingTrip->id,
                        'driver_id' => $ongoingTrip->driver_id,
                        'type' => 'Delay', // maps to Medium severity
                        'description' => "Bus {$busCheck->plate_number} signal lost — last known position: {$location}",
                        'status' => 'reported',
                        'reported_at' => now(),
                    ]);
                }
            }
        }

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
            ->get();

        $severityMap = IncidentType::getSeverityMap();

        $dbIncidentsMapped = $dbIncidents->map(function ($incident) use ($severityMap) {
            $severity = $severityMap[$incident->type] ?? 'Low';

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

        $activeIncidents = $dbIncidentsMapped;
        $overviewKpi['open_incidents'] = count($activeIncidents);

        // ─── 3. ROUTE HEALTH ──────────────────────────────────────────────────
        // Get route colors from database
        $routeColorsList = ColorPalette::where('usage', 'routes')->orderBy('order')->get();
        $routeColors = [];
        foreach ($routeColorsList as $idx => $colorEntry) {
            $routeColors[$idx + 1] = $colorEntry->hex_color;
        }

        $routes = Route::getAllCached()->sortBy('id');

        // Pre-fetch counts to avoid N+1 queries in loop
        $ongoingCounts = Trip::where('status', 'ongoing')
            ->select('route_id', DB::raw('count(*) as count'))
            ->groupBy('route_id')
            ->pluck('count', 'route_id')
            ->toArray();

        $completedTodayCounts = Trip::where('status', 'completed')
            ->whereDate('ended_at', $today)
            ->select('route_id', DB::raw('count(*) as count'))
            ->groupBy('route_id')
            ->pluck('count', 'route_id')
            ->toArray();

        $completedAllCounts = Trip::where('status', 'completed')
            ->select('route_id', DB::raw('count(*) as count'))
            ->groupBy('route_id')
            ->pluck('count', 'route_id')
            ->toArray();

        $scheduledCounts = Schedule::select('route_id', DB::raw('count(*) as count'))
            ->groupBy('route_id')
            ->pluck('count', 'route_id')
            ->toArray();

        $schedulesByRoute = Schedule::orderBy('departure_time')
            ->get(['route_id', 'departure_time'])
            ->groupBy('route_id');

        $routeHealth = $routes->map(function ($route) use ($today, $routeColors, $ongoingCounts, $completedTodayCounts, $completedAllCounts, $scheduledCounts, $schedulesByRoute) {
            $busesOnRoute = $ongoingCounts[$route->id] ?? 0;

            $completedToday = $completedTodayCounts[$route->id] ?? 0;
            if ($completedToday === 0) {
                $completedToday = $completedAllCounts[$route->id] ?? 0;
            }

            $scheduledTrips = $scheduledCounts[$route->id] ?? 0;

            // Compute real headway as the average gap between consecutive scheduled departures.
            $departureTimes = $schedulesByRoute->get($route->id, collect())->pluck('departure_time');
            $avgHeadway = 0;
            if ($departureTimes->count() >= 2) {
                $gaps = [];
                for ($i = 1; $i < $departureTimes->count(); $i++) {
                    $prev = Carbon::parse($departureTimes[$i - 1]);
                    $curr = Carbon::parse($departureTimes[$i]);
                    $gapMinutes = $prev->diffInMinutes($curr, false);
                    if ($gapMinutes > 0) {
                        $gaps[] = $gapMinutes;
                    }
                }
                $avgHeadway = !empty($gaps) ? round(array_sum($gaps) / count($gaps), 1) : 0;
            }

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
        $onTimeCount   = Schedule::where('status', Schedule::STATUS_ON_TIME)->count();
        $delayedCount  = Schedule::where('status', Schedule::STATUS_DELAYED)->count();
        $cancelledCount = Schedule::where('status', Schedule::STATUS_CANCELLED)->count();
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
        $allRoutes = Route::getAllCached()->sortBy('id')->map(function ($route) {
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

    /**
     * API endpoint to get commuter trips.
     */
    public function getCommuterTrips(Request $request)
    {
        $query = CommuterTrip::with(['route', 'originStop', 'destinationStop'])
            ->where('is_simulated', false)
            ->latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('session_token', 'like', '%' . $search . '%');
        }

        if ($request->filled('route_id') && $request->input('route_id') !== 'all') {
            $query->where('route_id', $request->input('route_id'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $trips = $query->paginate(15);

        return response()->json($trips);
    }

    /**
     * API endpoint to get active commuter sessions.
     */
    public function getCommuterSessions(Request $request)
    {
        $query = CommuterSession::latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('session_token', 'like', '%' . $search . '%')
                  ->orWhere('ip_address', 'like', '%' . $search . '%');
            });
        }

        $sessions = $query->paginate(15);

        // Map session statuses dynamically
        $sessions->getCollection()->transform(function ($session) {
            $session->is_active = $session->expires_at && $session->expires_at->isFuture();
            return $session;
        });

        return response()->json($sessions);
    }
}
