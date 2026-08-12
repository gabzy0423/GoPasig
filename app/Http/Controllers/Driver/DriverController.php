<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Driver;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Trip;
use App\Models\TripPassengerEvent;
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

        $nextTripPreview = $this->buildNextTripPreview(
            $driver,
            $bus,
            $route,
            $activeTrip,
            $lastCompletedTrip
        );

        $developerGpsPresets = [];
        if (config('app.env') === 'local' && $activeTrip?->status === 'ongoing') {
            $coordinateStops = $tripOrderedStops
                ->filter(fn ($stop) => $stop->lat !== null && $stop->lng !== null)
                ->values();
            $stopCount = $coordinateStops->count();
            $developerGpsPresets = $coordinateStops
                ->map(function ($stop, $index) use ($stopCount) {
                    $role = $index === 0
                        ? 'Origin'
                        : ($index === $stopCount - 1 ? 'Destination' : 'Stop ' . $stop->sequence);

                    return [
                        'label' => $role . ' - ' . $stop->name,
                        'name' => $stop->name,
                        'lat' => (float) $stop->lat,
                        'lng' => (float) $stop->lng,
                    ];
                })
                ->all();
        }

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
            'gpsCoords',
            'developerGpsPresets'
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
                        return response()->json([
                            'success' => false,
                            'message' => 'No dispatched official trip is assigned. Contact your dispatcher.',
                        ], 422);
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
     * Start the next point-to-point leg on the retained bus/driver assignment.
     *
     * A completed leg is the hand-off boundary: the previous Trip remains
     * immutable, while this action creates one opposite-direction Trip and
     * starts it through the normal lifecycle service.
     */
    public function startNextTrip(Request $request, \App\Services\TripLifecycleService $tripLifecycleService)
    {
        $driver = Driver::where('user_id', Auth::id())->first();

        if (! $driver || ! $driver->assigned_bus || ! $driver->assigned_route) {
            return response()->json([
                'success' => false,
                'message' => 'No retained bus and route assignment is available for the next trip.',
            ], 400);
        }

        try {
            $result = DB::transaction(function () use ($driver, $tripLifecycleService) {
                $lockedDriver = Driver::whereKey($driver->id)->lockForUpdate()->firstOrFail();
                $bus = Bus::where('plate_number', $lockedDriver->assigned_bus)
                    ->lockForUpdate()
                    ->first();
                $route = Route::find($lockedDriver->assigned_route);

                if (! $bus || ! $route) {
                    throw new \RuntimeException('The retained bus or route assignment could not be found.');
                }

                $context = $this->resolveNextTripContext($lockedDriver, $bus, $route);
                $trip = \App\Services\TripService::startTrip(
                    $bus,
                    $lockedDriver,
                    $route,
                    (int) ($bus->passengers ?: 0),
                    $context['variant'],
                    $context['schedule']
                );

                $tripLifecycleService->startTrip($trip->fresh(['bus', 'driver']));
                $trip->refresh()->load(['routeVariant', 'bus', 'driver']);

                return [
                    'trip_id' => $trip->id,
                    'route_id' => $trip->route_id,
                    'route_variant_id' => $trip->route_variant_id,
                    'direction' => $trip->routeVariant?->direction,
                    'status' => $trip->status,
                    'schedule_id' => $trip->schedule_id,
                    'bus_id' => $trip->bus_id,
                    'driver_id' => $trip->driver_id,
                ];
            });

            return response()->json(['success' => true, ...$result]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?: $exception->getMessage();

            return response()->json([
                'success' => false,
                'message' => 'Cannot start next trip: ' . $message,
            ], 422);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot start next trip: ' . $exception->getMessage(),
            ], 422);
        }
    }

    private function buildNextTripPreview(
        ?Driver $driver,
        ?Bus $bus,
        ?Route $route,
        ?Trip $activeTrip,
        ?Trip $lastCompletedTrip
    ): array {
        $preview = [
            'available' => false,
            'label' => 'No upcoming trips scheduled',
            'message' => 'No upcoming dispatches available.',
        ];

        if (! $driver || ! $bus || ! $route || $activeTrip || ! $lastCompletedTrip) {
            return $preview;
        }

        try {
            $context = $this->resolveNextTripContext($driver, $bus, $route);
            $variant = $context['variant'];
            $label = app(\App\Services\RouteVariantSelectionService::class)->label($variant);

            if ($context['schedule']) {
                $label .= ' · ' . substr((string) $context['schedule']->departure_time, 0, 5);
            }

            return [
                'available' => true,
                'label' => $label,
                'message' => 'Trip completed — ready for next trip.',
            ];
        } catch (\Throwable $exception) {
            return [
                'available' => false,
                'label' => 'No upcoming trips scheduled',
                'message' => 'Next trip unavailable: ' . $exception->getMessage(),
            ];
        }
    }

    private function resolveNextTripContext(Driver $driver, Bus $bus, Route $route): array
    {
        if ($driver->status !== 'active') {
            throw new \RuntimeException('Driver is not active.');
        }

        if ($driver->assigned_bus !== $bus->plate_number || (int) $driver->assigned_route !== (int) $route->id) {
            throw new \RuntimeException('Driver, bus, and route assignments are inconsistent.');
        }

        if ($bus->status !== 'ready') {
            throw new \RuntimeException('Bus is not ready for the next leg.');
        }

        if ($driver->operational_status !== 'assigned') {
            throw new \RuntimeException('Driver is not ready for the next leg.');
        }

        $routeEligibility = \App\Services\CentralDispatchEligibilityService::route($route);
        if (! $routeEligibility['eligible']) {
            throw new \RuntimeException($routeEligibility['reason']);
        }

        $activeTripExists = Trip::query()
            ->where(function ($query) use ($driver, $bus) {
                $query->where('driver_id', $driver->id)
                    ->orWhere('bus_id', $bus->id);
            })
            ->whereIn('status', ['dispatched', 'ongoing'])
            ->exists();

        if ($activeTripExists) {
            throw new \RuntimeException('A dispatched or ongoing trip already exists for this driver or bus.');
        }

        $previousTrip = Trip::query()
            ->where('driver_id', $driver->id)
            ->where('bus_id', $bus->id)
            ->where('route_id', $route->id)
            ->where('status', 'completed')
            ->with('routeVariant')
            ->latest('ended_at')
            ->latest('id')
            ->first();

        if (! $previousTrip) {
            throw new \RuntimeException('No completed leg is available to resolve the next direction.');
        }

        if (! $previousTrip->routeVariant) {
            throw new \RuntimeException('The completed leg has no stored route direction.');
        }

        $hasMajorIncident = \App\Models\Incident::query()
            ->where('trip_id', $previousTrip->id)
            ->whereIn('status', ['reported', 'under_review'])
            ->get()
            ->contains(fn ($incident) =>
                \App\Models\Incident::isBreakdown($incident->type)
                || \App\Models\Incident::isAccident($incident->type)
            );

        if ($hasMajorIncident) {
            throw new \RuntimeException('An unresolved breakdown or accident blocks the next leg.');
        }

        $variant = app(\App\Services\RouteVariantSelectionService::class)
            ->resolveOppositeForNextTrip($route, $previousTrip->routeVariant);

        if (! $variant) {
            throw new \RuntimeException('No usable opposite direction is available.');
        }

        $matchingSchedules = Schedule::query()
            ->where('route_id', $route->id)
            ->where('route_variant_id', $variant->id)
            ->where('bus_id', $bus->id)
            ->where('driver_id', $driver->id)
            ->whereDate('service_date', now('Asia/Manila')->toDateString())
            ->where('status', '!=', Schedule::STATUS_CANCELLED)
            ->whereDoesntHave('trip')
            ->orderBy('departure_time')
            ->get();

        if ($matchingSchedules->count() > 1) {
            throw new \RuntimeException('multiple matching return Schedules exist. Dispatcher review is required.');
        }

        return [
            'variant' => $variant,
            'schedule' => $matchingSchedules->first(),
        ];
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
                $currentPax = (int) $bus->passengers;
                $newPax = max(0, min($bus->capacity, $currentPax + $change));
                $acceptedDelta = abs($newPax - $currentPax);
                $bus->update(['passengers' => $newPax]);

                if ($acceptedDelta > 0 && $change > 0) {
                    $driver->increment('pax_today', $acceptedDelta);
                }

                $currentPeak = (int) ($ongoingTrip->peak_passengers ?? 0);
                if ($newPax > $currentPeak) {
                    \App\Services\TripService::updatePeakPassengers($ongoingTrip, $newPax);
                }

                if ($acceptedDelta > 0) {
                    TripPassengerEvent::create([
                        'trip_id' => $ongoingTrip->id,
                        'driver_id' => $driver->id,
                        'bus_id' => $bus->id,
                        'route_id' => $ongoingTrip->route_id,
                        'event_type' => $change > 0
                            ? TripPassengerEvent::TYPE_BOARDED
                            : TripPassengerEvent::TYPE_ALIGHTED,
                        'passenger_delta' => $acceptedDelta,
                        'onboard_after' => $newPax,
                        'recorded_at' => now(),
                    ]);
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
    }

    /**
     * Apply one local-only developer coordinate to the driver's operating bus.
     * This intentionally bypasses GPSLog/telemetry history so UAT movement does
     * not become production-looking GPS analytics data.
     */
    public function updateDeveloperGPS(Request $request)
    {
        if (config('app.env') !== 'local') {
            return response()->json(['success' => false, 'message' => 'Developer GPS is available only locally.'], 404);
        }

        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'speed' => 'nullable|numeric|min:0|max:60',
            'heading' => 'nullable|numeric|between:0,360',
            'accuracy' => 'nullable|numeric|min:0|max:50',
            'next_stop' => 'nullable|string|max:255',
            'eta' => 'nullable|integer|min:0|max:1440',
        ]);

        $driver = Driver::where('user_id', Auth::id())->first();
        $bus = $driver?->assigned_bus
            ? Bus::where('plate_number', $driver->assigned_bus)->first()
            : null;

        if (! $driver || ! $bus) {
            return response()->json(['success' => false, 'message' => 'No assigned bus.'], 400);
        }

        $trip = Trip::query()
            ->where('driver_id', $driver->id)
            ->where('bus_id', $bus->id)
            ->where('status', 'ongoing')
            ->where('gps_session', 'ACTIVE')
            ->first();

        if (! $trip) {
            return response()->json([
                'success' => false,
                'message' => 'Start a real operating trip before using Developer GPS.',
            ], 409);
        }

        if (! Route::publicCommuterActiveService()->whereKey($trip->route_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Developer GPS is limited to official active commuter routes.',
            ], 422);
        }

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];
        $speed = (float) ($validated['speed'] ?? 0);
        $nextStop = $validated['next_stop'] ?? null;
        $eta = array_key_exists('eta', $validated) ? (int) $validated['eta'] : null;

        DB::transaction(function () use ($bus, $trip, $lat, $lng, $speed, $validated, $nextStop, $eta) {
            $bus->update([
                'lat' => $lat,
                'lng' => $lng,
                'speed' => $speed,
                'next_stop' => $nextStop ?: $bus->next_stop,
                'eta' => $eta ?? $bus->eta,
            ]);

            \App\Models\VehiclePosition::updateOrCreate(
                ['bus_id' => $bus->id],
                [
                    'trip_id' => $trip->id,
                    'lat' => $lat,
                    'lng' => $lng,
                    'heading' => $validated['heading'] ?? null,
                    'speed' => $speed,
                    'last_updated_at' => now(),
                    'gps_quality_state' => 'DEVELOPER',
                    'gps_quality_reason' => 'Local developer GPS UAT',
                    'gps_quality_updated_at' => now(),
                    'gps_fix_age_seconds' => 0,
                    'last_gps_fix_at' => now(),
                ]
            );
        });

        Cache::forget('commuter_active_buses_list');
        Cache::forget('commuter_dashboard_aggregate');
        Cache::forget('commuter_route_stops_aggregate');

        return response()->json([
            'success' => true,
            'source' => 'developer',
            'message' => 'Developer GPS location applied locally.',
            'trip_id' => $trip->id,
            'bus_id' => $bus->id,
            'lat' => $lat,
            'lng' => $lng,
            'next_stop' => $nextStop ?: $bus->next_stop,
            'eta' => $eta ?? $bus->eta,
        ]);
    }

    /**
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
