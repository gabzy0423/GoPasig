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
        foreach ($routes as $route) {
            $commuters = Schedule::where('route_id', $route->id)->sum('passengers');
            $noCommuterData = ($commuters === 0 || $commuters === null);

            $drivers = Driver::where('assigned_route', $route->id)->count();
            $noDriverData = ($drivers === 0);

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

        $stats = [
            'total_commuters' => $totalCommuters,
            'total_drivers' => $totalDrivers,
            'route_stats' => $routeStats,
            'insufficient_route_data' => true,
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
        $validated = $request->validate([
            'title' => 'required|string|max:80',
            'message' => 'required|string|max:500',
            'severity' => 'required|in:Low,Medium,High,Emergency',
            'type' => 'required|string|max:50',
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
            'Low' => 'info',
            'Medium' => 'warning',
            'High' => 'warning',
            'Emergency' => 'critical'
        ];
        $dbSeverity = $severityMap[$validated['severity']] ?? 'info';

        $routeId = null;
        if (count($validated['affects']) === 1) {
            $route = Route::where('name', $validated['affects'][0])->first();
            $routeId = $route?->id;
        }

        $createdAt = Carbon::now();
        if ($validated['timing'] === 'later' && !empty($validated['schedule_time'])) {
            $createdAt = Carbon::parse($validated['schedule_time']);
        }

        $alert = ServiceAlert::create([
            'route_id' => $routeId,
            'title' => $validated['title'],
            'message' => $sanitizedMessage,
            'severity' => $dbSeverity,
            'type' => $validated['type'],
            'affected_routes' => implode(',', $validated['affects']),
            'status' => 'active',
            'created_at' => $createdAt,
            'updated_at' => Carbon::now()
        ]);

        if (!empty($validated['suspend_route']) && $validated['suspend_route']) {
            Route::whereIn('name', $validated['affects'])->update(['status' => 'Suspended']);
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

        $validated = $request->validate([
            'title' => 'required|string|max:80',
            'message' => 'required|string|max:500',
            'severity' => 'required|in:Low,Medium,High,Emergency',
            'type' => 'required|string|max:50',
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
            'Low' => 'info',
            'Medium' => 'warning',
            'High' => 'warning',
            'Emergency' => 'critical'
        ];
        $dbSeverity = $severityMap[$validated['severity']] ?? 'info';

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

        $alert->update([
            'route_id' => $routeId,
            'title' => $validated['title'],
            'message' => $sanitizedMessage,
            'severity' => $dbSeverity,
            'type' => $validated['type'],
            'affected_routes' => implode(',', $validated['affects']),
            'created_at' => $createdAt,
            'updated_at' => Carbon::now()
        ]);

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
     * Issue 3.2.3: Notify commuters/drivers on suspension/resolution
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

        // ISSUE-050 FIX: Notify affected drivers and admins upon resolution
        $recipientIds = User::where('role', 'admin')->pluck('id')->toArray();
        if ($alert->route_id) {
            $driverUserIds = Driver::where('assigned_route', $alert->route_id)
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->toArray();
            $recipientIds = array_unique(array_merge($recipientIds, $driverUserIds));
        }
        \App\Services\NotificationService::sendServiceAlertNotification($alert, $recipientIds);

        return response()->json([
            'success' => true,
            'message' => 'Alert successfully resolved!',
            'alert' => $alert
        ]);
    }

    /**
     * Resolve all active service alerts and notify users.
     * Issue 3.2.3: Notify commuters/drivers on suspension/resolution
     */
    public function resolveAll()
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can resolve all alerts'
            ], 403);
        }

        // ISSUE-050 FIX: Notify affected drivers and admins upon resolving all active alerts
        $activeAlerts = ServiceAlert::where('status', 'active')->get();
        foreach ($activeAlerts as $alert) {
            $alert->update([
                'status' => 'resolved',
                'updated_at' => Carbon::now()
            ]);

            $recipientIds = User::where('role', 'admin')->pluck('id')->toArray();
            if ($alert->route_id) {
                $driverUserIds = Driver::where('assigned_route', $alert->route_id)
                    ->whereNotNull('user_id')
                    ->pluck('user_id')
                    ->toArray();
                $recipientIds = array_unique(array_merge($recipientIds, $driverUserIds));
            }
            \App\Services\NotificationService::sendServiceAlertNotification($alert, $recipientIds);
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
