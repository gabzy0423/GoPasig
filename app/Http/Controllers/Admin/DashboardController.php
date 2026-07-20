<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\Bus;
use App\Models\Trip;
use App\Models\ServiceAlert;
use App\Models\SystemSetting;
use App\Models\Stop;
use App\Models\DispatchSimulationDefault;
use App\Models\TripProgress;
use App\Models\VehiclePosition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


class DashboardController extends Controller
{
    public function index()
    {
        $missingThresholdKey = false;
        if (Schema::hasTable('dispatch_simulation_defaults')) {
            $missingThresholdKey = !DispatchSimulationDefault::where('key', 'default_demand_threshold')->exists();
        }
        $routes = Route::getAllCached();
        $primaryRouteName = $routes->first()?->name
            ?? SystemSetting::get('overview_default_route_name')
            ?? SystemSetting::get('default_route_name', 'Route 1');
        $busCapacityLimit = (int) SystemSetting::get('bus_capacity_default', (int) SystemSetting::get('default_bus_capacity', 45));
        $licenseWarningDays = (int) SystemSetting::get('license_expiry_warning_threshold_days', 30);
        $mapCenterLat = (float) SystemSetting::get('map_default_latitude', 14.5690);
        $mapCenterLng = (float) SystemSetting::get('map_default_longitude', 121.0680);
        $mapZoom = (int) SystemSetting::get('map_default_zoom', 13);
        $pollingInterval = (int) SystemSetting::get('map_gps_polling_interval_ms', 5000);
        $scheduleBuffer = (int) SystemSetting::get('driver_schedule_buffer_minutes', 15);
        $busScheduleBuffer = (int) SystemSetting::get('bus_schedule_buffer_minutes', 15);
        $defaultTravelTime = (int) SystemSetting::get('schedule_default_travel_time_minutes', 30);
        $defaultDepartureTime = (string) SystemSetting::get('schedule_default_departure_time', '08:00');
        $defaultActiveDays = array_filter(array_map('trim', explode(',', (string) SystemSetting::get('schedule_default_active_days', 'M,T,W,Th,F'))));
        $defaultStopBoarding = (int) SystemSetting::get('route_stop_default_avg_boarding', 15);
        $defaultStopAlighting = (int) SystemSetting::get('route_stop_default_avg_alighting', 10);
        $defaultStopDwellSeconds = (int) SystemSetting::get('route_stop_default_dwell_seconds', 45);
        $maintenanceTypes = array_filter(array_map('trim', explode(',', (string) SystemSetting::get('maintenance_type_options', 'Preventive Maintenance,Corrective Maintenance'))));

        // System health status: check live operational risk signals.
        $activeAlerts = ServiceAlert::activeAlerts()->count();
        $openIncidents = DB::table('incidents')
            ->whereIn('status', ['reported', 'under_review'])
            ->count();
        $breakdownBuses = Bus::where('status', 'breakdown')->count();
        $maintenanceBuses = Bus::where('status', 'maintenance')->count();
        if ($activeAlerts > 0 || $openIncidents > 0 || $breakdownBuses > 0) {
            $systemStatus = 'critical';
        } elseif ($maintenanceBuses > 0) {
            $systemStatus = 'degraded';
        } else {
            $systemStatus = 'nominal';
        }

        $maintenanceStats = app(\App\Services\MaintenanceStatisticsService::class)->getSummary();
        $totalRecords = $maintenanceStats['totalRecords'];
        $scheduledCount = $maintenanceStats['scheduledCount'];
        $inProgressCount = $maintenanceStats['inProgressCount'];
        $completedCount = $maintenanceStats['completedCount'];
        $cancelledCount = $maintenanceStats['cancelledCount'];
        $observationCount = $maintenanceStats['observationCount'];
        $overdueCount = $maintenanceStats['overdueCount'];
        $requiringRepairCount = $maintenanceStats['requiringRepairCount'];
        $averageDuration = $maintenanceStats['averageDuration'];

        return view('admin.dashboard', compact(
            'missingThresholdKey', 'routes', 'primaryRouteName', 'busCapacityLimit',
            'licenseWarningDays', 'mapCenterLat', 'mapCenterLng', 'mapZoom',
            'pollingInterval', 'systemStatus', 'scheduleBuffer', 'busScheduleBuffer',
            'defaultTravelTime', 'defaultDepartureTime', 'defaultActiveDays',
            'defaultStopBoarding', 'defaultStopAlighting', 'defaultStopDwellSeconds',
            'maintenanceTypes', 'totalRecords', 'scheduledCount', 'inProgressCount',
            'completedCount', 'cancelledCount', 'observationCount', 'overdueCount',
            'requiringRepairCount', 'averageDuration'
        ));
    }

    public function getFleetData()
    {
        $stopsByRoute = Stop::getAllCached()->groupBy('route_id');
        $avgPaxByRoute = Trip::where('status', 'completed')
            ->select('route_id', DB::raw('AVG(peak_passengers) as avg_peak'))
            ->groupBy('route_id')
            ->pluck('avg_peak', 'route_id');

        $routes = Route::getAllCached()
            ->whereNotIn('status', ['suspended', 'inactive', 'Suspended', 'Inactive'])
            ->map(function ($route) use ($stopsByRoute, $avgPaxByRoute) {
                $route->setRelation('stops', $stopsByRoute->get($route->id, collect()));

                $avgPax = $avgPaxByRoute->get($route->id);

                if ($avgPax === null) {
                    $avgPax = (int) (SystemSetting::get('default_route_avg_pax') ?? 0);
                }

                $route->avg_passengers = (int) round($avgPax);
                return $route;
            });

        $activeTrips = Trip::where('status', 'ongoing')
            ->with('driver')
            ->get()
            ->keyBy('bus_id');
        $activeBusIds = $activeTrips->keys()->all();
        $positions = VehiclePosition::all()->keyBy('bus_id');
        $progresses = TripProgress::with(['currentStop', 'nextStop', 'trip'])->get()->keyBy(fn($p) => $p->trip->bus_id ?? 0);

        $buses = Bus::all()->map(function ($bus) use ($activeBusIds, $activeTrips, $positions, $progresses) {
            $bus->has_active_trip = in_array($bus->id, $activeBusIds);
            $activeTrip = $activeTrips->get($bus->id);

            $originalBusStatus = $bus->status;
            $pos = $positions->get($bus->id);
            $positionMatchesTrip = $pos && (!$activeTrip || (int) $pos->trip_id === (int) $activeTrip->id);
            $hasLiveTelemetry = (bool) $positionMatchesTrip;
            $coordinateSource = $hasLiveTelemetry ? 'vehicle_position' : 'bus_fallback';

            $speedMps = $hasLiveTelemetry ? (float) $pos->speed : (float) ($bus->speed ?? 0);
            $speedKmh = round($speedMps * 3.6, 1);
            $heading = $hasLiveTelemetry ? $pos->heading : null;
            $displayHeading = $hasLiveTelemetry ? $pos->display_heading : null;
            $headingSource = $hasLiveTelemetry ? ($pos->heading_source ?: 'unavailable') : null;
            $headingUpdatedAt = $hasLiveTelemetry && $pos->heading_updated_at ? $pos->heading_updated_at->toIso8601String() : null;
            $movementState = $hasLiveTelemetry ? ($pos->movement_state ?: 'UNKNOWN') : null;
            $movementConfidence = $hasLiveTelemetry ? $pos->movement_confidence : null;
            $movementReason = $hasLiveTelemetry ? $pos->movement_reason : null;
            $movementStateUpdatedAt = $hasLiveTelemetry && $pos->movement_state_updated_at ? $pos->movement_state_updated_at->toIso8601String() : null;
            $fleetStatusService = app(\App\Services\Routing\FleetStatusService::class);
            $operationalStatus = $hasLiveTelemetry ? $fleetStatusService->determineStatus($pos) : null;
            $stationaryDurationSeconds = $hasLiveTelemetry ? $fleetStatusService->stationaryDurationSeconds($pos) : null;
            $gpsQualityState = $hasLiveTelemetry ? ($pos->gps_quality_state ?: 'UNKNOWN') : null;
            $gpsQualityReason = $hasLiveTelemetry ? $pos->gps_quality_reason : null;
            $gpsFixAgeSeconds = $hasLiveTelemetry ? $pos->gps_fix_age_seconds : null;
            $lastGpsFixAt = $hasLiveTelemetry && $pos->last_gps_fix_at ? $pos->last_gps_fix_at->toIso8601String() : null;

            if ($hasLiveTelemetry) {
                $bus->lat = $pos->lat;
                $bus->lng = $pos->lng;
                $bus->status = $operationalStatus; // Moving, Stopped, Idle, Offline
            } else {
                $bus->lat = $bus->lat;
                $bus->lng = $bus->lng;
            }

            $bus->speed = $speedMps;
            $bus->speed_mps = $speedMps;
            $bus->speed_kmh = $speedKmh;
            $bus->speed_unit = 'm/s';
            $bus->heading = $heading !== null ? (float) $heading : null;
            $bus->display_heading = $displayHeading !== null ? (float) $displayHeading : null;
            $bus->heading_source = $headingSource;
            $bus->heading_updated_at = $headingUpdatedAt;

            $bus->driver_name = $activeTrip && $activeTrip->driver
                ? "{$activeTrip->driver->first_name} {$activeTrip->driver->last_name}"
                : 'Unassigned';
            $bus->trip_id = $activeTrip?->id;
            $bus->bus_status = $originalBusStatus;
            $bus->operational_status = $operationalStatus;
            $bus->movement_state = $movementState;
            $bus->movement_confidence = $movementConfidence !== null ? (float) $movementConfidence : null;
            $bus->movement_reason = $movementReason;
            $bus->movement_state_updated_at = $movementStateUpdatedAt;
            $bus->stationary_duration_seconds = $stationaryDurationSeconds;
            $bus->gps_quality_state = $gpsQualityState;
            $bus->gps_quality_reason = $gpsQualityReason;
            $bus->gps_fix_age_seconds = $gpsFixAgeSeconds;
            $bus->last_gps_fix_at = $lastGpsFixAt;
            $bus->coordinate_source = $coordinateSource;
            $bus->has_live_telemetry = $hasLiveTelemetry;
            $bus->last_gps_at = $hasLiveTelemetry && $pos->last_updated_at ? $pos->last_updated_at->toIso8601String() : null;
            $bus->corridor_distance = $hasLiveTelemetry ? (float) $pos->corridor_distance : null;
            $bus->route_adherence = null;
            $bus->trip_progress = null;
            $bus->current_stop = null;
            $bus->upcoming_stop = null;

            $healthyBusStatuses = ['operating', 'active', 'ready'];
            $stateMismatch = $activeTrip && !in_array((string) $originalBusStatus, $healthyBusStatuses, true);
            $bus->state_mismatch = (bool) $stateMismatch;
            $bus->state_mismatch_details = $stateMismatch ? [
                'trip_status' => $activeTrip->status,
                'gps_session' => $activeTrip->gps_session,
                'bus_status' => $originalBusStatus,
            ] : null;

            $prog = $progresses->get($bus->id);
            if ($prog) {
                $bus->current_stop = $prog->currentStop ? $prog->currentStop->name : null;
                $bus->upcoming_stop = $prog->nextStop ? $prog->nextStop->name : null;
                $bus->next_stop = $bus->upcoming_stop;
                $bus->route_adherence = $prog->route_adherence;
                $bus->trip_progress = [
                    'current_stop_id' => $prog->current_stop_id,
                    'next_stop_id' => $prog->next_stop_id,
                    'last_completed_stop_id' => $prog->last_completed_stop_id,
                    'completed_stops' => $prog->completed_stops_count,
                    'remaining_stops' => $prog->remaining_stops_count,
                    'completion_percentage' => $prog->trip_percentage,
                ];

                $etas = $prog->upcoming_etas ?? [];
                if (!empty($etas)) {
                    $etaTime = \Carbon\Carbon::parse($etas[0]['eta_timestamp'] ?? now());
                    $bus->eta = max(0, (int) round(now()->diffInMinutes($etaTime)));
                } else {
                    $bus->eta = null;
                }
            } else {
                $bus->next_stop = null;
                $bus->eta = null;
            }

            return $bus;
        });

        $trips = Trip::with(['bus', 'driver', 'route'])->latest()->take(5)->get();

        return response()->json(compact('routes', 'buses', 'trips'));
    }

    public function getSettings()
    {
        $systemSettings = SystemSetting::orderBy('key')->get();
        $simulationDefaults = [];
        if (Schema::hasTable('dispatch_simulation_defaults')) {
            $simulationDefaults = DispatchSimulationDefault::orderBy('key')->get();
        }
        return response()->json([
            'system_settings' => $systemSettings,
            'simulation_defaults' => $simulationDefaults,
        ]);
    }

    public function saveSetting(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:system,simulation',
            'key' => 'required|string',
            'value' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $type = $validated['type'];
        $key = $validated['key'];
        $value = $validated['value'];
        $description = $validated['description'] ?? 'Custom user-defined setting.';

        // Perform dynamic type/range validation
        $rule = 'nullable|string';
        if (in_array($key, [
            'default_bus_capacity', 'bus_capacity_default', 'bus_capacity_min', 'bus_capacity_max',
            'captcha_attempt_threshold', 'delay_threshold', 'occupancy_warning_threshold',
            'occupancy_critical_threshold', 'gps_sync_interval_ms', 'speed_simulation_interval_ms',
            'sim_speed_min', 'sim_speed_max', 'speed_fast_threshold', 'stop_auto_advance_distance',
            'stop_grouping_radius', 'driver_score_incident_penalty', 'incident_score_penalty_per_event',
            'driver_score_delay_penalty', 'driver_performance_rolling_days', 'driver_passenger_rating_default',
            'license_expiry_warning_threshold_days', 'license_expiry_warn_critical_days',
            'driver_initial_performance_score', 'maintenance_due_warning_days', 'maintenance_schedule_window_days',
            'default_on_time_target', 'default_headway_target', 'default_dispatch_eta_minutes',
            'default_travel_time_minutes', 'driver_schedule_buffer_minutes', 'bus_schedule_buffer_minutes',
            'sim_rush_spurt_min', 'sim_rush_spurt_max', 'threshold_min_value', 'threshold_max_value',
            'default_maintenance_duration_minutes', 'map_gps_polling_interval_ms', 'map_telemetry_polling_interval_ms',
            'analytics_top_stops_limit', 'analytics_top_drivers_limit', 'analytics_historical_trend_limit',
            'time_slot_start_hour', 'time_slot_end_hour', 'geofence_default_radius_meters',
            'maintenance_max_failed_inspections', 'system_setting_cache_ttl_seconds', 'route_min_capacity_default'
        ]) || str_ends_with($key, '_limit') || str_ends_with($key, '_days') || str_ends_with($key, '_minutes') || str_ends_with($key, '_seconds') || str_contains($key, '_interval_ms')) {
            $rule = 'required|integer';
            if ($key === 'driver_initial_performance_score' || $key === 'driver_passenger_rating_default' || $key === 'occupancy_warning_threshold' || $key === 'occupancy_critical_threshold') {
                $rule .= '|between:0,100';
            } else {
                $rule .= '|min:0';
            }
        } elseif (in_array($key, [
            'default_route_start_lat', 'default_route_start_lng', 'default_map_center_lat', 'default_map_center_lng',
            'map_default_latitude', 'map_default_longitude', 'coordinates_bounds_north_latitude', 'coordinates_bounds_south_latitude',
            'coordinates_bounds_east_longitude', 'coordinates_bounds_west_longitude'
        ])) {
            $rule = 'required|numeric';
            if (str_contains($key, 'lat')) {
                $rule .= '|between:-90,90';
            } else {
                $rule .= '|between:-180,180';
            }
        } elseif (in_array($key, [
            'default_map_zoom', 'map_default_zoom'
        ])) {
            $rule = 'required|numeric|between:1,20';
        } elseif (in_array($key, [
            'service_start_time', 'service_end_time', 'schedule_default_departure_time',
            'analytics_default_start_time', 'analytics_default_end_time'
        ])) {
            $rule = 'required|date_format:H:i';
        } elseif ($key === 'incident_severity_map') {
            $rule = 'required|json';
        }

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['value' => $value],
            ['value' => $rule]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error for setting ' . $key . ': ' . implode(' ', $validator->errors()->all()),
            ], 422);
        }

        $isNew = false;
        if ($type === 'system') {
            $setting = SystemSetting::where('key', $key)->first();
            if ($setting) {
                $setting->update(['value' => $value ?? '']);
            } else {
                $setting = SystemSetting::create([
                    'key' => $key,
                    'value' => $value ?? '',
                    'description' => $description,
                ]);
                $isNew = true;
            }
        } else {
            if (Schema::hasTable('dispatch_simulation_defaults')) {
                $setting = DispatchSimulationDefault::where('key', $key)->first();
                if ($setting) {
                    $setting->update(['value' => $value ?? '']);
                } else {
                    $setting = DispatchSimulationDefault::create([
                        'key' => $key,
                        'value' => $value ?? '',
                        'description' => $description,
                    ]);
                    $isNew = true;
                }
            } else {
                return response()->json(['success' => false, 'message' => 'Simulation defaults table not found.'], 404);
            }
        }

        // Clear related caches
        \Illuminate\Support\Facades\Cache::forget('routes_all');
        \Illuminate\Support\Facades\Cache::forget('stops_all');
        \Illuminate\Support\Facades\Cache::forget('commuter_dashboard_aggregate');
        \Illuminate\Support\Facades\Cache::forget('commuter_route_stops_aggregate');
        if ($key === 'system_setting_cache_ttl_seconds') {
            \Illuminate\Support\Facades\Cache::forget('system_setting_cache_ttl_val');
        }

        $msgType = $type === 'system' ? 'Setting' : 'Simulation setting';
        $action = $isNew ? 'created' : 'updated';
        $message = "{$msgType} '{$key}' {$action} successfully. All related application caches have been cleared.";

        return response()->json([
            'success' => true,
            'message' => $message,
            'setting' => $setting
        ]);
    }
}










