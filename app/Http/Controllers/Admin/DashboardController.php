<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\Bus;
use App\Models\Trip;
use App\Models\ServiceAlert;


class DashboardController extends Controller
{
    public function index()
    {
        $missingThresholdKey = false;
        if (\Schema::hasTable('dispatch_simulation_defaults')) {
            $missingThresholdKey = !\App\Models\DispatchSimulationDefault::where('key', 'default_demand_threshold')->exists();
        }
        $routes = Route::getAllCached();
        $primaryRouteName = $routes->first()->name ?? \App\Models\SystemSetting::get('default_route_name', 'Route 1');
        $busCapacityLimit = (int) \App\Models\SystemSetting::get('default_bus_capacity', 45);
        $licenseWarningDays = (int) \App\Models\SystemSetting::get('license_expiry_warning_threshold_days', 30);
        $mapCenterLat = (float) \App\Models\SystemSetting::get('map_default_latitude', 14.5690);
        $mapCenterLng = (float) \App\Models\SystemSetting::get('map_default_longitude', 121.0680);
        $mapZoom = (int) \App\Models\SystemSetting::get('map_default_zoom', 13);
        $pollingInterval = (int) \App\Models\SystemSetting::get('map_gps_polling_interval_ms', 10000);

        // System health status: check for active alerts or maintenance buses
        $activeAlerts = \App\Models\ServiceAlert::where('status', 'active')->count();
        $maintenanceBuses = Bus::where('status', 'maintenance')->count();
        if ($activeAlerts > 0) {
            $systemStatus = 'critical';
        } elseif ($maintenanceBuses > 0) {
            $systemStatus = 'degraded';
        } else {
            $systemStatus = 'nominal';
        }

        return view('admin.dashboard', compact(
            'missingThresholdKey', 'routes', 'primaryRouteName', 'busCapacityLimit',
            'licenseWarningDays', 'mapCenterLat', 'mapCenterLng', 'mapZoom',
            'pollingInterval', 'systemStatus'
        ));
    }

    public function getFleetData()
    {
        $stopsByRoute = \App\Models\Stop::getAllCached()->groupBy('route_id');
        $avgPaxByRoute = \App\Models\Trip::where('status', 'completed')
            ->select('route_id', \Illuminate\Support\Facades\DB::raw('AVG(peak_passengers) as avg_peak'))
            ->groupBy('route_id')
            ->pluck('avg_peak', 'route_id');

        $routes = Route::getAllCached()->map(function ($route) use ($stopsByRoute, $avgPaxByRoute) {
            $route->setRelation('stops', $stopsByRoute->get($route->id, collect()));

            $avgPax = $avgPaxByRoute->get($route->id);

            if ($avgPax === null) {
                // Get default average pax from SystemSetting (optional fallback)
                $avgPax = (int) \App\Models\SystemSetting::get('default_route_avg_pax', 0);
            }

            $route->avg_passengers = (int) round($avgPax);
            return $route;
        });

        $buses = Bus::all();
        $trips = Trip::with(['bus', 'driver', 'route'])->latest()->take(5)->get();

        return response()->json(compact('routes', 'buses', 'trips'));
    }

    public function getSettings()
    {
        $systemSettings = \App\Models\SystemSetting::orderBy('key')->get();
        $simulationDefaults = [];
        if (\Schema::hasTable('dispatch_simulation_defaults')) {
            $simulationDefaults = \App\Models\DispatchSimulationDefault::orderBy('key')->get();
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
        ]);

        if ($validated['type'] === 'system') {
            $setting = \App\Models\SystemSetting::where('key', $validated['key'])->first();
            if ($setting) {
                $setting->update(['value' => $validated['value'] ?? '']);
                return response()->json(['success' => true, 'message' => "Setting '{$validated['key']}' updated successfully."]);
            }
        } else {
            if (\Schema::hasTable('dispatch_simulation_defaults')) {
                $setting = \App\Models\DispatchSimulationDefault::where('key', $validated['key'])->first();
                if ($setting) {
                    $setting->update(['value' => $validated['value'] ?? '']);
                    return response()->json(['success' => true, 'message' => "Simulation setting '{$validated['key']}' updated successfully."]);
                }
            }
        }

        return response()->json(['success' => false, 'message' => 'Setting not found.'], 404);
    }
}
