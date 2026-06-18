<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Driver;
use App\Models\Bus;
use App\Models\Route;
use App\Models\User;
use App\Models\Schedule;
use App\Models\ServiceAlert;
use App\Models\Stop;
use App\Services\GPSKalmanFilter;
use App\Services\DashboardService;
use App\Models\SystemSetting;

class DriverController extends Controller
{
    /**
     * Get dynamic dispatcher first name from database.
     */
    private function getDispatcherName()
    {
        $dispatcher = User::where('role', 'dispatcher')->first() 
            ?? User::where('role', 'admin')->first() 
            ?? User::first();
        return $dispatcher ? explode(' ', $dispatcher->name)[0] : 'Dispatcher';
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

        $dispatcherName = $this->getDispatcherName();

        return view('driver.dashboard.index', compact('driver', 'bus', 'route', 'quickStats', 'dispatcherName'));
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

        $dispatcherName = $this->getDispatcherName();

        return view('driver.trip.index', compact('driver', 'bus', 'route', 'dispatcherName', 'gpsCoords'));
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

        $dispatcherName = $this->getDispatcherName();

        return view('driver.schedule.index', compact('driver', 'schedules', 'dispatcherName'));
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

        return view('driver.announcements.index', compact('driver', 'alerts'));
    }

    /**
     * Start/Toggle a trip.
     */
    public function toggleTrip(Request $request)
    {
        $user = Auth::user();
        $driver = Driver::where('user_id', $user->id)->first();
        if ($driver && $driver->assigned_bus) {
            $bus = Bus::where('plate_number', $driver->assigned_bus)->first();
            if ($bus) {
                $status = $request->input('status'); // 'active' or 'inactive'
                
                if ($status === 'active' && !$driver->assigned_route) {
                    return response()->json(['success' => false, 'message' => 'No route assigned. Contact your dispatcher.'], 422);
                }

                $bus->update(['status' => $status]);
                
                if ($status === 'active') {
                    $driver->increment('trips_today');
                    
                    // Create an ongoing trip record
                    $existingTrip = DB::table('trips')
                        ->where('driver_id', $driver->id)
                        ->where('status', 'ongoing')
                        ->first();
                    
                    if (!$existingTrip) {
                        DB::table('trips')->insert([
                            'bus_id' => $bus->id,
                            'driver_id' => $driver->id,
                            'route_id' => (int) $driver->assigned_route,
                            'status' => 'ongoing',
                            'peak_passengers' => $bus->passengers ?: 0,
                            'started_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($status === 'inactive' || $status === 'breakdown') {
                    DB::table('trips')
                        ->where('driver_id', $driver->id)
                        ->where('status', 'ongoing')
                        ->update([
                            'status' => $status === 'breakdown' ? 'cancelled' : 'completed',
                            'ended_at' => now(),
                            'updated_at' => now(),
                        ]);
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
                    $defaultRoute = Route::first();
                    $routeId = $driver->assigned_route ?? ($defaultRoute ? $defaultRoute->id : 1);

                    $tripId = DB::table('trips')->insertGetId([
                        'bus_id' => $bus->id,
                        'driver_id' => $driver->id,
                        'route_id' => $routeId,
                        'status' => 'ongoing',
                        'started_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $tripId = $trip->id;
                }

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

                // Update bus status if it's a breakdown
                $breakdownType = SystemSetting::get('incident_breakdown_type', 'Breakdown');
                if ($type === $breakdownType) {
                    $bus->update(['status' => 'breakdown']);
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
                $change = (int)$request->input('change', 0);
                $newPax = max(0, min($bus->capacity, $bus->passengers + $change));
                $bus->update(['passengers' => $newPax]);
                
                if ($change > 0) {
                    $driver->increment('pax_today', $change);
                }
                
                // Track peak passengers in the active ongoing trip
                $ongoingTrip = DB::table('trips')
                    ->where('driver_id', $driver->id)
                    ->where('status', 'ongoing')
                    ->first();
                
                if (!$ongoingTrip) {
                    if (!$driver->assigned_route) {
                        return response()->json(['success' => false, 'message' => 'No route assigned. Contact your dispatcher.'], 422);
                    }
                    $routeId = (int) $driver->assigned_route;
                    DB::table('trips')->insert([
                        'bus_id' => $bus->id,
                        'driver_id' => $driver->id,
                        'route_id' => $routeId,
                        'status' => 'ongoing',
                        'peak_passengers' => $newPax,
                        'started_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $currentPeak = (int) ($ongoingTrip->peak_passengers ?? 0);
                    if ($newPax > $currentPeak) {
                        DB::table('trips')
                            ->where('id', $ongoingTrip->id)
                            ->update([
                                'peak_passengers' => $newPax,
                                'updated_at' => now(),
                            ]);
                    }
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
     * Receive GPS telemetry coordinates, apply Kalman filtering, advance sequence if within threshold, compute ETA.
     */
    public function updateGPS(Request $request)
    {
        $user = Auth::user();
        $driver = Driver::where('user_id', $user->id)->first();
        if ($driver && $driver->assigned_bus) {
            $bus = Bus::where('plate_number', $driver->assigned_bus)->first();
            if ($bus) {
                $lat = $request->input('lat');
                $lng = $request->input('lng');
                $speed = (int)$request->input('speed', 0);
                $isSimulated = (bool) $request->input('is_simulated', false);

                // 1. Apply Kalman Filter to smooth raw GPS inputs
                $filtered = GPSKalmanFilter::smooth($bus->id, $lat, $lng);
                $smoothedLat = $filtered['lat'];
                $smoothedLng = $filtered['lng'];

                // 2. Dynamic Next Stop Progression and ETA calculation via Haversine
                $nextStop = $bus->next_stop;
                $eta = $bus->eta ?: 5;

                $route = $bus->route()->with('stops')->first();
                if ($route && $route->stops->isNotEmpty()) {
                    $stops = $route->stops; // Ordered by sequence

                    // Find current target stop or default to first
                    $currentStop = $stops->first(function ($s) use ($nextStop) {
                        return stripos($s->name, (string)$nextStop) !== false || stripos((string)$nextStop, $s->name) !== false;
                    });

                    if (!$currentStop) {
                        $currentStop = $stops->first();
                    }

                    // Calculate distance in meters using Haversine formula
                    $distanceToStop = GPSKalmanFilter::calculateDistance(
                        $smoothedLat,
                        $smoothedLng,
                        $currentStop->lat,
                        $currentStop->lng
                    );

                    // If bus is within configurable threshold, automatically advance to next stop in sequence
                    $autoAdvanceThreshold = (float) \App\Models\SystemSetting::get('stop_auto_advance_distance', 100);
                    if ($distanceToStop <= $autoAdvanceThreshold) {
                        $currentIndex = $stops->search(fn ($stop) => $stop->is($currentStop));
                        if ($currentIndex === false) {
                            $currentIndex = 0;
                        }
                        $nextIndex = ($currentIndex + 1) % $stops->count();
                        $currentStop = $stops->get($nextIndex);

                        // Recalculate distance to new next stop
                        $distanceToStop = GPSKalmanFilter::calculateDistance(
                            $smoothedLat,
                            $smoothedLng,
                            $currentStop->lat,
                            $currentStop->lng
                        );
                    }

                    $nextStop = $currentStop->name;

                    // Compute ETA: ETA = Distance (km) / Speed (km/h) * 60 min
                    $distanceKm = $distanceToStop / 1000;
                    if ($speed > 5) {
                        $eta = (int) round(($distanceKm / $speed) * 60);
                    } else {
                        // Traffic or stoplights: compute dynamic average speed from active fleet or historical GPS logs
                        $averageFleetSpeed = null;
                        if ($route) {
                            $averageFleetSpeed = Bus::where('route_id', $route->id)
                                ->where('status', 'active')
                                ->where('speed', '>', 5)
                                ->avg('speed');
                        }
                        if (!$averageFleetSpeed) {
                            $averageFleetSpeed = Bus::where('status', 'active')
                                ->where('speed', '>', 5)
                                ->avg('speed');
                        }
                        if (!$averageFleetSpeed) {
                            // Limit to the last hour to avoid a full-table scan on the
                            // ever-growing gps_logs table (receives entries every ~6 seconds).
                            $averageFleetSpeed = DB::table('gps_logs')
                                ->where('speed', '>', 5)
                                ->where('created_at', '>=', now()->subHour())
                                ->avg('speed');
                        }
                        $fallbackSpeed = $averageFleetSpeed ? (int) round($averageFleetSpeed) : 15;
                        $fallbackSpeed = max(5, $fallbackSpeed); // Ensure speed is at least 5 km/h to avoid division by zero
                        
                        $eta = (int) round(($distanceKm / $fallbackSpeed) * 60);
                    }
                    $eta = max(1, $eta); // Guarantee at least 1 minute
                }

                $bus->update([
                    'lat' => $smoothedLat,
                    'lng' => $smoothedLng,
                    'speed' => $speed,
                    'next_stop' => $nextStop,
                    'eta' => $eta,
                    'is_simulated' => $isSimulated
                ]);

                // Record to gps_logs table if an ongoing trip exists for this driver
                $ongoingTrip = DB::table('trips')
                    ->where('driver_id', $driver->id)
                    ->where('status', 'ongoing')
                    ->first();
                if ($ongoingTrip) {
                    DB::table('gps_logs')->insert([
                        'trip_id' => $ongoingTrip->id,
                        'lat' => $smoothedLat,
                        'lng' => $smoothedLng,
                        'speed' => $speed,
                        'timestamp' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return response()->json([
                    'success' => true, 
                    'message' => 'GPS Telemetry updated (Kalman filtered, Haversine ETA).', 
                    'lat' => $smoothedLat, 
                    'lng' => $smoothedLng, 
                    'speed' => $speed,
                    'next_stop' => $nextStop,
                    'eta' => $eta
                ]);
            }
        }
        return response()->json(['success' => false, 'message' => 'No assigned bus.'], 400);
    }
}
