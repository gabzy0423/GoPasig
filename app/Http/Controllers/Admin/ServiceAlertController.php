<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceAlert;
use App\Models\Route;
use App\Models\User;
use App\Models\Driver;
use App\Models\Schedule;
use App\Services\ValidationService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ServiceAlertController extends Controller
{
    /**
     * Display a listing of the service alerts.
     */
    public function index()
    {
        $alerts = ServiceAlert::withCount('reads')->orderBy('created_at', 'desc')->get();
        
        $totalCommuters = User::where('role', 'passenger')->count();
        $totalDrivers = Driver::count();
        
        $routes = Route::all();
        $routeStats = [];
        $hasInsufficient = false;
        foreach ($routes as $route) {
            $commuters = Schedule::where('route_id', $route->id)->sum('passengers');
            $noCommuterData = ($commuters === 0 || $commuters === null);
            if ($noCommuterData) {
                $hasInsufficient = true;
            }

            // BL-7.4: Count drivers by assigned_route in all formats (int, string, or name)
            $drivers = Driver::where('assigned_route', $route->id)
                ->orWhere('assigned_route', (string) $route->id)
                ->orWhere('assigned_route', $route->name)
                ->count();

            $routeStats[$route->name] = [
                'commuters' => (int) $commuters,
                'drivers' => (int) $drivers,
                'no_data' => $noCommuterData,
            ];
        }

        $routeStats['All routes'] = [
            'commuters' => $totalCommuters,
            'drivers'   => $totalDrivers,
        ];

        // HC-7.4: Compute dynamically instead of hardcoding to true
        $insufficientData = $routes->isEmpty() || $hasInsufficient;

        $stats = [
            'total_commuters' => $totalCommuters,
            'total_drivers' => $totalDrivers,
            'route_stats' => $routeStats,
            'insufficient_route_data' => $insufficientData,
        ];

        return response()->json([
            'success' => true,
            'alerts' => $alerts,
            'stats' => $stats
        ]);
    }

    /**
     * Store a newly created service alert.
     * Issue 5.1.3: XSS sanitization added
     */
    public function store(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can create alerts'
            ], 403);
        }
        $severityOptions = \App\Models\SystemSetting::get('service_alert_severity_options', 'Low,Medium,High,Emergency');
        $typeOptions = \App\Models\SystemSetting::get('service_alert_type_options', 'Delay,Route change,Suspension,Breakdown,Weather,Emergency');

        $allowedSeverities = array_unique(array_merge(
            explode(',', $severityOptions),
            array_map('strtolower', explode(',', $severityOptions)),
            array_map('ucfirst', explode(',', $severityOptions))
        ));
        $allowedTypes = array_unique(array_merge(
            explode(',', $typeOptions),
            array_map('strtolower', explode(',', $typeOptions)),
            array_map('ucfirst', explode(',', $typeOptions)),
            ['delay', 'route_change', 'suspension', 'breakdown', 'weather', 'emergency', 'announcement', 'Announcement']
        ));

        $validated = $request->validate([
            'title' => 'required|string|max:80',
            'message' => 'required|string|max:500',
            'severity' => 'required|in:' . implode(',', $allowedSeverities),
            'type' => 'required|string|max:50|in:' . implode(',', $allowedTypes),
            'affects' => 'required|array',
            'timing' => 'required|in:now,later',
            'schedule_time' => 'required_if:timing,later|nullable|date',
            'suspend_route' => 'nullable|boolean',
        ]);

        // Sanitize message for XSS protection (Issue 5.1.3)
        $messageValidation = ValidationService::validateServiceAlertMessage($validated['message'], 500);
        if (!$messageValidation['valid']) {
            return response()->json([
                'success' => false,
                'message' => $messageValidation['message']
            ], 422);
        }
        $sanitizedMessage = $messageValidation['sanitized'];

        $severityMap = [
            'low' => 'info',
            'medium' => 'warning',
            'high' => 'high',
            'emergency' => 'critical',
            'critical' => 'critical',
        ];
        $dbSeverity = $severityMap[strtolower($validated['severity'])] ?? 'info';

        $routeId = null;
        if (count($validated['affects']) === 1) {
            $route = Route::where('name', $validated['affects'][0])->first();
            $routeId = $route?->id;
        }

        $createdAt = Carbon::now();
        if ($validated['timing'] === 'later' && !empty($validated['schedule_time'])) {
            $createdAt = Carbon::parse($validated['schedule_time']);
        }

        $alert = new ServiceAlert([
            'route_id' => $routeId,
            'title' => $validated['title'],
            'message' => $sanitizedMessage,
            'severity' => $dbSeverity,
            'type' => $validated['type'],
            'affected_routes' => implode(',', $validated['affects']),
            'status' => 'active',
            'suspend_route' => !empty($validated['suspend_route']) && $validated['suspend_route'],
        ]);
        $alert->created_at = $createdAt;
        $alert->updated_at = Carbon::now();
        $alert->save();

        if (!empty($validated['suspend_route']) && $validated['suspend_route']) {
            Route::whereIn('name', $validated['affects'])->update(['status' => 'Suspended']);
        }

        // Notify commuters/drivers (simulated notification broadcast)
        if ($validated['timing'] === 'now') {
            \App\Services\NotificationService::sendServiceAlertNotification($alert);
        }

        return response()->json([
            'success' => true,
            'message' => $validated['timing'] === 'later' ? 'Alert scheduled successfully!' : 'Alert broadcasted successfully!',
            'alert' => $alert
        ], 201);
    }

    /**
     * Update the specified service alert.
     * Issue 5.1.3: XSS sanitization added
     */
    public function update(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can update alerts'
            ], 403);
        }
        $alert = ServiceAlert::findOrFail($id);

        $severityOptions = \App\Models\SystemSetting::get('service_alert_severity_options', 'Low,Medium,High,Emergency');
        $typeOptions = \App\Models\SystemSetting::get('service_alert_type_options', 'Delay,Route change,Suspension,Breakdown,Weather,Emergency');

        $allowedSeverities = array_unique(array_merge(
            explode(',', $severityOptions),
            array_map('strtolower', explode(',', $severityOptions)),
            array_map('ucfirst', explode(',', $severityOptions))
        ));
        $allowedTypes = array_unique(array_merge(
            explode(',', $typeOptions),
            array_map('strtolower', explode(',', $typeOptions)),
            array_map('ucfirst', explode(',', $typeOptions)),
            ['delay', 'route_change', 'suspension', 'breakdown', 'weather', 'emergency', 'announcement', 'Announcement']
        ));

        $validated = $request->validate([
            'title' => 'required|string|max:80',
            'message' => 'required|string|max:500',
            'severity' => 'required|in:' . implode(',', $allowedSeverities),
            'type' => 'required|string|max:50|in:' . implode(',', $allowedTypes),
            'affects' => 'required|array',
            'timing' => 'required|in:now,later',
            'schedule_time' => 'required_if:timing,later|nullable|date',
            'suspend_route' => 'nullable|boolean',
        ]);

        // Sanitize message for XSS protection (Issue 5.1.3)
        $messageValidation = ValidationService::validateServiceAlertMessage($validated['message'], 500);
        if (!$messageValidation['valid']) {
            return response()->json([
                'success' => false,
                'message' => $messageValidation['message']
            ], 422);
        }
        $sanitizedMessage = $messageValidation['sanitized'];

        $severityMap = [
            'low' => 'info',
            'medium' => 'warning',
            'high' => 'high',
            'emergency' => 'critical',
            'critical' => 'critical',
        ];
        $dbSeverity = $severityMap[strtolower($validated['severity'])] ?? 'info';

        $routeId = null;
        if (count($validated['affects']) === 1) {
            $route = Route::where('name', $validated['affects'][0])->first();
            $routeId = $route?->id;
        }

        $createdAt = $alert->created_at;
        if ($validated['timing'] === 'later' && !empty($validated['schedule_time'])) {
            $createdAt = Carbon::parse($validated['schedule_time']);
        } elseif ($validated['timing'] === 'now') {
            $createdAt = Carbon::now();
        }

        $alert->fill([
            'route_id' => $routeId,
            'title' => $validated['title'],
            'message' => $sanitizedMessage,
            'severity' => $dbSeverity,
            'type' => $validated['type'],
            'affected_routes' => implode(',', $validated['affects']),
            'suspend_route' => !empty($validated['suspend_route']) && $validated['suspend_route'],
        ]);
        $alert->created_at = $createdAt;
        $alert->updated_at = Carbon::now();
        $alert->save();

        if (!empty($validated['suspend_route']) && $validated['suspend_route']) {
            Route::whereIn('name', $validated['affects'])->update(['status' => 'Suspended']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Alert updated successfully!',
            'alert' => $alert
        ]);
    }

    /**
     * Resolve the specified service alert and notify affected parties.
     * Issue 3.2.3: Notify commuters/drivers on suspension
     */
    public function resolve($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can resolve alerts'
            ], 403);
        }
        $alert = ServiceAlert::findOrFail($id);
        $alert->update([
            'status' => 'resolved',
            'updated_at' => Carbon::now()
        ]);

        if ($alert->suspend_route && !empty($alert->affected_routes)) {
            $affectedRoutes = explode(',', $alert->affected_routes);
            foreach ($affectedRoutes as $routeName) {
                $otherActiveSuspensionExists = ServiceAlert::where('status', 'active')
                    ->where('id', '!=', $alert->id)
                    ->where('suspend_route', true)
                    ->where(function($q) use ($routeName) {
                        $q->where('affected_routes', $routeName)
                          ->orWhere('affected_routes', 'like', $routeName . ',%')
                          ->orWhere('affected_routes', 'like', '%,' . $routeName)
                          ->orWhere('affected_routes', 'like', '%,' . $routeName . ',%');
                    })
                    ->exists();
                if (!$otherActiveSuspensionExists) {
                    Route::where('name', $routeName)->update(['status' => 'Active']);
                }
            }
        }

        \App\Services\NotificationService::sendServiceAlertNotification($alert);

        return response()->json([
            'success' => true,
            'message' => 'Alert successfully resolved!',
            'alert' => $alert
        ]);
    }

    /**
     * Resolve all active service alerts and notify users.
     * Issue 3.2.3: Notify commuters/drivers on suspension
     */
    public function resolveAll()
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can resolve all alerts'
            ], 403);
        }

        $activeAlerts = ServiceAlert::where('status', 'active')->get();

        foreach ($activeAlerts as $alert) {
            $alert->update([
                'status' => 'resolved',
                'updated_at' => Carbon::now()
            ]);

            if ($alert->suspend_route && !empty($alert->affected_routes)) {
                $affectedRoutes = explode(',', $alert->affected_routes);
                foreach ($affectedRoutes as $routeName) {
                    $otherActiveSuspensionExists = ServiceAlert::where('status', 'active')
                        ->where('id', '!=', $alert->id)
                        ->where('suspend_route', true)
                        ->where(function($q) use ($routeName) {
                            $q->where('affected_routes', $routeName)
                              ->orWhere('affected_routes', 'like', $routeName . ',%')
                              ->orWhere('affected_routes', 'like', '%,' . $routeName)
                              ->orWhere('affected_routes', 'like', '%,' . $routeName . ',%');
                        })
                        ->exists();
                    if (!$otherActiveSuspensionExists) {
                        Route::where('name', $routeName)->update(['status' => 'Active']);
                    }
                }
            }

            \App\Services\NotificationService::sendServiceAlertNotification($alert);
        }

        return response()->json([
            'success' => true,
            'message' => 'All active alerts have been resolved.'
        ]);
    }

    /**
     * Remove the specified service alert.
     */
    public function destroy($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can delete alerts'
            ], 403);
        }
        $alert = ServiceAlert::findOrFail($id);
        $alert->delete();

        return response()->json([
            'success' => true,
            'message' => 'Alert successfully deleted!'
        ]);
    }

    /**
     * Show the service alerts history page.
     */
    public function history()
    {
        return redirect(route('admin.dashboard') . '#alerts-history');
    }
}
