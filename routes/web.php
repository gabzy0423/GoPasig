<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\CommuterController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Admin\BusController as AdminBusController;
use App\Http\Controllers\Admin\DriverController as AdminDriverController;
use App\Http\Controllers\Admin\ScheduleController as AdminScheduleController;
use App\Http\Controllers\Admin\RouteController as AdminRouteController;
use App\Http\Controllers\Admin\StopController as AdminStopController;
use App\Http\Controllers\Admin\MaintenanceController as AdminMaintenanceController;
use App\Http\Controllers\Admin\ServiceAlertController as AdminServiceAlertController;
use App\Http\Controllers\Fleet\FleetController;
use App\Http\Controllers\Fleet\DriverPerformanceController;
use App\Http\Controllers\Fleet\RoutePerformanceController;
use App\Http\Controllers\Fleet\ScheduleComplianceController;
use App\Http\Controllers\Fleet\IncidentController;
use App\Http\Controllers\Fleet\MaintenanceManagementController;
use App\Http\Controllers\Fleet\AnnouncementController;
use App\Http\Controllers\Fleet\AnalyticsController as FleetAnalyticsController;
use App\Http\Controllers\Fleet\DispatchIntelligenceController;
use App\Http\Controllers\Driver\DriverController;


Route::redirect('/', '/login');

Route::get('/autologin-dispatcher', [LoginController::class, 'autoLoginDispatcher']);

Route::get('/login', [LoginController::class, 'index'])->name('login');
Route::post('/login', [LoginController::class, 'authenticate']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/logout', [LoginController::class, 'logout']);

Route::prefix('commuter')->middleware('commuter_session')->name('commuter.')->group(function () {
    Route::redirect('/', '/commuter/dashboard')->name('index');

    Route::get('/dashboard', [CommuterController::class, 'dashboard'])->name('dashboard');
    Route::get('/tracker', [CommuterController::class, 'tracker'])->name('tracker');
    Route::get('/alerts', [CommuterController::class, 'alerts'])->name('alerts');
    Route::get('/routes', [CommuterController::class, 'routes'])->name('routes');
    Route::get('/schedule', [CommuterController::class, 'schedule'])->name('schedule');
    Route::get('/stops', [CommuterController::class, 'stops'])->name('stops');
});

Route::get('/api/commuter/buses', [CommuterController::class, 'busesApi']);

// Admin Dashboard (Protected)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/buses/create', [AdminBusController::class, 'create'])->name('buses.create');
    Route::get('/buses/{bus}/edit', [AdminBusController::class, 'edit'])->name('buses.edit');
    Route::get('/drivers/create', [AdminDriverController::class, 'create'])->name('drivers.create');
    Route::get('/drivers/{driver}', [AdminDriverController::class, 'show'])->name('drivers.show');
    Route::get('/drivers/{driver}/edit', [AdminDriverController::class, 'edit'])->name('drivers.edit');
    Route::get('/schedules/create', [AdminScheduleController::class, 'create'])->name('schedules.create');
    Route::get('/schedules/conflict', [AdminScheduleController::class, 'conflict'])->name('schedules.conflict');
    Route::get('/maintenance/create', [AdminMaintenanceController::class, 'create'])->name('maintenance.create');
    Route::get('/alerts/history', [AdminServiceAlertController::class, 'history'])->name('alerts.history');
});

// Admin API Routes (Protected by Auth, role checks done inside controllers)
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/api/analytics', [AdminAnalyticsController::class, 'index'])->name('api.analytics');
    Route::get('/api/fleet-data', [AdminDashboardController::class, 'getFleetData'])->name('api.fleet-data');
    Route::get('/api/settings', [AdminDashboardController::class, 'getSettings'])->name('api.settings.index');
    Route::post('/api/settings', [AdminDashboardController::class, 'saveSetting'])->name('api.settings.store');

    Route::post('/api/buses', [AdminBusController::class, 'store'])->name('api.buses.store');
    Route::put('/api/buses/{bus}', [AdminBusController::class, 'update'])->name('api.buses.update');
    Route::delete('/api/buses/{bus}', [AdminBusController::class, 'destroy'])->name('api.buses.destroy');
    Route::put('/api/buses/{bus}/assign-route', [AdminBusController::class, 'assignRoute'])->name('api.buses.assign-route');

    Route::get('/api/drivers', [AdminDriverController::class, 'index'])->name('api.drivers.index');
    Route::post('/api/drivers', [AdminDriverController::class, 'store'])->name('api.drivers.store');
    Route::put('/api/drivers/{driver}', [AdminDriverController::class, 'update'])->name('api.drivers.update');
    Route::delete('/api/drivers/{driver}', [AdminDriverController::class, 'destroy'])->name('api.drivers.destroy');
    Route::post('/api/drivers/{driver}/suspend', [AdminDriverController::class, 'toggleSuspend'])->name('api.drivers.suspend');

    Route::get('/api/schedules', [AdminScheduleController::class, 'index'])->name('api.schedules.index');
    Route::post('/api/schedules', [AdminScheduleController::class, 'store'])->name('api.schedules.store');
    Route::put('/api/schedules/{schedule}', [AdminScheduleController::class, 'update'])->name('api.schedules.update');
    Route::patch('/api/schedules/{schedule}/status', [AdminScheduleController::class, 'updateStatus'])->name('api.schedules.status');
    Route::delete('/api/schedules/{schedule}', [AdminScheduleController::class, 'destroy'])->name('api.schedules.destroy');

    Route::post('/api/routes', [AdminRouteController::class, 'store'])->name('api.routes.store');
    Route::put('/api/routes/{route}', [AdminRouteController::class, 'update'])->name('api.routes.update');
    Route::delete('/api/routes/{route}', [AdminRouteController::class, 'destroy'])->name('api.routes.destroy');

    Route::post('/api/stops', [AdminStopController::class, 'store'])->name('api.stops.store');
    Route::delete('/api/stops/{stop}', [AdminStopController::class, 'destroy'])->name('api.stops.destroy');
    Route::put('/api/routes/{route}/stops/reorder', [AdminStopController::class, 'reorder'])->name('api.routes.stops.reorder');

    Route::get('/api/maintenance', [AdminMaintenanceController::class, 'index'])->name('api.maintenance.index');
    Route::get('/api/maintenance/{id}', [AdminMaintenanceController::class, 'show'])->name('api.maintenance.show');
    Route::post('/api/maintenance', [AdminMaintenanceController::class, 'store'])->name('api.maintenance.store');
    Route::post('/api/maintenance/{id}/perform-inspection', [AdminMaintenanceController::class, 'performInspection'])->name('api.maintenance.perform-inspection');
    Route::post('/api/maintenance/{id}/complete', [AdminMaintenanceController::class, 'complete'])->name('api.maintenance.complete');
    Route::delete('/api/maintenance/{id}', [AdminMaintenanceController::class, 'destroy'])->name('api.maintenance.destroy');

    Route::get('/api/alerts', [AdminServiceAlertController::class, 'index'])->name('api.alerts.index');
    Route::post('/api/alerts', [AdminServiceAlertController::class, 'store'])->name('api.alerts.store');
    Route::put('/api/alerts/{id}', [AdminServiceAlertController::class, 'update'])->name('api.alerts.update');
    Route::delete('/api/alerts/{id}', [AdminServiceAlertController::class, 'destroy'])->name('api.alerts.destroy');
    Route::post('/api/alerts/{id}/resolve', [AdminServiceAlertController::class, 'resolve'])->name('api.alerts.resolve');
    Route::post('/api/alerts/resolve-all', [AdminServiceAlertController::class, 'resolveAll'])->name('api.alerts.resolve-all');
});

// Fleet / Dispatcher Dashboard (Protected)
Route::middleware(['auth', 'role:dispatcher'])->prefix('fleet')->name('fleet.')->group(function () {
    Route::get('/dashboard', [FleetController::class, 'dashboard'])->name('dashboard');
    Route::get('/api/overview-data', [FleetController::class, 'getOverviewData'])->name('api.overview-data');
    Route::post('/api/incidents', [FleetController::class, 'submitIncident'])->name('api.incidents.store');
    Route::post('/api/incidents/{id}/resolve', [FleetController::class, 'resolveIncident'])->name('api.incidents.resolve');
    Route::post('/api/announcements', [FleetController::class, 'submitAnnouncement'])->name('api.announcements.store');

    Route::get('/monitor', fn() => redirect()->route('fleet.dashboard', ['tab' => 'monitor']))->name('monitor');
    Route::get('/utilization', fn() => redirect()->route('fleet.dashboard', ['tab' => 'utilization']))->name('utilization');

    Route::get('/drivers', fn() => redirect()->route('fleet.dashboard', ['tab' => 'drivers']))->name('drivers');
    Route::get('/api/drivers-data', [DriverPerformanceController::class, 'getDriversData'])->name('api.drivers-data');
    Route::get('/api/drivers-details/{id}', [DriverPerformanceController::class, 'getDriverDetails'])->name('api.drivers-details');
    Route::post('/api/drivers-message/{id}', [DriverPerformanceController::class, 'messageDriver'])->name('api.drivers-message');
    Route::get('/api/drivers-export', [DriverPerformanceController::class, 'exportCsv'])->name('api.drivers-export');

    Route::get('/routes', fn() => redirect()->route('fleet.dashboard', ['tab' => 'routes']))->name('routes');
    Route::get('/api/routes-data', [RoutePerformanceController::class, 'getRoutesData'])->name('api.routes-data');
    Route::get('/api/routes-export', [RoutePerformanceController::class, 'exportCsv'])->name('api.routes-export');

    Route::get('/schedule', fn() => redirect()->route('fleet.dashboard', ['tab' => 'schedule']))->name('schedule');
    Route::get('/api/schedule-compliance-data', [ScheduleComplianceController::class, 'getComplianceDataAjax'])->name('api.schedule-compliance-data');
    Route::get('/api/schedule-compliance-export', [ScheduleComplianceController::class, 'exportCsv'])->name('api.schedule-compliance-export');

    Route::get('/incidents', fn() => redirect()->route('fleet.dashboard', ['tab' => 'incidents']))->name('incidents');
    Route::get('/api/incidents-data', [IncidentController::class, 'getIncidentsData'])->name('api.incidents-data');
    Route::get('/api/trips-details/{id}', [IncidentController::class, 'getTripDetails'])->name('api.trips-details');
    Route::post('/api/incidents-store', [IncidentController::class, 'store'])->name('api.incidents-store');
    Route::post('/api/incidents-update-status/{id}', [IncidentController::class, 'updateStatus'])->name('api.incidents-update-status');
    Route::delete('/api/incidents-delete/{id}', [IncidentController::class, 'destroy'])->name('api.incidents-delete');
    Route::get('/api/incidents-export', [IncidentController::class, 'exportCsv'])->name('api.incidents-export');

    Route::get('/maintenance', fn() => redirect()->route('fleet.dashboard', ['tab' => 'maintenance']))->name('maintenance');
    Route::get('/api/maintenance-data', [MaintenanceManagementController::class, 'getMaintenanceData'])->name('api.maintenance-data');
    Route::get('/api/maintenance-record/{id}', [MaintenanceManagementController::class, 'getRecordDetails'])->name('api.maintenance-record');
    Route::get('/api/maintenance-bus/{id}', [MaintenanceManagementController::class, 'getBusProfile'])->name('api.maintenance-bus');
    Route::post('/api/maintenance-store', [MaintenanceManagementController::class, 'storeOrUpdate'])->name('api.maintenance-store');
    Route::post('/api/maintenance-update-status/{id}', [MaintenanceManagementController::class, 'updateStatus'])->name('api.maintenance-update-status');
    Route::delete('/api/maintenance-delete/{id}', [MaintenanceManagementController::class, 'destroy'])->name('api.maintenance-delete');
    Route::get('/api/maintenance-export', [MaintenanceManagementController::class, 'exportCsv'])->name('api.maintenance-export');

    Route::get('/announcements', fn() => redirect()->route('fleet.dashboard', ['tab' => 'announcements']))->name('announcements');
    Route::get('/api/announcements-data', [AnnouncementController::class, 'getAnnouncementsData'])->name('api.announcements-data');
    Route::get('/api/announcements-details/{id}', [AnnouncementController::class, 'getDetails'])->name('api.announcements-details');
    Route::post('/api/announcements-store', [AnnouncementController::class, 'storeOrUpdate'])->name('api.announcements-store');
    Route::delete('/api/announcements-delete/{id}', [AnnouncementController::class, 'destroy'])->name('api.announcements-delete');

    Route::get('/analytics', fn() => redirect()->route('fleet.dashboard', ['tab' => 'analytics']))->name('analytics');
    Route::get('/api/analytics-data', [FleetAnalyticsController::class, 'getAnalyticsData'])->name('api.analytics-data');
    Route::get('/api/analytics-export', [FleetAnalyticsController::class, 'exportCsv'])->name('api.analytics-export');

    Route::get('/dispatch-intelligence', fn() => redirect()->route('fleet.dashboard', ['tab' => 'dispatch-intelligence']))->name('dispatch_intelligence');
    Route::get('/api/dispatch-data', [DispatchIntelligenceController::class, 'getDispatchData'])->name('api.dispatch-data');
    Route::post('/api/dispatch-save-threshold', [DispatchIntelligenceController::class, 'saveThreshold'])->name('api.dispatch-save-threshold');
    Route::post('/api/dispatch-add-commuter', [DispatchIntelligenceController::class, 'addCommuter'])->name('api.dispatch-add-commuter');
    Route::post('/api/dispatch-add-manual', [DispatchIntelligenceController::class, 'addManualTicker'])->name('api.dispatch-add-manual');
    Route::post('/api/dispatch-simulate-spurt', [DispatchIntelligenceController::class, 'simulateRushSpurt'])->name('api.dispatch-simulate-spurt');
    Route::post('/api/dispatch-clear-simulator', [DispatchIntelligenceController::class, 'clearSimulatorData'])->name('api.dispatch-clear-simulator');
    Route::post('/api/dispatch-now', [DispatchIntelligenceController::class, 'dispatchNow'])->name('api.dispatch-now');

    // Commuter session and trip log tracking
    Route::get('/api/commuter-trips', [FleetController::class, 'getCommuterTrips'])->name('api.commuter-trips');
    Route::get('/api/commuter-sessions', [FleetController::class, 'getCommuterSessions'])->name('api.commuter-sessions');
});

// Driver Dashboard (Protected)
Route::middleware(['auth', 'role:driver'])->prefix('driver')->name('driver.')->group(function () {
    Route::get('/', [DriverController::class, 'index'])->name('index');
    Route::get('/dashboard', [DriverController::class, 'dashboard'])->name('dashboard');
    Route::get('/trip', [DriverController::class, 'trip'])->name('trip');
    Route::get('/schedule', [DriverController::class, 'schedule'])->name('schedule');
    Route::get('/announcements', [DriverController::class, 'announcements'])->name('announcements');

    // Dynamic endpoints for driver real-time actions
    Route::post('/trip/toggle', [DriverController::class, 'toggleTrip'])->name('trip.toggle');
    Route::post('/trip/incident', [DriverController::class, 'reportIncident'])->name('trip.incident');
    Route::post('/trip/pax', [DriverController::class, 'updatePassengers'])->name('trip.pax');
    Route::post('/trip/stop', [DriverController::class, 'updateStop'])->name('trip.stop');
    Route::post('/trip/gps', [DriverController::class, 'updateGPS'])->middleware('throttle:15,1')->name('trip.gps');
});
