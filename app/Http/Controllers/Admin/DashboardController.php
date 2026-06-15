<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\Bus;
use App\Models\Trip;


class DashboardController extends Controller
{
    public function index()
    {
        $missingThresholdKey = false;
        if (\Schema::hasTable('dispatch_simulation_defaults')) {
            $missingThresholdKey = !\App\Models\DispatchSimulationDefault::where('key', 'default_demand_threshold')->exists();
        }
        $routes = Route::getAllCached();
        return view('admin.dashboard', compact('missingThresholdKey', 'routes'));
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
                $fallback = [1 => 145, 2 => 165, 3 => 110, 4 => 125];
                $avgPax = $fallback[$route->id] ?? 120;
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
