<?php

namespace App\Providers;

use App\Events\PositionUpdated;
use App\Listeners\ETAListener;
use App\Listeners\SpatialMonitoringListener;
use App\Listeners\TripProgressListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            \App\Services\Contracts\GeospatialServiceInterface::class,
            \App\Services\GeospatialService::class
        );
        $this->app->singleton(
            \App\Services\Contracts\KalmanFilterServiceInterface::class,
            \App\Services\GPS\KalmanFilterService::class
        );
        $this->app->singleton(
            \App\Services\GPS\Contracts\PositionFilterInterface::class,
            \App\Services\GPS\KalmanFilterService::class
        );
        $this->app->singleton(
            \App\Services\GPS\GPSSmoothingService::class
        );
        $this->app->singleton(
            \App\Services\Spatial\SpatialContextResolver::class
        );
        $this->app->singleton(
            \App\Services\Spatial\GeofenceEngine::class
        );
        $this->app->singleton(\App\Services\Spatial\Handlers\StopGeofenceHandler::class);
        $this->app->singleton(\App\Services\Spatial\Handlers\DepotGeofenceHandler::class);
        $this->app->singleton(\App\Services\Spatial\Handlers\TerminalGeofenceHandler::class);
        $this->app->singleton(\App\Services\Spatial\Handlers\GeofenceHandlerRegistry::class, function ($app) {
            $registry = new \App\Services\Spatial\Handlers\GeofenceHandlerRegistry();
            $registry->register(\App\Enums\GeofenceType::STOP, $app->make(\App\Services\Spatial\Handlers\StopGeofenceHandler::class));
            $registry->register(\App\Enums\GeofenceType::DEPOT, $app->make(\App\Services\Spatial\Handlers\DepotGeofenceHandler::class));
            $registry->register(\App\Enums\GeofenceType::TERMINAL, $app->make(\App\Services\Spatial\Handlers\TerminalGeofenceHandler::class));
            $registry->register(\App\Enums\GeofenceType::GARAGE, $app->make(\App\Services\Spatial\Handlers\DepotGeofenceHandler::class));
            return $registry;
        });
        $this->app->singleton(
            \App\Services\Spatial\SpatialMonitoringEngine::class
        );
        $this->app->singleton(
            \App\Repositories\Contracts\RouteGeometryRepositoryInterface::class,
            \App\Repositories\RouteGeometryRepository::class
        );
        $this->app->singleton(
            \App\Services\Contracts\RouteGeometryServiceInterface::class,
            \App\Services\RouteGeometryService::class
        );
        $this->app->singleton(
            \App\Services\Contracts\GeometryValidatorInterface::class,
            \App\Services\GeometryValidator::class
        );
        $this->app->singleton(
            \App\Services\Contracts\RouteGeometryEngineInterface::class,
            \App\Services\RouteGeometryEngine::class
        );
        $this->app->singleton(
            \App\Services\GeometrySimplifier::class
        );
        $this->app->singleton(
            \App\Services\GeometryVersioningService::class
        );
        $this->app->singleton(
            \App\Services\Providers\GoogleRoutingProvider::class
        );
        $this->app->singleton(
            \App\Services\Providers\OsrmRoutingProvider::class
        );
        $this->app->singleton(
            \App\Services\Providers\ManualRoutingProvider::class
        );
        $this->app->singleton(
            \App\Services\Routing\RouteComparisonService::class
        );
        $this->app->singleton(
            \App\Services\Routing\RouteGenerationSessionService::class
        );
        $this->app->singleton(
            \App\Services\Routing\ProviderHealthService::class
        );
        $this->app->singleton(
            \App\Services\Routing\ProviderQuotaService::class
        );
        $this->app->singleton(
            \App\Services\Contracts\ProviderCircuitBreakerInterface::class,
            \App\Services\Routing\ProviderCircuitBreaker::class
        );
        $this->app->singleton(
            \App\Services\Contracts\RouteQualityInterface::class,
            \App\Services\Routing\RouteQualityService::class
        );
        $this->app->singleton(
            \App\Services\Routing\IntelligentRoutingEngine::class
        );
        $this->app->singleton(
            \App\Services\Routing\GPSValidationService::class
        );
        $this->app->singleton(
            \App\Services\Routing\ETAEngine::class
        );
        $this->app->singleton(
            \App\Services\Routing\TripProgressService::class
        );
        $this->app->singleton(
            \App\Services\Routing\AuthoritativeRouteResolver::class
        );
        $this->app->singleton(
            \App\Services\Routing\FleetStatusService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(PositionUpdated::class, SpatialMonitoringListener::class);
        Event::listen(PositionUpdated::class, TripProgressListener::class);
        Event::listen(PositionUpdated::class, ETAListener::class);

        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            \Illuminate\Support\Facades\DB::statement('PRAGMA ignore_check_constraints = ON;');
        }

        // ------------------------------------------------------------
        // Reverse-proxy / ngrok support
        // When the app is accessed through ngrok or any other HTTPS
        // proxy, the forwarded headers must be trusted so that
        // request()->secure(), url(), asset(), and route() all produce
        // the correct public URL instead of the local http://localhost
        // one. Without this, Vite manifest paths and CSRF cookies will
        // mismatch the ngrok origin, causing broken assets and 419s.
        // ------------------------------------------------------------
        $request = app('request');

        if ($request->hasHeader('X-Forwarded-Host') ||
            $request->hasHeader('X-Forwarded-Proto') ||
            $request->hasHeader('ngrok-skip-browser-warning')) {

            \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }


        \Illuminate\Support\Facades\View::composer('fleet.monitor.index', function ($view) {
            $activeBusIds = \App\Models\Trip::where('status', 'ongoing')->pluck('bus_id')->toArray();
            $buses = \App\Models\Bus::with(['route', 'vehiclePosition'])
                ->where(function($q) use ($activeBusIds) {
                    $q->whereIn('id', $activeBusIds)
                      ->orWhere('status', 'breakdown');
                })
                ->get();
            $routes = $view->offsetExists('routes')
                ? $view->offsetGet('routes')
                : \App\Models\Route::getCanonicalProductionCached()->values()->toArray();
            $stops = \App\Models\Stop::getAllCached();
            
            // Find active incidents to map them to buses
            $activeIncidents = \App\Models\Incident::whereIn('status', ['reported', 'under_review'])
                ->with('trip.bus')
                ->get();
                
            // Map bus plate number to incident description / delay message
            $busIssues = [];
            foreach ($activeIncidents as $incident) {
                if ($incident->trip && $incident->trip->bus) {
                    $busIssues[$incident->trip->bus->plate_number] = $incident->type . ': ' . $incident->description;
                }
            }

            $view->with(compact('buses', 'routes', 'stops', 'busIssues'));
        });

        \Illuminate\Support\Facades\View::composer('fleet.utilization.index', function ($view) {
            $view->with(app(\App\Services\FleetUtilizationService::class)->snapshot());
            return;

            /*

                $lastActive = 'Ã¢â‚¬â€';
            */
        });

        \Illuminate\Support\Facades\View::composer('fleet.analytics.index', function ($view) {
            if (!$view->offsetExists('startDate')) {
                $startDate = \Carbon\Carbon::today()->subDays(30)->toDateString();
                $endDate = \Carbon\Carbon::today()->toDateString();
                $selectedRoute = 'all';
                $reportType = 'daily';

                $availableRoutes = \App\Models\Route::query()
                    ->publicCommuterActiveService()
                    ->get(['id', 'name'])
                    ->map(fn ($route) => ['id' => (int) $route->id, 'name' => $route->name])
                    ->values()
                    ->toArray();

                $analyticsController = app(\App\Http\Controllers\Fleet\AnalyticsController::class);
                $analyticsData = $analyticsController->fetchSummaryData($startDate, $endDate, $selectedRoute);

                $view->with(array_merge([
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'selectedRoute' => $selectedRoute,
                    'reportType' => $reportType,
                    'availableRoutes' => $availableRoutes,
                    'lastUpdatedTime' => now()->format('g:i A'),
                ], $analyticsData));
            }
        });

        \Illuminate\Support\Facades\View::composer('fleet.incidents.index', function ($view) {
            if (!$view->offsetExists('dateStart')) {
                $dateStart = now()->startOfMonth()->format('Y-m-d');
                $dateEnd = now()->endOfMonth()->format('Y-m-d');
                $routeFilter = 'all';
                $typeFilter = 'all';
                $statusFilter = 'all';
                $activeSort = 'newest';

                $routes = \App\Models\Route::publicCommuterActiveService()->get(['id', 'name']);
                $ongoingTrips = app(\App\Services\IncidentWorkflowService::class)
                    ->eligibleOngoingTripsQuery()
                    ->with(['bus', 'driver', 'route'])
                    ->get();

                $controller = app(\App\Http\Controllers\Fleet\IncidentController::class);
                $metrics = $controller->getIncidentMetrics();
                $activeIncidents = $controller->getFilteredIncidents('active', $dateStart, $dateEnd, $routeFilter, $typeFilter, $statusFilter, $activeSort);
                $resolvedIncidents = $controller->getFilteredIncidents('resolved', $dateStart, $dateEnd, $routeFilter, $typeFilter, $statusFilter, $activeSort);

                $view->with([
                    'dateStart' => $dateStart,
                    'dateEnd' => $dateEnd,
                    'routeFilter' => $routeFilter,
                    'typeFilter' => $typeFilter,
                    'statusFilter' => $statusFilter,
                    'activeSort' => $activeSort,
                    'routes' => $routes,
                    'ongoingTrips' => $ongoingTrips,
                    'incidentMetrics' => $metrics,
                    'activeIncidents' => $activeIncidents,
                    'resolvedIncidents' => $resolvedIncidents,
                ]);
            }
        });

        \Illuminate\Support\Facades\View::composer('fleet.dispatch-intelligence.index', function ($view) {
            if (!$view->offsetExists('selectedPhase')) {
                $selectedPhase = (int) \App\Models\SystemSetting::get('dispatch_default_phase', 1);
                $simulatedDay = \App\Models\SystemSetting::get('default_simulated_day', \Carbon\Carbon::now()->englishDayOfWeek);
                $activePublicRouteIds = \App\Models\Route::publicCommuterActiveService()->pluck('id')->map(fn ($id) => (int) $id)->all();
                $selectedRouteId = (int) \App\Models\SystemSetting::get('default_route_id', $activePublicRouteIds[0] ?? 0);

                $timeSlotConfig = \App\Models\TimeSlotConfiguration::getTimeSlotByHour();
                $simulatedTimeSlot = $timeSlotConfig
                    ? $timeSlotConfig->time_slot_display
                    : \App\Models\SystemSetting::get('default_time_slot', '18:00-20:00');

                $controller = app(\App\Http\Controllers\Fleet\DispatchIntelligenceController::class);
                $routesData = $controller->fetchRoutesData($simulatedDay, $simulatedTimeSlot, $selectedPhase);
                
                $threshold = \App\Models\DemandThreshold::where('route_id', $selectedRouteId)
                    ->where('day_of_week', $simulatedDay)
                    ->where('time_slot', $simulatedTimeSlot)
                    ->first();
                $customThreshold = $threshold ? $threshold->threshold_count : \App\Models\SystemSetting::get('default_demand_threshold', 20);

                $historicalPatterns = \App\Models\DemandHistory::forecastEligible()
                    ->with(['route', 'routeVariant'])
                    ->whereIn('route_id', $activePublicRouteIds)
                    ->orderBy('total_commuters', 'desc')
                    ->take(8)
                    ->get();

                $recentDispatches = \App\Models\DispatchLog::with(['trip.route', 'trip.bus', 'trip.driver', 'dispatcher'])
                    ->latest()
                    ->take(6)
                    ->get();

                $forecastShadow = app(\App\Services\DemandForecastShadowService::class)->dashboard();

                $view->with([
                    'selectedPhase' => $selectedPhase,
                    'simulatedDay' => $simulatedDay,
                    'simulatedTimeSlot' => $simulatedTimeSlot,
                    'selectedRouteId' => $selectedRouteId,
                    'customThreshold' => $customThreshold,
                    'routesData' => $routesData,
                    'forecastShadow' => $forecastShadow,
                    'historicalPatterns' => $historicalPatterns,
                    'recentDispatches' => $recentDispatches,
                ]);
            }
        });

        \Illuminate\Support\Facades\View::composer('fleet.performance.drivers.index', function ($view) {
            if (!$view->offsetExists('startDate')) {
                $startDate = \Carbon\Carbon::today()->subDays(30)->toDateString();
                $endDate   = \Carbon\Carbon::today()->toDateString();
                $selectedRoute = 'all';
                $selectedStatus = 'all';
                $search = '';

                $availableRoutes = \App\Models\Route::orderBy('id')->get(['id', 'name'])->toArray();

                $controller = app(\App\Http\Controllers\Fleet\DriverPerformanceController::class);
                $drivers = $controller->getFilteredDriversList($startDate, $endDate, $selectedRoute, $selectedStatus, $search);
                $metrics = $controller->getMetrics($drivers);
                $topDrivers = $controller->getTopDrivers($drivers);

                $view->with([
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'selectedRoute' => $selectedRoute,
                    'selectedStatus' => $selectedStatus,
                    'search' => $search,
                    'availableRoutes' => $availableRoutes,
                    'driverMetrics' => $metrics,
                    'topDrivers' => $topDrivers,
                    'driverLogs' => $drivers,
                    'driverPerformance' => $drivers,
                ]);
            }
        });

        \Illuminate\Support\Facades\View::composer('fleet.performance.routes.index', function ($view) {
            if (!$view->offsetExists('startDate')) {
                $startDate = \Carbon\Carbon::today('Asia/Manila')->subDays(30)->toDateString();
                $endDate   = \Carbon\Carbon::today('Asia/Manila')->toDateString();
                $selectedRoute = 'all';
                $page = 1;

                $controller = app(\App\Http\Controllers\Fleet\RoutePerformanceController::class);
                $data = $controller->getRoutePerformanceData($startDate, $endDate, $selectedRoute);
                $availableRoutes = $data['available_routes'];
                $selectedRoute = $data['selected_route'];

                $stopsCollection = collect($data['stops']);
                $perPage = 10;
                $paginatedStops = new \Illuminate\Pagination\LengthAwarePaginator(
                    $stopsCollection->slice(($page - 1) * $perPage, $perPage)->values(),
                    $stopsCollection->count(),
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'query' => request()->query()]
                );

                $view->with([
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'selectedRoute' => $selectedRoute,
                    'availableRoutes' => $availableRoutes,
                    'routePerformanceSummary' => $data['summary'],
                    'headwayData' => $data['headway'],
                    'tripDurationData' => $data['trip_durations'],
                    'stopActivityData' => $data['stops'],
                    'stopAdherence' => $paginatedStops,
                    'incidentLog' => $data['incidents'],
                    'routeHealthScore' => $data['health'],
                ]);
            }
        });

        \Illuminate\Support\Facades\View::composer([
            'fleet.maintenance.index',
            'fleet.maintenance.fragment',
        ], function ($view) {
            if (!$view->offsetExists('maintenanceSummary')) {
                $logTypeFilter = 'all';
                $logStatusFilter = 'all';
                $search = '';

                $controller = app(\App\Http\Controllers\Fleet\MaintenanceManagementController::class);
                $maintenanceSummary = $controller->getMaintenanceSummary();
                $busHealth = $controller->getBusHealth();
                $upcomingSchedule = $controller->getUpcomingSchedule();
                $maintenanceLogs = $controller->getFilteredLogsQuery($logTypeFilter, $logStatusFilter)->paginate(15);

                $view->with(compact(
                    'maintenanceSummary',
                    'busHealth',
                    'upcomingSchedule',
                    'maintenanceLogs',
                    'logTypeFilter',
                    'logStatusFilter',
                    'search'
                ));
            }
        });
    }

}
