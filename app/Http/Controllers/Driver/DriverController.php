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
            $route = Route::with('stops')->find($driver->assigned_route);
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
            $fallbackRoute = Route::with('stops')->has('stops')->first();
            if ($fallbackRoute) {
                foreach ($fallbackRoute->stops as $stop) {
                    $gpsCoords[] = ['lat' => (float)$stop->lat, 'lng' => (float)$stop->lng];
                }
            }
        }

        if (empty($gpsCoords)) {
            // Fallback: Load stops from all available stops in database
            $allStops = Stop::orderBy('id')->get();
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
                $nearLat = (float) \App\Models\SystemSetting::get('sim_near_lat', 14.5768);
                $nearLng = (float) \App\Models\SystemSetting::get('sim_near_lng', 121.0858);
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
        
        $alerts = ServiceAlert::where('status', 'active')
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
                $bus->update(['status' => $status]);
                
                if ($status === 'active') {
                    $driver->increment('trips_today');
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
                if ($type === 'Breakdown') {
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

                    // If bus is within 100 meters, automatically advance to next stop in sequence
                    if ($distanceToStop <= 100) {
                        $currentIndex = $stops->indexOf($currentStop);
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
                            $averageFleetSpeed = DB::table('gps_logs')
                                ->where('speed', '>', 5)
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
                    'eta' => $eta
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
