<?php

namespace App\Http\Controllers\Fleet;

use App\Exceptions\BusUnavailableException;
use App\Exceptions\DispatchException;
use App\Exceptions\DriverUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\DemandHistory;
use App\Models\DemandThreshold;
use App\Models\DispatchAlertSetting;
use App\Models\DispatchLog;
use App\Models\DispatchSimulationDefault;
use App\Models\DispatchSimulatorCount;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\Stop;
use App\Models\SystemSetting;
use App\Models\TimeSlotConfiguration;
use App\Services\CentralDispatchEligibilityService;
use App\Services\DemandForecastShadowService;
use App\Services\DirectionAwareDemandForecastService;
use App\Services\GPSKalmanFilter;
use App\Services\ReactiveDispatchDecisionService;
use App\Services\RouteVariantSelectionService;
use App\Services\SimulationDispatchService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DispatchIntelligenceController extends Controller
{
    private ?array $tomorrowDemandForecast = null;

    private ?array $forecastShadowDashboard = null;

    /**
     * Display the Dispatch Intelligence index page.
     */
    public function index(Request $request)
    {
        return view('fleet.dispatch-intelligence.index', $this->viewData($request));
    }

    /**
     * Build the Dispatch Intelligence view payload for both direct and fragment renders.
     */
    public function viewData(Request $request): array
    {
        $defaultPhase = $this->getSimulationDefault('phase_default', 1);
        $defaultDay = $this->getSimulationDefault('default_simulated_day', Carbon::now()->englishDayOfWeek);
        $defaultRouteId = $this->getSimulationDefault('route_default', $this->dispatchIntelligenceRoutes()->first()?->id);
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
        $historicalPatterns = DemandHistory::forecastEligible()
            ->with(['route', 'routeVariant'])
            ->whereIn('route_id', $this->activePublicRouteIds())
            ->orderBy('total_commuters', 'desc')
            ->take(8)
            ->get();

        // Load recent dispatches
        $recentDispatches = DispatchLog::with(['trip.route', 'trip.routeVariant', 'trip.bus', 'trip.driver', 'dispatcher'])
            ->latest()
            ->take(6)
            ->get();

        return [
            'selectedPhase' => $selectedPhase,
            'simulatedDay' => $simulatedDay,
            'simulatedTimeSlot' => $simulatedTimeSlot,
            'selectedRouteId' => $selectedRouteId,
            'customThreshold' => $customThreshold,
            'routesData' => $routesData,
            'demandForecast' => $this->tomorrowDemandForecast(),
            'forecastShadow' => $this->forecastShadowDashboard(),
            'historicalPatterns' => $historicalPatterns,
            'recentDispatches' => $recentDispatches,
        ];
    }

    /**
     * Get JSON data payload for dynamic updates.
     */
    public function getDispatchData(Request $request)
    {
        $defaultPhase = $this->getSimulationDefault('phase_default', 1);
        $defaultDay = $this->getSimulationDefault('default_simulated_day', Carbon::now()->englishDayOfWeek);
        $defaultRouteId = $this->getSimulationDefault('route_default', $this->dispatchIntelligenceRoutes()->first()?->id);
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
        $recentDispatches = DispatchLog::with(['trip.route', 'trip.routeVariant', 'trip.bus', 'trip.driver', 'dispatcher'])
            ->latest()
            ->take(6)
            ->get()->map(function ($log) {
                return [
                    'id' => $log->id,
                    'route_id' => $log->trip ? $log->trip->route_id : null,
                    'route_name' => ($log->trip && $log->trip->route) ? $log->trip->route->name : 'Route',
                    // ISSUE-035 FIX: Include the DB route color so the JS doesn't need a hardcoded palette.
                    'route_color' => ($log->trip && $log->trip->route) ? ($log->trip->route->color ?: '#003F87') : '#003F87',
                    'route_variant_id' => $log->trip ? $log->trip->route_variant_id : null,
                    'direction' => ($log->trip && $log->trip->routeVariant) ? $log->trip->routeVariant->direction : null,
                    'origin_name' => ($log->trip && $log->trip->routeVariant) ? $log->trip->routeVariant->origin_name : null,
                    'destination_name' => ($log->trip && $log->trip->routeVariant) ? $log->trip->routeVariant->destination_name : null,
                    'bus_plate' => ($log->trip && $log->trip->bus) ? $log->trip->bus->plate_number : '—',
                    'driver_name' => ($log->trip && $log->trip->driver) ? "{$log->trip->driver->first_name} {$log->trip->driver->last_name}" : '—',
                    'notes' => $log->notes,
                    'time_diff' => Carbon::parse($log->dispatched_at)->diffForHumans(),
                ];
            });

        // Historical patterns
        $historicalPatterns = DemandHistory::forecastEligible()
            ->with(['route', 'routeVariant'])
            ->whereIn('route_id', $this->activePublicRouteIds())
            ->orderBy('total_commuters', 'desc')
            ->take(8)
            ->get()->map(function ($p) {
                return [
                    'id' => $p->id,
                    'route_id' => $p->route_id,
                    'route_name' => $p->route?->name,
                    'route_variant_id' => $p->route_variant_id,
                    'direction' => $p->routeVariant?->direction,
                    'day_of_week' => $p->day_of_week,
                    'total_commuters' => $p->total_commuters,
                    'time_slot' => $p->time_slot,
                ];
            });

        return response()->json([
            'routesData' => $routesData,
            'activeAlerts' => $activeAlerts,
            'demandForecast' => $this->tomorrowDemandForecast(),
            'forecastShadow' => $this->forecastShadowDashboard(),
            'customThreshold' => $customThreshold,
            'recentDispatches' => $recentDispatches,
            'historicalPatterns' => $historicalPatterns,
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
            'route_id' => ['required', 'exists:routes,id', Rule::in($this->activePublicRouteIds())],
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
                'threshold_count' => $validated['threshold'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Threshold successfully updated in database!',
        ]);
    }

    /**
     * Simulator: Add commuter pending check-in.
     */
    public function addCommuter(Request $request)
    {
        $routeId = (int) $request->input('route_id');
        if (! in_array($routeId, $this->activePublicRouteIds(), true)) {
            return response()->json(['success' => false, 'message' => 'Only official production routes are available for new operations.'], 422);
        }

        $route = Route::findOrFail($routeId);
        $variantId = $request->filled('route_variant_id') ? (int) $request->input('route_variant_id') : null;

        if ($this->createSimulatedWaitingTrip($route, $request->ip(), $variantId)) {
            return response()->json([
                'success' => true,
                'message' => 'Simulated app commuter added.',
            ]);
        }

        return response()->json(['success' => false, 'message' => 'No usable route direction available for simulator check-in.'], 400);
    }

    /**
     * Simulator: Add manual count ticker from drivers.
     */
    public function addManualTicker(Request $request)
    {
        $routeId = (int) $request->input('route_id');
        if (! in_array($routeId, $this->activePublicRouteIds(), true)) {
            return response()->json(['success' => false, 'message' => 'Only official production routes are available for new operations.'], 422);
        }

        $defaultDay = $this->getSimulationDefault('default_simulated_day', Carbon::now()->englishDayOfWeek);
        $day = $request->input('day', $defaultDay);
        $timeSlot = $request->input('time_slot', $this->getDefaultTimeSlot());

        $simulatorCount = DispatchSimulatorCount::firstOrCreate(
            [
                'route_id' => $routeId,
                'day_of_week' => $day,
                'time_slot' => $timeSlot,
            ],
            [
                'manual_count' => 0,
            ]
        );
        $simulatorCount->increment('manual_count');
        $simulatorCount->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Simulated driver passenger count added.',
            'new_count' => $simulatorCount->manual_count,
        ]);
    }

    /**
     * Simulator: Simulate rush hour surge spurt.
     */
    public function simulateRushSpurt(Request $request)
    {
        $routeId = (int) $request->input('route_id');
        if (! in_array($routeId, $this->activePublicRouteIds(), true)) {
            return response()->json(['success' => false, 'message' => 'Only official production routes are available for new operations.'], 422);
        }

        $defaultDay = $this->getSimulationDefault('default_simulated_day', Carbon::now()->englishDayOfWeek);
        $day = $request->input('day', $defaultDay);
        $timeSlot = $request->input('time_slot', $this->getDefaultTimeSlot());

        $defaultThreshold = $this->getSimulationDefault('default_demand_threshold', 20);

        $thresholdRec = DemandThreshold::where('route_id', $routeId)
            ->where('day_of_week', $day)
            ->where('time_slot', $timeSlot)
            ->first();
        $limit = $thresholdRec ? $thresholdRec->threshold_count : $defaultThreshold;

        $variantId = $request->filled('route_variant_id') ? (int) $request->input('route_variant_id') : null;
        $route = Route::findOrFail($routeId);
        $simulatorVariant = $this->simulatorVariantForRoute($route, $variantId);

        if (! $simulatorVariant) {
            return response()->json(['success' => false, 'message' => 'No usable route direction available for simulator spurt.'], 400);
        }

        $currentPending = CommuterTrip::where('route_id', $routeId)
            ->where('route_variant_id', $simulatorVariant->id)
            ->where('status', 'WAITING')
            ->where('is_simulated', true)
            ->whereHas('session', function ($q) {
                $q->where('expires_at', '>', now());
            })
            ->count();
        $minSpurt = (int) $this->getSimulationDefault('sim_rush_spurt_min', 2);
        $maxSpurt = (int) $this->getSimulationDefault('sim_rush_spurt_max', 5);
        $needed = max(0, $limit - $currentPending + rand($minSpurt, $maxSpurt));

        for ($i = 0; $i < $needed; $i++) {
            $this->createSimulatedWaitingTrip(
                $route,
                $request->ip(),
                $simulatorVariant->id,
                now()->subMinutes(rand(1, 5))
            );
        }

        return response()->json([
            'success' => true,
            'message' => $needed.' simulated commuter'.($needed === 1 ? '' : 's').' added.',
            'created_count' => $needed,
        ]);
    }

    /**
     * Simulator: Reset simulator.
     */
    public function clearSimulatorData()
    {
        CommuterTrip::where('status', 'WAITING')
            ->where(function ($query) {
                $query->where('is_simulated', true)
                    ->orWhere('session_token', 'like', 'simulated-%');
            })
            ->delete();
        DispatchSimulatorCount::truncate();

        return response()->json([
            'success' => true,
            'message' => 'Simulator data successfully reset!',
        ]);
    }

    /**
     * Dispatch Bus and Driver onto Route.
     */
    public function dispatchNow(Request $request)
    {
        $defaultPhase = $this->getSimulationDefault('phase_default', 1);
        $validated = $request->validate([
            'route_id' => ['required', 'exists:routes,id', Rule::in($this->canonicalRouteIds())],
            'route_variant_id' => 'nullable|integer|exists:route_variants,id',
            'phase' => 'nullable',
        ]);

        $routeId = (int) $validated['route_id'];
        $phase = $validated['phase'] ?? $defaultPhase;
        $selectedRouteVariantId = array_key_exists('route_variant_id', $validated) && $validated['route_variant_id'] !== null
            ? (int) $validated['route_variant_id']
            : null;
        $route = Route::findOrFail($routeId);

        $availableBuses = Bus::all()
            ->filter(fn ($bus) => CentralDispatchEligibilityService::busIsEligible($bus));

        $bus = $availableBuses->first();

        $availableDrivers = Driver::all()
            ->filter(fn ($driver) => CentralDispatchEligibilityService::driverIsEligible($driver));

        $driver = $availableDrivers->first();

        try {
            if (! $bus) {
                throw new BusUnavailableException('No available buses. All fleet units are currently active.');
            }

            if (! $driver) {
                throw new DriverUnavailableException('No available drivers. All fleet units are currently active.');
            }

            $routeVariant = app(RouteVariantSelectionService::class)->resolveForDispatch($route, $selectedRouteVariantId);

            if (! $routeVariant) {
                throw ValidationException::withMessages([
                    'route_variant_id' => 'Select an official route direction before dispatching.',
                ]);
            }

            $reactiveDecision = app(ReactiveDispatchDecisionService::class)
                ->assertCanDispatch($route, $routeVariant, Carbon::now('Asia/Manila'));

            DB::beginTransaction();

            $trip = SimulationDispatchService::dispatch(
                $bus,
                $driver,
                $route,
                Auth::id(),
                'Reactive dispatch confirmed through Dispatch Intelligence (Phase '.$phase.').',
                $routeVariant
            );

            DispatchSimulatorCount::where('route_id', $routeId)->update(['manual_count' => 0]);

            DB::commit();
            $trip->loadMissing('routeVariant');

            return response()->json([
                'success' => true,
                'message' => 'Bus successfully dispatched to '.$route->name.'. Commuter boarding remains geofence-confirmed.',
                'trip_id' => $trip->id,
                'route_variant_id' => $trip->route_variant_id,
                'direction' => $trip->routeVariant?->direction,
                'origin_name' => $trip->routeVariant?->origin_name,
                'destination_name' => $trip->routeVariant?->destination_name,
                'reactive_waiting_count' => $reactiveDecision['waiting_count'],
            ]);
        } catch (DispatchException|ValidationException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return response()->json([
                'success' => false,
                'message' => 'Dispatch failed: '.$e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return response()->json([
                'success' => false,
                'message' => 'Dispatch failed: '.$e->getMessage(),
            ], 500);
        }
    }

    private function canonicalRouteIds(): array
    {
        return Route::getCanonicalProductionCached()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function activePublicRouteIds(): array
    {
        return $this->dispatchIntelligenceRoutes()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function createSimulatedWaitingTrip(Route $route, ?string $ipAddress, ?int $routeVariantId = null, ?Carbon $createdAt = null): bool
    {
        $variant = $this->simulatorVariantForRoute($route, $routeVariantId);
        $variantStops = $variant?->stops?->sortBy('sequence')->values();

        if (! $variant || ! $variantStops || $variantStops->count() < 2) {
            return false;
        }

        $origin = $variantStops->first();
        $destination = $variantStops->last();
        $token = 'simulated-'.Str::random(32);

        CommuterSession::create([
            'session_token' => $token,
            'ip_address' => $ipAddress,
            'expires_at' => now()->addHours(24),
        ]);

        CommuterTrip::create([
            'session_token' => $token,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'origin_stop_id' => $origin->canonical_stop_id,
            'destination_stop_id' => $destination->canonical_stop_id,
            'origin_route_variant_stop_id' => $origin->id,
            'destination_route_variant_stop_id' => $destination->id,
            'status' => 'WAITING',
            'is_simulated' => true,
            'created_at' => $createdAt ?? now(),
        ]);

        return true;
    }

    private function simulatorVariantForRoute(Route $route, ?int $routeVariantId = null): ?RouteVariant
    {
        $routeVariantSelection = app(RouteVariantSelectionService::class);
        $query = RouteVariant::with(['stops' => fn ($stops) => $stops->orderBy('sequence')])
            ->where('route_id', $route->id);

        if ($routeVariantId !== null) {
            $variant = (clone $query)->whereKey($routeVariantId)->first();

            return $variant && $routeVariantSelection->isUsableForLiveDispatch($variant) ? $variant : null;
        }

        return $query->get()
            ->filter(fn (RouteVariant $variant): bool => $routeVariantSelection->isUsableForLiveDispatch($variant))
            ->sortByDesc(fn (RouteVariant $variant): int => $variant->is_default ? 1 : 0)
            ->first();
    }

    private function dispatchIntelligenceRoutes()
    {
        return Route::publicCommuterActiveService()->get();
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

        $routes = $this->dispatchIntelligenceRoutes();
        $routeIds = $routes->pluck('id')->map(fn ($id) => (int) $id)->all();
        $forecastSummaries = collect($this->tomorrowDemandForecast()['route_summaries'])
            ->keyBy('route_id');

        $realWaitingQuery = CommuterTrip::where('status', 'WAITING')
            ->where('is_simulated', false)
            ->whereIn('route_id', $routeIds)
            ->whereHas('session', function ($q) {
                $q->where('expires_at', '>', now());
            });

        $variantWaitingCounts = (clone $realWaitingQuery)
            ->whereNotNull('route_variant_id')
            ->select('route_id', 'route_variant_id', DB::raw('count(*) as count'))
            ->groupBy('route_id', 'route_variant_id')
            ->get()
            ->groupBy('route_id')
            ->map(fn ($counts) => $counts->pluck('count', 'route_variant_id')->toArray())
            ->toArray();

        $allWaitingCounts = (clone $realWaitingQuery)
            ->select('route_id', DB::raw('count(*) as count'))
            ->groupBy('route_id')
            ->pluck('count', 'route_id')
            ->toArray();

        $simulatedWaitingQuery = CommuterTrip::where('status', 'WAITING')
            ->where('is_simulated', true)
            ->whereIn('route_id', $routeIds)
            ->whereHas('session', function ($q) {
                $q->where('expires_at', '>', now());
            });

        $simulatedVariantWaitingCounts = (clone $simulatedWaitingQuery)
            ->whereNotNull('route_variant_id')
            ->select('route_id', 'route_variant_id', DB::raw('count(*) as count'))
            ->groupBy('route_id', 'route_variant_id')
            ->get()
            ->groupBy('route_id')
            ->map(fn ($counts) => $counts->pluck('count', 'route_variant_id')->toArray())
            ->toArray();

        $simulatedWaitingCounts = (clone $simulatedWaitingQuery)
            ->select('route_id', DB::raw('count(*) as count'))
            ->groupBy('route_id')
            ->pluck('count', 'route_id')
            ->toArray();

        $simulatorCounts = DispatchSimulatorCount::where('day_of_week', $day)
            ->where('time_slot', $timeSlot)
            ->whereIn('route_id', $routeIds)
            ->pluck('manual_count', 'route_id')
            ->toArray();

        $thresholds = DemandThreshold::where('day_of_week', $day)
            ->where('time_slot', $timeSlot)
            ->whereIn('route_id', $routeIds)
            ->pluck('threshold_count', 'route_id')
            ->toArray();

        $routeVariantSelection = app(RouteVariantSelectionService::class);
        $variantsByRoute = RouteVariant::with(['stops' => fn ($query) => $query->orderBy('sequence')])
            ->withCount('stops')
            ->whereIn('route_id', $routeIds)
            ->get()
            ->groupBy('route_id');

        return $routes->map(function ($route) use ($defaultThreshold, $yellowPercentage, $redPercentage, $variantWaitingCounts, $allWaitingCounts, $simulatedVariantWaitingCounts, $simulatedWaitingCounts, $simulatorCounts, $thresholds, $forecastSummaries, $variantsByRoute, $routeVariantSelection) {
            $routeVariants = $variantsByRoute->get($route->id, collect());
            $routeVariantWaitingCounts = $variantWaitingCounts[$route->id] ?? [];
            $simulatedRouteVariantWaitingCounts = $simulatedVariantWaitingCounts[$route->id] ?? [];
            $variants = $routeVariants->map(function ($variant) use ($routeVariantSelection, $routeVariantWaitingCounts, $simulatedRouteVariantWaitingCounts) {
                $waitingCount = (int) ($routeVariantWaitingCounts[$variant->id] ?? 0);
                $simulatedWaitingCount = (int) ($simulatedRouteVariantWaitingCounts[$variant->id] ?? 0);

                return [
                    'id' => $variant->id,
                    'route_id' => $variant->route_id,
                    'direction' => $variant->direction,
                    'origin_name' => $variant->origin_name,
                    'destination_name' => $variant->destination_name,
                    'geometry_status' => $variant->geometry_status,
                    'is_default' => (bool) $variant->is_default,
                    'label' => $routeVariantSelection->label($variant),
                    'usable_for_dispatch' => $routeVariantSelection->isUsableForLiveDispatch($variant),
                    'waiting_count' => $waitingCount,
                    'simulated_waiting_count' => $simulatedWaitingCount,
                    'stops' => $variant->stops->map(fn ($stop) => [
                        'id' => $stop->id,
                        'name' => $stop->name,
                        'lat' => $stop->lat,
                        'lng' => $stop->lng,
                        'sequence' => $stop->sequence,
                        'stop_type' => $stop->stop_type,
                    ])->values(),
                ];
            })->values();

            $autoCount = (int) $variants->sum('waiting_count');
            $unresolvedWaitingCount = max(0, (int) ($allWaitingCounts[$route->id] ?? 0) - $autoCount);
            $criticalVariant = $variants->sortByDesc('waiting_count')->first();
            $maxDirectionWaitingCount = (int) ($criticalVariant['waiting_count'] ?? 0);
            $manualCount = $simulatorCounts[$route->id] ?? 0;
            $simulatedAppCount = (int) ($simulatedWaitingCounts[$route->id] ?? 0);
            $simulatorTotal = $simulatedAppCount + $manualCount;
            $totalDemand = $autoCount;

            $threshold = $thresholds[$route->id] ?? $defaultThreshold;

            // Status indicator
            $status = 'green';
            if ($maxDirectionWaitingCount >= ($threshold * ($redPercentage / 100))) {
                $status = 'red';
            } elseif ($maxDirectionWaitingCount >= ($threshold * ($yellowPercentage / 100))) {
                $status = 'yellow';
            }

            // Auto-suggest the nearest Central Dispatch eligible bus to the route's first stop if red status
            $suggestedBusData = null;
            if ($status === 'red') {
                $criticalVariantModel = $criticalVariant
                    ? $routeVariants->firstWhere('id', $criticalVariant['id'])
                    : null;
                $firstStop = $criticalVariantModel?->stops->sortBy('sequence')->first()
                    ?? Stop::where('route_id', $route->id)->orderBy('sequence')->first();
                $firstLat = $firstStop ? (float) $firstStop->lat : (float) SystemSetting::get('map_default_latitude', 14.5593);
                $firstLng = $firstStop ? (float) $firstStop->lng : (float) SystemSetting::get('map_default_longitude', 121.0805);

                $eligibleBuses = Bus::all()->filter(fn ($bus) => CentralDispatchEligibilityService::busIsEligible($bus));
                $minDist = null;
                $bestBus = null;

                foreach ($eligibleBuses as $bus) {
                    if ($bus->lat !== null && $bus->lng !== null) {
                        $dist = GPSKalmanFilter::calculateDistance($firstLat, $firstLng, (float) $bus->lat, (float) $bus->lng);
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
                        'distance_km' => $minDist !== null ? round($minDist / 1000, 1) : 0,
                    ];
                }
            }

            return (object) [
                'id' => $route->id,
                'name' => $route->name,
                'description' => $route->description,
                'auto_count' => $autoCount,
                'unresolved_waiting_count' => $unresolvedWaitingCount,
                'manual_count' => $manualCount,
                'simulated_app_count' => $simulatedAppCount,
                'total' => $totalDemand,
                'max_direction_waiting_count' => $maxDirectionWaitingCount,
                'simulator_total' => $simulatorTotal,
                'threshold' => $threshold,
                'status' => $status,
                'forecast_summary' => $forecastSummaries->get((int) $route->id),
                'suggested_bus' => $suggestedBusData,
                'critical_variant' => $criticalVariant,
                'variants' => $variants,
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
            foreach ($this->reactiveVariantsAtThreshold($route) as $variant) {
                $routeDirection = $route->name.' '.ucfirst((string) $variant['direction']);
                $titleKey = $phase == 1 ? 'alert_template_reactive_title' : 'alert_template_reactive_live_title';
                $messageKey = $phase == 1 ? 'alert_template_reactive_message' : 'alert_template_reactive_live_message';
                $defaultTitle = $phase == 1 ? 'Threshold Overflow Alarm' : 'Threshold Overflow Alarm (Live)';
                $defaultMessage = $phase == 1
                    ? '{total} commuters waiting on {route_name}. Limit of {threshold} exceeded! Dispatch recommended.'
                    : '{total} commuters waiting on {route_name} (Live counter). Threshold is {threshold}! Dispatch immediately.';
                $title = SystemSetting::get($titleKey.'_'.$locale, SystemSetting::get($titleKey.'_en', $defaultTitle));
                $message = $this->parseAlertTemplate($messageKey, [
                    'total' => $variant['waiting_count'],
                    'route_name' => $routeDirection,
                    'threshold' => $route->threshold,
                ], $defaultMessage);

                $alerts[] = [
                    'route_id' => $route->id,
                    'route_variant_id' => $variant['id'],
                    'route_name' => $routeDirection,
                    'direction' => $variant['direction'],
                    'type' => 'reactive',
                    'severity' => 'High',
                    'title' => $title,
                    'message' => $message,
                ];
            }
        }

        return $alerts;
    }

    private function reactiveVariantsAtThreshold(object $route): Collection
    {
        return collect($route->variants ?? [])
            ->filter(fn (array $variant) => (int) ($variant['waiting_count'] ?? 0) >= (int) $route->threshold)
            ->values();
    }

    private function tomorrowDemandForecast(): array
    {
        return $this->tomorrowDemandForecast ??= app(DirectionAwareDemandForecastService::class)
            ->forecastForDate(Carbon::now('Asia/Manila')->addDay()->startOfDay());
    }

    private function forecastShadowDashboard(): array
    {
        return $this->forecastShadowDashboard ??= app(DemandForecastShadowService::class)
            ->dashboard();
    }

    /**
     * Parse alert template with dynamic data.
     */
    protected function parseAlertTemplate(string $key, array $data, string $default): string
    {
        $locale = app()->getLocale();
        $template = SystemSetting::get("{$key}_{$locale}", SystemSetting::get("{$key}_en", $default));

        $search = array_map(fn ($k) => "{{$k}}", array_keys($data));

        return str_replace($search, array_values($data), $template);
    }
}
