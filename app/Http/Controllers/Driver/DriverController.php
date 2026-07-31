<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Driver;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Trip;
use App\Models\User;
use App\Models\Schedule;
use App\Models\ServiceAlert;
use App\Models\Stop;
use App\Services\GPSKalmanFilter;
use App\Services\DashboardService;
use App\Services\TripLogService;
use App\Models\SystemSetting;
use App\Models\GPSLog;
use App\Services\TelemetryProcessingService;
use Illuminate\Support\Facades\Log;

class DriverController extends Controller
{
    /**
     * Get dynamic fleet supervisor contact details from database.
     *
     * Selection order:
     * 1. First user with role fleet_manager
     * 2. Otherwise, first user with role admin
     * 3. Safe generic fallback without using arbitrary User::first()
     */
    private function getFleetSupervisorContact(): array
    {
        $fleetManager = User::where('role', 'fleet_manager')->first();
        if ($fleetManager) {
            return [
                'name' => $fleetManager->name,
                'role_label' => $fleetManager->displayRole(),
            ];
        }

        $admin = User::where('role', 'admin')->first();
        if ($admin) {
            return [
                'name' => $admin->name,
                'role_label' => $admin->displayRole(),
            ];
        }

        return [
            'name' => 'Fleet Operations Office',
            'role_label' => 'Supervisor',
        ];
    }
    /**
     * Redirect driver index to dashboard.
     */
    public function index()
    {
        return redirect()->route('driver.dashboard');
    }

    /**
     * Display the driver dashboard.
     */
    public function dashboard(DashboardService $dashboardService)
    {
        $user = Auth::user();
        $driver = Driver::where('user_id', $user->id)->first();

        $bus = null;
        $route = null;
        if ($driver) {
            $bus = Bus::where('plate_number', $driver->assigned_bus)->first();
            $route = Route::find($driver->assigned_route);
        }

        $quickStats = $dashboardService->getDriverStats($driver);

        $supervisorContact = $this->getFleetSupervisorContact();
        $dispatcherName = $supervisorContact['name'];
        $dispatcherRole = $supervisorContact['role_label'];

        return view('driver.dashboard.index', compact('driver', 'bus', 'route', 'quickStats', 'supervisorContact', 'dispatcherName', 'dispatcherRole'));
    }

    /**
     * Display driver current trip tracking interface.
     */
    public function trip()
    {
        $user = Auth::user();
        $driver = Driver::where('user_id', $user->id)->first();

        $bus = null;
        $route = null;
        $gpsCoords = [];
        if ($driver) {
            $bus = Bus::where('plate_number', $driver->assigned_bus)->first();
            $route = Route::getAllCached()->firstWhere('id', $driver->assigned_route);
            if ($route) {
                $routeStops = Stop::getAllCached()->where('route_id', $route->id)->sortBy('sequence');
                $route->setRelation('stops', $routeStops);
            }
            if ($route && !empty($route->polyline_coordinates)) {
                foreach ($route->polyline_coordinates as $coord) {
                    $gpsCoords[] = ['lat' => (float)$coord[0], 'lng' => (float)$coord[1]];
                }
            }
        }

        if (empty($gpsCoords)) {
            // Load stops from assigned route dynamically
            if ($route && $route->stops->isNotEmpty()) {
                foreach ($route->stops as $stop) {
                    $gpsCoords[] = ['lat' => (float)$stop->lat, 'lng' => (float)$stop->lng];
                }
            }
        }

        if (empty($gpsCoords)) {
            // Fallback: Load stops from any first available route with stops
            $fallbackRoute = Route::getAllCached()->first(function($r) {
                return Stop::getAllCached()->where('route_id', $r->id)->isNotEmpty();
            });
            if ($fallbackRoute) {
                $routeStops = Stop::getAllCached()->where('route_id', $fallbackRoute->id)->sortBy('sequence');
                $fallbackRoute->setRelation('stops', $routeStops);
                foreach ($fallbackRoute->stops as $stop) {
                    $gpsCoords[] = ['lat' => (float)$stop->lat, 'lng' => (float)$stop->lng];
                }
            }
        }

        if (empty($gpsCoords)) {
            // Fallback: Load stops from all available stops in database
            $allStops = Stop::getAllCached()->sortBy('id');
            if ($allStops->isNotEmpty()) {
                foreach ($allStops as $stop) {
                    $gpsCoords[] = ['lat' => (float)$stop->lat, 'lng' => (float)$stop->lng];
                }
            }
        }

        if (empty($gpsCoords)) {
            // Final fallback: attempt to use default route settings or system settings
            $routeDefaults = \App\Models\DefaultRouteSetting::latest()->first();
            if ($routeDefaults && $routeDefaults->default_latitude && $routeDefaults->default_longitude) {
                $gpsCoords[] = ['lat' => (float)$routeDefaults->default_latitude, 'lng' => (float)$routeDefaults->default_longitude];
            } else {
                $nearLat = (float) \App\Models\SystemSetting::get('default_route_start_lat', 14.5593);
                $nearLng = (float) \App\Models\SystemSetting::get('default_route_start_lng', 121.0805);
                $gpsCoords[] = ['lat' => $nearLat, 'lng' => $nearLng];
            }
        }

        $activeTrip = null;
        $lastCompletedTrip = null;
        $tripProgress = null;
        $tripOrderedStops = collect();
        $activeVehiclePosition = null;

        if ($driver) {
            $activeTrip = Trip::query()
                ->where('driver_id', $driver->id)
                ->whereIn('status', ['dispatched', 'ongoing'])
                ->with(['routeVariant.stops', 'bus', 'route.stops'])
                ->orderByRaw("CASE WHEN status = 'ongoing' THEN 0 ELSE 1 END")
                ->latest('dispatched_at')
                ->latest('id')
                ->first();

            $lastCompletedTrip = Trip::query()
                ->where('driver_id', $driver->id)
                ->where('status', 'completed')
                ->with('routeVariant')
                ->latest('ended_at')
                ->latest('id')
                ->first();

            if ($activeTrip) {
                $tripProgress = \App\Models\TripProgress::where('trip_id', $activeTrip->id)
                    ->with(['currentStop', 'nextStop', 'currentRouteVariantStop', 'nextRouteVariantStop'])
                    ->first();

                if ($activeTrip->routeVariant && $activeTrip->routeVariant->stops->isNotEmpty()) {
                    $tripOrderedStops = $activeTrip->routeVariant->stops->sortBy('sequence');
                } elseif ($activeTrip->route && $activeTrip->route->stops->isNotEmpty()) {
                    $tripOrderedStops = $activeTrip->route->stops->sortBy('sequence');
                }
            }
        }

        $nextTripPreview = [
            'available' => false,
            'label' => 'No upcoming trips scheduled',
            'message' => 'No upcoming dispatches available.',
        ];

        $supervisorContact = $this->getFleetSupervisorContact();
        $dispatcherName = $supervisorContact['name'];
        $dispatcherRole = $supervisorContact['role_label'];

        return view('driver.trip.index', compact(
            'driver',
            'bus',
            'route',
            'activeTrip',
            'lastCompletedTrip',
            'nextTripPreview',
            'tripProgress',
            'tripOrderedStops',
            'activeVehiclePosition',
            'supervisorContact',
            'dispatcherName',
            'dispatcherRole',
            'gpsCoords'
        ));
    }

    /**
     * Display driver schedules.
     */
    public function schedule()
    {
        $user = Auth::user();
        $driver = Driver::where('user_id', $user->id)->first();

        $schedules = collect();
        if ($driver && $driver->assigned_route) {
            $schedules = Schedule::where('route_id', $driver->assigned_route)
                ->orderBy('departure_time')
                ->get();
        }

        $supervisorContact = $this->getFleetSupervisorContact();
        $dispatcherName = $supervisorContact['name'];
        $dispatcherRole = $supervisorContact['role_label'];

        return view('driver.schedule.index', compact('driver', 'schedules', 'supervisorContact', 'dispatcherName', 'dispatcherRole'));
    }

    /**
     * Display announcements and alerts for driver.
     */
    public function announcements()
    {
        $user = Auth::user();
        $driver = Driver::where('user_id', $user->id)->first();

        $alerts = ServiceAlert::activeAlerts()
            ->latest()
            ->get();

        $messages = collect();
        if ($driver) {
            $messages = \App\Models\DriverMessage::where('driver_id', $driver->id)
                ->with('sender')
                ->latest()
                ->get();
        }

        return view('driver.announcements.index', compact('driver', 'alerts', 'messages'));
    }

    /**
     * Start/Toggle a trip.
     */
    public function toggleTrip(Request $request, \App\Services\TripLifecycleService $tripLifecycleService)
    {
        $user = Auth::user();
        $driver = Driver::where('user_id', $user->id)->first();
        if ($driver && $driver->assigned_bus) {
            $bus = Bus::where('plate_number', $driver->assigned_bus)->first();
            if ($bus) {
                $status = $request->input('status'); // 'active', 'inactive', or 'breakdown'

                if ($status === 'active' && !$driver->assigned_route) {
                    return response()->json(['success' => false, 'message' => 'No route assigned. Contact your dispatcher.'], 422);
                }

                if ($status === 'active') {
                    if ($bus->status === 'maintenance') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Cannot start trip. Bus is currently locked in maintenance. Contact your dispatcher.'
                        ], 422);
                    }

                    if ($bus->status === \App\Models\Bus::STATUS_BREAKDOWN) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Cannot start trip. Bus has an unresolved breakdown. Contact your dispatcher.'
                        ], 422);
                    }

                    $trip = \App\Models\Trip::where('driver_id', $driver->id)
                        ->where('status', 'dispatched')
                        ->first();

                    if (!$trip) {
                        $routeId = (int) $driver->assigned_route;
                        $route = \App\Models\Route::findOrFail($routeId);
                        $trip = \App\Services\TripService::startTrip($bus, $driver, $route, $bus->passengers ?: 0);
                    }

                    try {
                        $tripLifecycleService->startTrip($trip);
                    } catch (\Exception $e) {
                        return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
                    }

                } elseif ($status === 'inactive' || $status === 'breakdown') {
                    $trip = \App\Models\Trip::where('driver_id', $driver->id)
                        ->where('status', 'ongoing')
                        ->first();

                    if ($trip) {
                        try {
                            if ($status === 'breakdown') {
                                $tripLifecycleService->cancelTrip($trip);
                            } else {
                                $tripLifecycleService->completeTrip($trip);
                            }
                        } catch (\Exception $e) {
                            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
                        }
                    }

                    if ($status === \App\Models\Bus::STATUS_INACTIVE) {
                        $today = now(config('app.timezone', 'Asia/Manila'))->toDateString();
                        Schedule::where('route_id', (int) $driver->assigned_route)
                            ->whereDate('service_date', $today)
                            ->where('bus_id', $bus->id)
                            ->update(['passengers' => $bus->passengers]);
                    }

                    // Populate actual departure on the driver's current schedule.
                    $schedule = \App\Models\Schedule::where('driver_id', $driver->id)
                        ->whereNull('actual_departure_time')
                        ->where('service_date', now('Asia/Manila')->toDateString())
                        ->orderBy('departure_time', 'asc')
                        ->first();

                    if ($schedule) {
                        $now = now('Asia/Manila');
                        $schedule->actual_departure_time = $now->format('H:i:s');

                        $scheduledDep = \Carbon\Carbon::parse($schedule->departure_time);
                        $variance = $scheduledDep->diffInMinutes($now, false);

                        if ($variance < 0) {
                            $schedule->status = \App\Models\Schedule::STATUS_EARLY;
                        } elseif ($variance > 5) {
                            $schedule->status = \App\Models\Schedule::STATUS_DELAYED;
                            $schedule->delay_minutes = $variance;
                        }
                        $schedule->save();
                    }
                }
                return response()->json(['success' => true, 'status' => $status, 'trips_today' => $driver->trips_today]);
            }
        }
        return response()->json(['success' => false, 'message' => 'No active bus assigned.'], 400);
    }

    /**
     * Log an incident/breakdown.
     */
    public function reportIncident(Request $request)
    {
        $user = Auth::user();
        $driver = Driver::where('user_id', $user->id)->first();
        if ($driver && $driver->assigned_bus) {
            $bus = Bus::where('plate_number', $driver->assigned_bus)->first();
            if ($bus) {
                $type = $request->input('type', 'General Issue');
                $description = $request->input('description', '');

                // Ensure ongoing trip exists to satisfy foreign key constraints
                $trip = DB::table('trips')
                    ->where('driver_id', $driver->id)
                    ->where('status', 'ongoing')
                    ->first();

                if (!$trip) {
                    return response()->json(['success' => false, 'message' => 'Cannot log incident: No active trip in progress.'], 422);
                }
                $tripId = $trip->id;

                // Insert into incidents table
                DB::table('incidents')->insert([
                    'trip_id' => $tripId,
                    'driver_id' => $driver->id,
                    'type' => $type,
                    'description' => $description,
                    'status' => 'reported',
                    'reported_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Update bus status if it's a breakdown or accident, cancel the ongoing trip, and deactivate the driver
                if (\App\Models\Incident::isBreakdown($type) || \App\Models\Incident::isAccident($type)) {
                    try {
                        \App\Services\BusStateService::transition($bus, \App\Models\Bus::STATUS_BREAKDOWN, 'Incident report: ' . strtolower($type));
                    } catch (\App\Exceptions\InvalidStatusTransitionException $e) {
                        \Illuminate\Support\Facades\Log::warning('Incident status transition failed', [
                            'bus_id' => $bus->id,
                            'error'  => $e->getMessage()
                        ]);
                    }
                } else {
                    // Non-breakdown informational audit log
                    \App\Models\BusStatusAuditLog::create([
                        'bus_id'     => $bus->id,
                        'old_status' => $bus->status,
                        'new_status' => $bus->status,
                        'reason'     => 'Incident Report: ' . $type,
                        'changed_by' => $user->id,
                    ]);
                }

                return response()->json(['success' => true, 'message' => 'Incident logged. Dispatch has been notified!']);
            }
        }
        return response()->json(['success' => false, 'message' => 'Unable to report incident: No assigned bus.'], 400);
    }

    /**
     * Increment/decrement passenger count.
     */
    public function updatePassengers(Request $request)
    {
        $user = Auth::user();
        $driver = Driver::where('user_id', $user->id)->first();
        if ($driver && $driver->assigned_bus) {
            $bus = Bus::where('plate_number', $driver->assigned_bus)->first();
            if ($bus) {
                $ongoingTrip = Trip::query()
                    ->where('driver_id', $driver->id)
                    ->where('bus_id', $bus->id)
                    ->where('status', 'ongoing')
                    ->where('gps_session', 'ACTIVE')
                    ->whereNotNull('started_at')
                    ->first();

                if (!$ongoingTrip || $bus->status !== 'operating') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Passenger management is unavailable because the assigned trip is not currently operating.',
                    ], 409);
                }

                $change = (int)$request->input('change', 0);
                $newPax = max(0, min($bus->capacity, $bus->passengers + $change));
                $bus->update(['passengers' => $newPax]);

                if ($change > 0) {
                    $driver->increment('pax_today', $change);
                }

                $currentPeak = (int) ($ongoingTrip->peak_passengers ?? 0);
                if ($newPax > $currentPeak) {
                    \App\Services\TripService::updatePeakPassengers($ongoingTrip, $newPax);
                }

                return response()->json(['success' => true, 'passengers' => $newPax, 'pax_today' => $driver->pax_today]);
            }
        }
        return response()->json(['success' => false, 'message' => 'No active bus assigned.'], 400);
    }

    /**
     * Update target stop and ETA.
     */
    public function updateStop(Request $request)
    {
        $user = Auth::user();
        $driver = Driver::where('user_id', $user->id)->first();
        if ($driver && $driver->assigned_bus) {
            $bus = Bus::where('plate_number', $driver->assigned_bus)->first();
            if ($bus) {
                $nextStop = $request->input('next_stop');
                $eta = (int)$request->input('eta', 5);
                $bus->update([
                    'next_stop' => $nextStop,
                    'eta' => $eta
                ]);
                return response()->json(['success' => true, 'next_stop' => $nextStop, 'eta' => $eta]);
            }
        }
        return response()->json(['success' => false, 'message' => 'No active bus assigned.'], 400);
    }    /**
     * Receive GPS telemetry coordinates, validate request, and queue processing.
     * [GPS_TRACE] TEMPORARY INSTRUMENTATION — REMOVE AFTER INVESTIGATION
     */
    public function updateGPS(Request $request, TelemetryProcessingService $telemetry)
    {
        // [GPS_TRACE] A — Controller entered
        Log::info('[GPS_TRACE] A - Controller entered', [
            'user_id'          => Auth::id(),
            'queue_connection' => config('queue.default'),
            'queue_driver'     => config('queue.connections.' . config('queue.default') . '.driver'),
            'ip'               => $request->ip(),
            'payload'          => $request->only(['lat', 'lng', 'speed', 'heading', 'accuracy', 'is_simulated', 'gps_fix_timestamp', 'gps_fix_age_ms', 'is_cached_fix', 'speed_source']),
        ]);

        $request->validate([
            'lat'          => 'required|numeric|between:-90,90',
            'lng'          => 'required|numeric|between:-180,180',
            'speed'        => 'required|numeric|min:0',
            'heading'      => 'nullable|numeric|between:0,360',
            'accuracy'     => 'nullable|numeric|min:0',
            'is_simulated'      => 'nullable|boolean',
            'gps_fix_timestamp' => 'nullable|date',
            'gps_fix_age_ms'    => 'nullable|integer|min:0',
            'is_cached_fix'     => 'nullable|boolean',
            'speed_source'      => 'nullable|string|in:native,calculated,cached',
        ]);

        if ($request->boolean('is_simulated')) {
            return response()->json([
                'success' => false,
                'message' => 'Simulated speed is not accepted for live GPS telemetry.',
            ], 422);
        }

        // [GPS_TRACE] B — Validation passed
        Log::info('[GPS_TRACE] B - Validation passed');
        Log::info('[GPS_ACCURACY_TRACE] request_accuracy', [
            'request_accuracy' => $request->input('accuracy'),
            'request_has_accuracy' => $request->has('accuracy'),
        ]);

        $user   = Auth::user();
        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver || !$driver->assigned_bus) {
            Log::warning('[GPS_TRACE] EARLY EXIT - No driver or assigned_bus', [
                'user_id'      => $user->id,
                'driver_found' => (bool) $driver,
                'assigned_bus' => $driver->assigned_bus ?? null,
            ]);
            return response()->json(['success' => false, 'message' => 'No assigned bus.'], 400);
        }

        $bus = Bus::where('plate_number', $driver->assigned_bus)->first();

        if (!$bus) {
            Log::warning('[GPS_TRACE] EARLY EXIT - Bus record not found', [
                'assigned_bus' => $driver->assigned_bus,
            ]);
            return response()->json(['success' => false, 'message' => 'No assigned bus.'], 400);
        }

        $ongoingTrip = Trip::where('driver_id', $driver->id)
            ->where('status', 'ongoing')
            ->where('gps_session', 'ACTIVE')
            ->first();

        if (!$ongoingTrip) {
            Log::warning('[GPS_TRACE] EARLY EXIT - No ongoing trip with ACTIVE gps_session', [
                'driver_id'   => $driver->id,
                'trips_found' => Trip::where('driver_id', $driver->id)->get(['id', 'status', 'gps_session'])->toArray(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Live trip session has not started or is closed.',
            ], 409);
        }

        Log::info('[GPS_TRACE] B2 - Trip guard passed', [
            'trip_id'     => $ongoingTrip->id,
            'bus_id'      => $bus->id,
            'driver_id'   => $driver->id,
            'gps_session' => $ongoingTrip->gps_session,
            'trip_status' => $ongoingTrip->status,
        ]);

        // [GPS_TRACE] C — GPSLog::create
        Log::info('[GPS_TRACE] C - Before GPSLog::create', [
            'trip_id'     => $ongoingTrip->id,
            'lat'         => $request->input('lat'),
            'lng'         => $request->input('lng'),
            'received_at' => \Carbon\CarbonImmutable::now('UTC')->toIso8601String(),
        ]);

        Log::info('[GPS_ACCURACY_TRACE] accuracy_before_gpslog_create', [
            'accuracy_value' => $request->has('accuracy') ? (float) $request->input('accuracy') : null,
        ]);

        $log = GPSLog::create([
            'trip_id'           => $ongoingTrip->id,
            'lat'               => (float) $request->input('lat'),
            'lng'               => (float) $request->input('lng'),
            'speed'             => (float) $request->input('speed'),
            'heading'           => $request->input('heading') !== null ? (float) $request->input('heading') : null,
            'accuracy'          => $request->has('accuracy') ? (float) $request->input('accuracy') : null,
            'timestamp'         => now(),
            'received_at'       => now(),
            'gps_fix_timestamp' => $request->filled('gps_fix_timestamp') ? \Carbon\Carbon::parse($request->input('gps_fix_timestamp')) : null,
            'gps_fix_age_ms'    => $request->has('gps_fix_age_ms') ? (int) $request->input('gps_fix_age_ms') : null,
            'is_cached_fix'     => $request->boolean('is_cached_fix'),
            'speed_source'      => $request->input('speed_source'),
            'processing_status' => 'pending',
        ]);

        Log::info('[GPS_TRACE] C2 - GPSLog created', [
            'log_id'      => $log->id,
            'received_at' => $log->received_at,
            'trip_id'     => $log->trip_id,
        ]);

        Log::info('[GPS_ACCURACY_TRACE] persisted_accuracy', [
            'gps_log_id' => $log->id,
            'persisted_accuracy' => $log->fresh()->accuracy,
        ]);

        Log::info('[GPS_TRACE] D - Before synchronous TelemetryProcessingService::processGpsLog', [
            'log_id' => $log->id,
        ]);

        $result = $telemetry->processGpsLog($log->id);
        $processedLog = $result['log'] ?? $log->fresh();
        $position = $result['position'] ?? null;

        if (($result['status'] ?? null) !== 'processed') {
            return response()->json([
                'success'       => false,
                'message'       => 'GPS telemetry was received but failed live processing.',
                'status'        => $result['status'] ?? 'unknown',
                'error'         => $result['error'] ?? null,
                'log_id'        => $log->id,
                'trip_id'       => $ongoingTrip->id,
                'bus_id'        => $bus->id,
                'processing_ms' => $result['processing_ms'] ?? null,
            ], 422);
        }

        $bus->refresh();

        Log::info('[GPS_TRACE] M - Returning synchronous telemetry response', [
            'log_id'        => $log->id,
            'trip_id'       => $ongoingTrip->id,
            'bus_id'        => $bus->id,
            'processing_ms' => $result['processing_ms'] ?? null,
        ]);

        return response()->json([
            'success'       => true,
            'message'       => 'GPS telemetry processed.',
            'log_id'        => $log->id,
            'trip_id'       => $ongoingTrip->id,
            'bus_id'        => $bus->id,
            'lat'           => $position ? $position->lat : $bus->lat,
            'lng'           => $position ? $position->lng : $bus->lng,
            'filtered_lat'  => $processedLog?->filtered_lat,
            'filtered_lng'  => $processedLog?->filtered_lng,
            'speed'         => $bus->speed,
            'speed_mps'     => $bus->speed !== null ? (float) $bus->speed : null,
            'speed_kmh'     => $bus->speed !== null ? round((float) $bus->speed * 3.6, 1) : null,
            'speed_unit'    => 'm/s',
            'heading'       => $position ? $position->heading : null,
            'next_stop'     => $bus->next_stop,
            'eta'           => $bus->eta,
            'processing_ms' => $result['processing_ms'] ?? null,
        ]);
    }
}
