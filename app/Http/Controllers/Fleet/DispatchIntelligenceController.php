<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Route;
use App\Models\Stop;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\CommuterTrip;
use App\Models\DemandThreshold;
use App\Models\DemandHistory;
use App\Models\DispatchLog;
use App\Models\DispatchSimulatorCount;
use App\Models\TimeSlotConfiguration;
use App\Models\SystemSetting;
use App\Models\DispatchAlertSetting;
use App\Models\DispatchSimulationDefault;
use App\Models\Terminal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class DispatchIntelligenceController extends Controller
{
    /**
     * Display the Dispatch Intelligence index page.
     */
    public function index(Request $request)
    {
        $defaultPhase = $this->getSimulationDefault('phase_default', 1);
        $defaultDay = $this->getSimulationDefault('default_simulated_day', Carbon::now()->englishDayOfWeek);
        $defaultRouteId = $this->getSimulationDefault('route_default', Route::first()?->id);
        $defaultThreshold = $this->getSimulationDefault('default_demand_threshold', 20);

        $selectedPhase = $request->input('phase', $defaultPhase);
        $simulatedDay = $request->input('day', $defaultDay);
        $simulatedTimeSlot = $request->input('time_slot', $this->getDefaultTimeSlot());
        $selectedRouteId = $request->input('route_id', $defaultRouteId);

        // Load active routes data using helper
        $routesData = $this->fetchRoutesData($simulatedDay, $simulatedTimeSlot, $selectedPhase);

        // Threshold override limit for the current form inputs
        $threshold = DemandThreshold::where('route_id', $selectedRouteId)
            ->where('day_of_week', $simulatedDay)
            ->where('time_slot', $simulatedTimeSlot)
            ->first();
        $customThreshold = $threshold ? $threshold->threshold_count : $defaultThreshold;

        // Load historical patterns
        $historicalPatterns = DemandHistory::with('route')
            ->orderBy('total_commuters', 'desc')
            ->take(8)
            ->get();

        // Load recent dispatches
        $recentDispatches = DispatchLog::with(['trip.route', 'trip.bus', 'trip.driver', 'dispatcher'])
            ->latest()
            ->take(6)
            ->get();

        return view('fleet.dispatch-intelligence.index', [
            'selectedPhase' => $selectedPhase,
            'simulatedDay' => $simulatedDay,
            'simulatedTimeSlot' => $simulatedTimeSlot,
            'selectedRouteId' => $selectedRouteId,
            'customThreshold' => $customThreshold,
            'routesData' => $routesData,
            'historicalPatterns' => $historicalPatterns,
            'recentDispatches' => $recentDispatches,
        ]);
    }

    /**
     * Get JSON data payload for dynamic updates.
     */
    public function getDispatchData(Request $request)
    {
        $defaultPhase = $this->getSimulationDefault('phase_default', 1);
        $defaultDay = $this->getSimulationDefault('default_simulated_day', Carbon::now()->englishDayOfWeek);
        $defaultRouteId = $this->getSimulationDefault('route_default', Route::first()?->id);
        $defaultThreshold = $this->getSimulationDefault('default_demand_threshold', 20);

        $selectedPhase = $request->input('phase', $defaultPhase);
        $simulatedDay = $request->input('day', $defaultDay);
        $simulatedTimeSlot = $request->input('time_slot', $this->getDefaultTimeSlot());
        $selectedRouteId = $request->input('route_id', $defaultRouteId);

        $routesData = $this->fetchRoutesData($simulatedDay, $simulatedTimeSlot, $selectedPhase);
        $activeAlerts = $this->computeAlerts($simulatedDay, $simulatedTimeSlot, $selectedPhase, $routesData);

        // Fetch custom threshold for current route settings
        $threshold = DemandThreshold::where('route_id', $selectedRouteId)
            ->where('day_of_week', $simulatedDay)
            ->where('time_slot', $simulatedTimeSlot)
            ->first();
        $customThreshold = $threshold ? $threshold->threshold_count : $defaultThreshold;

        // Fetch recent dispatches
        $recentDispatches = DispatchLog::with(['trip.route', 'trip.bus', 'trip.driver', 'dispatcher'])
            ->latest()
            ->take(6)
            ->get()->map(function ($log) {
                return [
                    'id' => $log->id,
                    'route_id' => $log->trip ? $log->trip->route_id : null,
                    'route_name' => ($log->trip && $log->trip->route) ? $log->trip->route->name : 'Route',
                    'bus_plate' => ($log->trip && $log->trip->bus) ? $log->trip->bus->plate_number : '—',
                    'driver_name' => ($log->trip && $log->trip->driver) ? "{$log->trip->driver->first_name} {$log->trip->driver->last_name}" : '—',
                    'notes' => $log->notes,
                    'time_diff' => Carbon::parse($log->dispatched_at)->diffForHumans()
                ];
            });

        // Historical patterns
        $historicalPatterns = DemandHistory::with('route')
            ->orderBy('total_commuters', 'desc')
            ->take(8)
            ->get()->map(function ($p) {
                return [
                    'id' => $p->id,
                    'route_id' => $p->route_id,
                    'day_of_week' => $p->day_of_week,
                    'total_commuters' => $p->total_commuters,
                    'time_slot' => $p->time_slot
                ];
            });

        return response()->json([
            'routesData' => $routesData,
            'activeAlerts' => $activeAlerts,
            'customThreshold' => $customThreshold,
            'recentDispatches' => $recentDispatches,
            'historicalPatterns' => $historicalPatterns
        ]);
    }

    /**
     * Save/Override Threshold count.
     */
    public function saveThreshold(Request $request)
    {
        $min = (int) SystemSetting::get('threshold_min_value', 5);
        $max = (int) SystemSetting::get('threshold_max_value', 100);

        $validated = $request->validate([
            'route_id' => 'required|exists:routes,id',
            'day' => 'required|string',
            'time_slot' => 'required|string',
            'threshold' => "required|integer|min:{$min}|max:{$max}",
        ]);

        DemandThreshold::updateOrCreate(
            [
                'route_id' => $validated['route_id'],
                'day_of_week' => $validated['day'],
                'time_slot' => $validated['time_slot'],
            ],
            [
                'threshold_count' => $validated['threshold']
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Threshold successfully updated in database!'
        ]);
    }

    /**
     * Simulator: Add commuter pending check-in.
     */
    public function addCommuter(Request $request)
    {
        $routeId = $request->input('route_id');
        $routeStops = Stop::where('route_id', $routeId)->orderBy('sequence')->get();
        if ($routeStops->count() >= 2) {
            $token = 'simulated-' . \Illuminate\Support\Str::random(32);
            \App\Models\CommuterSession::create([
                'session_token' => $token,
                'ip_address' => $request->ip(),
                'expires_at' => now()->addHours(24),
            ]);

            CommuterTrip::create([
                'session_token' => $token,
                'route_id' => $routeId,
                'origin_stop_id' => $routeStops->first()->id,
                'destination_stop_id' => $routeStops->last()->id,
                'status' => 'WAITING',
                'created_at' => now(),
            ]);
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false, 'message' => 'Insufficient route stops'], 400);
    }

    /**
     * Simulator: Add manual count ticker from drivers.
     */
    public function addManualTicker(Request $request)
    {
        $routeId = (int) $request->input('route_id');
        $defaultDay = $this->getSimulationDefault('default_simulated_day', Carbon::now()->englishDayOfWeek);
        $day = $request->input('day', $defaultDay);
        $timeSlot = $request->input('time_slot', $this->getDefaultTimeSlot());

        // Create or update simulator count in database
        $simulatorCount = DispatchSimulatorCount::updateOrCreate(
            [
                'route_id' => $routeId,
                'day_of_week' => $day,
                'time_slot' => $timeSlot,
            ],
            [
                'manual_count' => DB::raw('manual_count + 1')
            ]
        );

        return response()->json(['success' => true, 'new_count' => $simulatorCount->manual_count]);
    }

    /**
     * Simulator: Simulate rush hour surge spurt.
     */
    public function simulateRushSpurt(Request $request)
    {
        $routeId = $request->input('route_id');
        $defaultDay = $this->getSimulationDefault('default_simulated_day', Carbon::now()->englishDayOfWeek);
        $day = $request->input('day', $defaultDay);
        $timeSlot = $request->input('time_slot', $this->getDefaultTimeSlot());

        $defaultThreshold = $this->getSimulationDefault('default_demand_threshold', 20);

        $thresholdRec = DemandThreshold::where('route_id', $routeId)
            ->where('day_of_week', $day)
            ->where('time_slot', $timeSlot)
            ->first();
        $limit = $thresholdRec ? $thresholdRec->threshold_count : $defaultThreshold;

        $currentPending = CommuterTrip::where('route_id', $routeId)
            ->where('status', 'WAITING')
            ->whereHas('session', function ($q) {
                $q->where('expires_at', '>', now());
            })
            ->count();
        $minSpurt = (int) $this->getSimulationDefault('sim_rush_spurt_min', 2);
        $maxSpurt = (int) $this->getSimulationDefault('sim_rush_spurt_max', 5);
        $needed = max(0, $limit - $currentPending + rand($minSpurt, $maxSpurt));

        $routeStops = Stop::where('route_id', $routeId)->orderBy('sequence')->get();
        if ($routeStops->count() >= 2) {
            for ($i = 0; $i < $needed; $i++) {
                $token = 'simulated-' . \Illuminate\Support\Str::random(32);
                \App\Models\CommuterSession::create([
                    'session_token' => $token,
                    'ip_address' => $request->ip(),
                    'expires_at' => now()->addHours(24),
                ]);

                CommuterTrip::create([
                    'session_token' => $token,
                    'route_id' => $routeId,
                    'origin_stop_id' => $routeStops->first()->id,
                    'destination_stop_id' => $routeStops->last()->id,
                    'status' => 'WAITING',
                    'created_at' => now()->subMinutes(rand(1, 5)),
                ]);
            }
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false, 'message' => 'Insufficient route stops'], 400);
    }

    /**
     * Simulator: Reset simulator.
     */
    public function clearSimulatorData()
    {
        CommuterTrip::where('status', 'WAITING')->delete();
        DispatchSimulatorCount::truncate();

        return response()->json([
            'success' => true,
            'message' => 'Simulator data successfully reset!'
        ]);
    }

    /**
     * Dispatch Bus and Driver onto Route.
     */
    public function dispatchNow(Request $request)
    {
        $routeId = $request->input('route_id');
        $defaultPhase = $this->getSimulationDefault('phase_default', 1);
        $phase = $request->input('phase', $defaultPhase);
        $route = Route::find($routeId);

        if (!$route) {
            return response()->json(['success' => false, 'message' => 'Route not found.'], 404);
        }

        // Prefer a bus already assigned to this route; fall back to any inactive bus.
        $bus = Bus::where('status', 'inactive')->where('route_id', $routeId)->first()
            ?? Bus::where('status', 'inactive')->whereNull('route_id')->first()
            ?? Bus::where('status', 'inactive')->first();

        // Prefer a driver whose assigned_route matches this route; fall back to any inactive driver.
        $driver = Driver::where('status', 'inactive')->where('assigned_route', (string) $routeId)->first()
            ?? Driver::where('status', 'inactive')->whereNull('assigned_route')->first()
            ?? Driver::where('status', 'inactive')->first();

        if (!$bus || !$driver) {
            return response()->json([
                'success' => false,
                'message' => 'No available buses or drivers. All fleet units are currently active.'
            ], 422);
        }

        $fallbackTerminal = $this->getSimulationDefault(
            'default_terminal',
            SystemSetting::get('default_terminal_name', Terminal::getDefaultName())
        );

        DB::beginTransaction();
        try {
            // Activate bus
            $bus->update([
                'status' => 'active',
                'route_id' => $routeId,
                'driver_name' => "{$driver->first_name} {$driver->last_name}",
                'passengers' => 0,
                'next_stop' => Stop::where('route_id', $routeId)->orderBy('sequence')->first()->name ?? $fallbackTerminal,
                'eta' => (int) SystemSetting::get('default_dispatch_eta_minutes', 5),
            ]);

            // Activate driver
            $driver->update([
                'status' => 'active',
                'assigned_bus' => $bus->plate_number,
                'assigned_route' => (string) $routeId,
            ]);

            // Create trip
            $tripId = DB::table('trips')->insertGetId([
                'bus_id' => $bus->id,
                'driver_id' => $driver->id,
                'route_id' => $routeId,
                'status' => 'ongoing',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create Dispatch Log
            DispatchLog::create([
                'trip_id' => $tripId,
                'dispatched_by' => Auth::id(), // NULL when system-triggered (no active session)
                'dispatched_at' => now(),
                'notes' => 'Automatic dispatch triggered by Dispatch Intelligence (Phase ' . $phase . ').',
            ]);

            // Mark check-ins as boarded
            CommuterTrip::where('route_id', $routeId)
                ->where('status', 'WAITING')
                ->update([
                    'status' => 'ON_BUS',
                    'boarded_at' => now()
                ]);

            // Reset manual count ticks in database
            DispatchSimulatorCount::where('route_id', $routeId)->update(['manual_count' => 0]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bus successfully dispatched to ' . $route->name . '! Check-ins boarding...'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Dispatch failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get default time slot from database configuration based on current hour
     */
    protected function getDefaultTimeSlot()
    {
        $timeSlotConfig = TimeSlotConfiguration::getTimeSlotByHour();

        if ($timeSlotConfig) {
            return $timeSlotConfig->time_slot_display;
        }

        // Use SystemSetting getter with fallback
        $defaultTimeSlot = DispatchSimulationDefault::getValue('default_time_slot', SystemSetting::get('default_time_slot'));

        // Fallback to first configured time slot or database configuration
        return TimeSlotConfiguration::orderBy('order')->first()?->time_slot_display ?? $defaultTimeSlot;
    }

    /**
     * Get simulator default values from the database.
     */
    protected function getSimulationDefault(string $key, $default = null)
    {
        if (Schema::hasTable('dispatch_simulation_defaults')) {
            $value = DispatchSimulationDefault::getValue($key, null);
            if ($value !== null) {
                return $value;
            }
        }

        return SystemSetting::get($key, $default);
    }

    /**
     * Fetch active route demand counts from database
     */
    public function fetchRoutesData($day, $timeSlot, $phase)
    {
        $defaultThreshold = (int) $this->getSimulationDefault('default_demand_threshold', 20);

        // Prefer dedicated dispatch alert settings table; fall back to system settings
        if (Schema::hasTable('dispatch_alert_settings')) {
            $alertSettings = DispatchAlertSetting::latest()->first();
        } else {
            $alertSettings = null;
        }

        if ($alertSettings) {
            $yellowPercentage = (int) $alertSettings->yellow_percentage;
            $redPercentage = (int) $alertSettings->red_percentage;
        } else {
            $yellowPercentage = (int) SystemSetting::get('alert_yellow_percentage', 50);
            $redPercentage = (int) SystemSetting::get('alert_red_percentage', 100);
        }

        $routes = Route::getAllCached();

        $autoCounts = CommuterTrip::where('status', 'WAITING')
            ->whereHas('session', function ($q) {
                $q->where('expires_at', '>', now());
            })
            ->select('route_id', DB::raw('count(*) as count'))
            ->groupBy('route_id')
            ->pluck('count', 'route_id')
            ->toArray();

        $simulatorCounts = DispatchSimulatorCount::where('day_of_week', $day)
            ->where('time_slot', $timeSlot)
            ->pluck('manual_count', 'route_id')
            ->toArray();

        $thresholds = DemandThreshold::where('day_of_week', $day)
            ->where('time_slot', $timeSlot)
            ->pluck('threshold_count', 'route_id')
            ->toArray();

        $historicalAverages = DemandHistory::where('day_of_week', $day)
            ->where('time_slot', $timeSlot)
            ->select('route_id', DB::raw('avg(total_commuters) as avg_commuters'))
            ->groupBy('route_id')
            ->pluck('avg_commuters', 'route_id')
            ->toArray();

        return $routes->map(function ($route) use ($day, $timeSlot, $defaultThreshold, $yellowPercentage, $redPercentage, $autoCounts, $simulatorCounts, $thresholds, $historicalAverages) {
            $autoCount = $autoCounts[$route->id] ?? 0;
            $manualCount = $simulatorCounts[$route->id] ?? 0;
            $totalDemand = $autoCount + $manualCount;

            $threshold = $thresholds[$route->id] ?? $defaultThreshold;

            // Status indicator
            $status = 'green';
            if ($totalDemand >= ($threshold * ($redPercentage / 100))) {
                $status = 'red';
            } elseif ($totalDemand >= ($threshold * ($yellowPercentage / 100))) {
                $status = 'yellow';
            }

            $historicalAvg = $historicalAverages[$route->id] ?? 0;

            // Auto-suggest the nearest available (inactive) bus to the route's first stop if red status
            $suggestedBusData = null;
            if ($status === 'red') {
                $firstStop = Stop::where('route_id', $route->id)->orderBy('sequence')->first();
                $firstLat = $firstStop ? (float) $firstStop->lat : 14.5593;
                $firstLng = $firstStop ? (float) $firstStop->lng : 121.0805;

                $inactiveBuses = Bus::where('status', 'inactive')->get();
                $minDist = null;
                $bestBus = null;

                foreach ($inactiveBuses as $bus) {
                    if ($bus->lat !== null && $bus->lng !== null) {
                        $dist = \App\Services\GPSKalmanFilter::calculateDistance($firstLat, $firstLng, (float) $bus->lat, (float) $bus->lng);
                        if ($minDist === null || $dist < $minDist) {
                            $minDist = $dist;
                            $bestBus = $bus;
                        }
                    }
                }

                if ($bestBus) {
                    $suggestedBusData = [
                        'id' => $bestBus->id,
                        'plate_number' => $bestBus->plate_number,
                        'distance_km' => $minDist !== null ? round($minDist / 1000, 1) : 0
                    ];
                }
            }

            return (object) [
                'id' => $route->id,
                'name' => $route->name,
                'description' => $route->description,
                'auto_count' => $autoCount,
                'manual_count' => $manualCount,
                'total' => $totalDemand,
                'threshold' => $threshold,
                'status' => $status,
                'historical_avg' => round($historicalAvg),
                'suggested_bus' => $suggestedBusData,
            ];
        });
    }

    /**
     * Compute Active Alerts
     */
    protected function computeAlerts($day, $timeSlot, $phase, $routesData)
    {
        $alerts = [];
        $locale = app()->getLocale();

        foreach ($routesData as $route) {
            // Phase 1: Reactive Alert
            if ($phase == 1) {
                if ($route->total >= $route->threshold) {
                    $title = SystemSetting::get('alert_template_reactive_title_' . $locale, SystemSetting::get('alert_template_reactive_title_en', 'Threshold Overflow Alarm'));
                    $message = $this->parseAlertTemplate('alert_template_reactive_message', [
                        'total' => $route->total,
                        'route_name' => $route->name,
                        'threshold' => $route->threshold
                    ], "{total} commuters waiting on {route_name}. Limit of {threshold} exceeded! Dispatch recommended.");

                    $alerts[] = [
                        'route_id' => $route->id,
                        'route_name' => $route->name,
                        'type' => 'reactive',
                        'severity' => 'High',
                        'title' => $title,
                        'message' => $message,
                    ];
                }
            }
            // Phase 2 and 3: Pattern-based Predictive Alerts
            else {
                $historicalAvg = DemandHistory::where('route_id', $route->id)
                    ->where('day_of_week', $day)
                    ->where('time_slot', $timeSlot)
                    ->avg('total_commuters');

                if ($historicalAvg && $historicalAvg >= $route->threshold) {
                    $slotStart = explode('-', $timeSlot)[0] ?? '7:00 AM';
                    $title = SystemSetting::get('alert_template_predictive_title_' . $locale, SystemSetting::get('alert_template_predictive_title_en', '⚡ Pre-dispatch Recommended'));
                    $message = $this->parseAlertTemplate('alert_template_predictive_message', [
                        'day' => $day,
                        'slot_start' => $slotStart,
                        'route_name' => $route->name,
                        'historical_avg' => round($historicalAvg)
                    ], "Every {day} at {slot_start} = passenger demand is consistently high on {route_name} (Avg: {historical_avg} pax expected). Dispatch a bus now to pre-empt overflow.");

                    $alerts[] = [
                        'route_id' => $route->id,
                        'route_name' => $route->name,
                        'type' => 'predictive',
                        'severity' => 'Medium',
                        'title' => $title,
                        'message' => $message,
                    ];
                }

                if ($route->total >= $route->threshold) {
                    $title = SystemSetting::get('alert_template_reactive_live_title_' . $locale, SystemSetting::get('alert_template_reactive_live_title_en', 'Threshold Overflow Alarm (Live)'));
                    $message = $this->parseAlertTemplate('alert_template_reactive_live_message', [
                        'total' => $route->total,
                        'route_name' => $route->name,
                        'threshold' => $route->threshold
                    ], "{total} commuters waiting on {route_name} (Live counter). Threshold is {threshold}! Dispatch immediately.");

                    $alerts[] = [
                        'route_id' => $route->id,
                        'route_name' => $route->name,
                        'type' => 'reactive',
                        'severity' => 'High',
                        'title' => $title,
                        'message' => $message,
                    ];
                }
            }
        }
        return $alerts;
    }

    /**
     * Parse alert template with dynamic data.
     */
    protected function parseAlertTemplate(string $key, array $data, string $default): string
    {
        $locale = app()->getLocale();
        $template = SystemSetting::get("{$key}_{$locale}", SystemSetting::get("{$key}_en", $default));
        
        $search = array_map(fn($k) => "{{$k}}", array_keys($data));
        return str_replace($search, array_values($data), $template);
    }
}
