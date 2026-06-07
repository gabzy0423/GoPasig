<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('fleet.monitor.index', function ($view) {
            $buses = \App\Models\Bus::with('route')->get();
            $routes = \App\Models\Route::all();
            $stops = \App\Models\Stop::all();
            
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
            $buses = \App\Models\Bus::with('route')->get();
            $routes = \App\Models\Route::all();
            
            // Generate last 30 days chart data dynamically
            $chartData = [];
            for ($i = 29; $i >= 0; $i--) {
                $date = \Carbon\Carbon::today('Asia/Manila')->subDays($i);
                $seed = ($date->day * 3) + ($date->month * 7);
                $active = 75 + ($seed % 10);
                $maint = 8 + ($seed % 6);
                $idle = 100 - $active - $maint;
                
                $chartData[] = [
                    'date' => $date->format('j M'),
                    'active' => $active,
                    'maintenance' => $maint,
                    'idle' => $idle,
                ];
            }

            // Calculate today's per-bus efficiency cards dynamically
            $busCards = [];
            foreach ($buses as $bus) {
                // Count trips today
                $tripsCount = \App\Models\Schedule::where('bus_id', $bus->id)->count();
                
                // Sum passengers today
                $paxSum = \App\Models\Schedule::where('bus_id', $bus->id)->sum('passengers');
                
                // Estimated kilometers
                $kmSum = $tripsCount * 18;
                
                // Calculate utilization percentage
                $util = 0;
                if ($tripsCount > 0 && $bus->capacity > 0) {
                    $util = min(100, (int) round(($paxSum / ($tripsCount * $bus->capacity)) * 100));
                } elseif ($bus->status === 'active') {
                    $util = 75; // default for active buses
                }
                
                // Map status classes
                if ($bus->status === 'active') {
                    if ($bus->capacity > 0 && ($bus->passengers / $bus->capacity) >= 0.8) {
                        $statusText = 'Near Full';
                        $statusClass = 'bg-[#FAEEDA] text-[#854F0B]';
                    } else {
                        $statusText = 'Active';
                        $statusClass = 'bg-[#E6F1FB] text-[#0C447C]';
                    }
                } elseif ($bus->status === 'maintenance') {
                    $statusText = 'Maintenance';
                    $statusClass = 'bg-[#FCEBEB] text-[#A32D2D]';
                    $util = 0;
                } else {
                    $statusText = 'Idle';
                    $statusClass = 'bg-[#F1EFE8] text-[#5F5E5A]';
                    $util = 0;
                }

                // Get last active time from last schedule
                $lastActive = '—';
                $lastSchedule = \App\Models\Schedule::where('bus_id', $bus->id)->orderBy('arrival_time', 'desc')->first();
                if ($lastSchedule) {
                    $lastActive = \Carbon\Carbon::createFromFormat('H:i:s', $lastSchedule->arrival_time)->format('g:i A');
                }

                $busCards[] = [
                    'plate' => $bus->plate_number,
                    'status' => $statusText,
                    'status_class' => $statusClass,
                    'trips' => $tripsCount,
                    'pax' => $paxSum,
                    'km' => $kmSum,
                    'util' => $util,
                    'route' => $bus->route_id ?? 'None',
                    'routeLabel' => $bus->route ? $bus->route->name : 'Standby',
                    'last' => $lastActive,
                ];
            }

            // Sort by utilization descending to highlight top efficiency
            usort($busCards, function ($a, $b) {
                return $b['util'] <=> $a['util'];
            });

            $view->with(compact('busCards', 'chartData', 'routes'));
        });

        \Illuminate\Support\Facades\View::composer('fleet.analytics.index', function ($view) {
            if (!$view->offsetExists('startDate')) {
                $startDate = \Carbon\Carbon::today()->subDays(30)->toDateString();
                $endDate = \Carbon\Carbon::today()->toDateString();
                $selectedRoute = 'all';
                $reportType = 'daily';

                $availableRoutes = \App\Models\Route::orderBy('id')->get(['id', 'name'])->toArray();

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

                $routes = \App\Models\Route::orderBy('id')->get(['id', 'name']);
                $ongoingTrips = \App\Models\Trip::where('status', 'ongoing')->with(['bus', 'driver', 'route'])->get();

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

        \Illuminate\Support\Facades\View::composer('fleet.announcements.index', function ($view) {
            if (!$view->offsetExists('search')) {
                $search = '';
                $filterPriority = 'all';
                $filterAudience = 'all';
                $filterStatus = 'all';
                $sortOrder = 'newest';

                $routes = \App\Models\Route::all();

                $controller = app(\App\Http\Controllers\Fleet\AnnouncementController::class);
                $announcementStats = $controller->getAnnouncementStats();
                $announcements = $controller->getFilteredAnnouncements($search, $filterPriority, $filterAudience, $filterStatus, $sortOrder);

                $view->with([
                    'search' => $search,
                    'filterPriority' => $filterPriority,
                    'filterAudience' => $filterAudience,
                    'filterStatus' => $filterStatus,
                    'sortOrder' => $sortOrder,
                    'routes' => $routes,
                    'announcementStats' => $announcementStats,
                    'announcements' => $announcements,
                ]);
            }
        });

        \Illuminate\Support\Facades\View::composer('fleet.dispatch-intelligence.index', function ($view) {
            if (!$view->offsetExists('selectedPhase')) {
                $selectedPhase = 1;
                $simulatedDay = \Carbon\Carbon::now()->englishDayOfWeek;

                $hour = (int) \Carbon\Carbon::now()->format('G');
                if ($hour >= 6 && $hour < 8) {
                    $simulatedTimeSlot = '06:00-08:00';
                } elseif ($hour >= 8 && $hour < 12) {
                    $simulatedTimeSlot = '08:00-10:00';
                } elseif ($hour >= 12 && $hour < 16) {
                    $simulatedTimeSlot = '12:00-14:00';
                } elseif ($hour >= 16 && $hour < 18) {
                    $simulatedTimeSlot = '16:00-18:00';
                } else {
                    $simulatedTimeSlot = '18:00-20:00';
                }
                
                $selectedRouteId = 1;

                $controller = app(\App\Http\Controllers\Fleet\DispatchIntelligenceController::class);
                $routesData = $controller->fetchRoutesData($simulatedDay, $simulatedTimeSlot, $selectedPhase);
                
                $threshold = \App\Models\DemandThreshold::where('route_id', $selectedRouteId)
                    ->where('day_of_week', $simulatedDay)
                    ->where('time_slot', $simulatedTimeSlot)
                    ->first();
                $customThreshold = $threshold ? $threshold->threshold_count : 20;

                $historicalPatterns = \App\Models\DemandHistory::with('route')
                    ->orderBy('total_commuters', 'desc')
                    ->take(8)
                    ->get();

                $recentDispatches = \App\Models\DispatchLog::with(['trip.route', 'trip.bus', 'trip.driver', 'dispatcher'])
                    ->latest()
                    ->take(6)
                    ->get();

                $view->with([
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
                $startDate = \Carbon\Carbon::today()->subDays(30)->toDateString();
                $endDate   = \Carbon\Carbon::today()->toDateString();
                $selectedRoute = 'all';
                $page = 1;

                $availableRoutes = \App\Models\Route::orderBy('id')->get(['id', 'name'])->toArray();

                $controller = app(\App\Http\Controllers\Fleet\RoutePerformanceController::class);
                $data = $controller->getRoutePerformanceData($startDate, $endDate, $selectedRoute);

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
                    'scheduleCompliance' => $data['schedule'],
                    'stopAdherence' => $paginatedStops,
                    'deviationLog' => $data['deviations'],
                    'routeHealthScore' => $data['health'],
                ]);
            }
        });

        \Illuminate\Support\Facades\View::composer('fleet.schedule.index', function ($view) {
            if (!$view->offsetExists('dateFrom')) {
                $dateFrom = \Carbon\Carbon::today()->subDays(30)->toDateString();
                $dateTo   = \Carbon\Carbon::today()->toDateString();
                $selectedRoute = 'all';
                $selectedDriver = 'all';
                $selectedStatus = 'all';
                $page = 1;

                $availableRoutes  = \App\Models\Route::orderBy('id')->get(['id', 'name'])->toArray();
                $availableDrivers = \App\Models\Driver::orderBy('last_name')
                    ->get(['id', 'first_name', 'last_name'])
                    ->map(fn($d) => ['id' => $d->id, 'name' => "{$d->first_name} {$d->last_name}"])
                    ->toArray();

                $controller = app(\App\Http\Controllers\Fleet\ScheduleComplianceController::class);
                $data = $controller->getComplianceData($dateFrom, $dateTo, $selectedRoute, $selectedDriver, $selectedStatus);

                $tripsCollection = collect($data['tripLogs']);
                $perPage = 10;
                $paginatedTrips = new \Illuminate\Pagination\LengthAwarePaginator(
                    $tripsCollection->slice(($page - 1) * $perPage, $perPage)->values(),
                    $tripsCollection->count(),
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'query' => request()->query()]
                );

                $view->with([
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo,
                    'selectedRoute' => $selectedRoute,
                    'selectedDriver' => $selectedDriver,
                    'selectedStatus' => $selectedStatus,
                    'availableRoutes' => $availableRoutes,
                    'availableDrivers' => $availableDrivers,
                    'complianceSummary' => $data['complianceSummary'],
                    'routeCompliance' => $data['routeCompliance'],
                    'delayTrend' => $data['delayTrend'],
                    'tripLogs' => $paginatedTrips,
                    'rawTripLogsCount' => $tripsCollection->count(),
                    'delayedRoutes' => $data['delayedRoutes'],
                    'lateDrivers' => $data['lateDrivers'],
                ]);
            }
        });

        \Illuminate\Support\Facades\View::composer('fleet.maintenance.index', function ($view) {
            if (!$view->offsetExists('maintenanceSummary')) {
                $logTypeFilter = 'all';
                $logStatusFilter = 'all';

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
                    'logStatusFilter'
                ));
            }
        });
    }
}
