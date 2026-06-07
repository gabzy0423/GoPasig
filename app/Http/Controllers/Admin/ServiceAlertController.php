<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceAlert;
use App\Models\Route;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ServiceAlertController extends Controller
{
    /**
     * Display a listing of the service alerts.
     */
    public function index()
    {
        $alerts = ServiceAlert::orderBy('created_at', 'desc')->get();
        return response()->json([
            'success' => true,
            'alerts' => $alerts
        ]);
    }

    /**
     * Store a newly created service alert.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:80',
            'message' => 'required|string|max:280',
            'severity' => 'required|in:Low,Medium,High,Emergency',
            'type' => 'required|string|max:50',
            'affects' => 'required|array',
            'timing' => 'required|in:now,later',
            'schedule_time' => 'required_if:timing,later|nullable|date',
            'suspend_route' => 'nullable|boolean',
        ]);

        // Map severity from UI string to DB enum value
        // DB enum: 'info', 'warning', 'critical'
        $severityMap = [
            'Low' => 'info',
            'Medium' => 'warning',
            'High' => 'warning',
            'Emergency' => 'critical'
        ];
        $dbSeverity = $severityMap[$validated['severity']] ?? 'info';

        // Parse route mapping
        // Front-end affects: ['Route A', 'Route B', ...]
        // UI Route A -> DB Route 1 (ID 1)
        // UI Route B -> DB Route 2 (ID 2)
        // UI Route C -> DB Route 3 (ID 3)
        $routeId = null;
        if (count($validated['affects']) === 1) {
            $routeMapping = [
                'Route A' => 1,
                'Route B' => 2,
                'Route C' => 3
            ];
            $routeId = $routeMapping[$validated['affects'][0]] ?? null;
        }

        // Set creation/publish time
        $createdAt = Carbon::now();
        if ($validated['timing'] === 'later' && !empty($validated['schedule_time'])) {
            $createdAt = Carbon::parse($validated['schedule_time']);
        }

        $alert = ServiceAlert::create([
            'route_id' => $routeId,
            'title' => $validated['title'],
            'message' => $validated['message'],
            'severity' => $dbSeverity,
            'type' => $validated['type'],
            'affected_routes' => implode(',', $validated['affects']),
            'status' => 'active',
            'created_at' => $createdAt,
            'updated_at' => Carbon::now()
        ]);

        // Route suspension
        if (!empty($validated['suspend_route']) && $validated['suspend_route']) {
            foreach ($validated['affects'] as $affected) {
                $routeNum = null;
                if ($affected === 'Route A') $routeNum = 1;
                elseif ($affected === 'Route B') $routeNum = 2;
                elseif ($affected === 'Route C') $routeNum = 3;

                if ($routeNum) {
                    $route = Route::find($routeNum);
                    if ($route) {
                        $route->update(['status' => 'Suspended']);
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => $validated['timing'] === 'later' ? 'Alert scheduled successfully!' : 'Alert broadcasted successfully!',
            'alert' => $alert
        ], 201);
    }

    /**
     * Update the specified service alert.
     */
    public function update(Request $request, $id)
    {
        $alert = ServiceAlert::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:80',
            'message' => 'required|string|max:280',
            'severity' => 'required|in:Low,Medium,High,Emergency',
            'type' => 'required|string|max:50',
            'affects' => 'required|array',
            'timing' => 'required|in:now,later',
            'schedule_time' => 'required_if:timing,later|nullable|date',
            'suspend_route' => 'nullable|boolean',
        ]);

        $severityMap = [
            'Low' => 'info',
            'Medium' => 'warning',
            'High' => 'warning',
            'Emergency' => 'critical'
        ];
        $dbSeverity = $severityMap[$validated['severity']] ?? 'info';

        $routeId = null;
        if (count($validated['affects']) === 1) {
            $routeMapping = [
                'Route A' => 1,
                'Route B' => 2,
                'Route C' => 3
            ];
            $routeId = $routeMapping[$validated['affects'][0]] ?? null;
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
            'message' => $validated['message'],
            'severity' => $dbSeverity,
            'type' => $validated['type'],
            'affected_routes' => implode(',', $validated['affects']),
            'created_at' => $createdAt,
            'updated_at' => Carbon::now()
        ]);

        // Route suspension
        if (!empty($validated['suspend_route']) && $validated['suspend_route']) {
            foreach ($validated['affects'] as $affected) {
                $routeNum = null;
                if ($affected === 'Route A') $routeNum = 1;
                elseif ($affected === 'Route B') $routeNum = 2;
                elseif ($affected === 'Route C') $routeNum = 3;

                if ($routeNum) {
                    $route = Route::find($routeNum);
                    if ($route) {
                        $route->update(['status' => 'Suspended']);
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Alert updated successfully!',
            'alert' => $alert
        ]);
    }

    /**
     * Resolve the specified service alert.
     */
    public function resolve($id)
    {
        $alert = ServiceAlert::findOrFail($id);
        $alert->update([
            'status' => 'resolved',
            'updated_at' => Carbon::now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Alert successfully resolved!',
            'alert' => $alert
        ]);
    }

    /**
     * Resolve all active service alerts.
     */
    public function resolveAll()
    {
        ServiceAlert::where('status', 'active')->update([
            'status' => 'resolved',
            'updated_at' => Carbon::now()
        ]);

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
        $alert = ServiceAlert::findOrFail($id);
        $alert->delete();

        return response()->json([
            'success' => true,
            'message' => 'Alert successfully deleted!'
        ]);
    }
}
