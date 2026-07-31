<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceAlert;
use App\Models\ServiceAlertLog;
use App\Models\Route;
use App\Models\User;
use App\Models\Driver;
use App\Models\Schedule;
use App\Models\Trip;
use App\Services\ValidationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Database\Seeders\UATSuspendRouteFixtureSeeder;

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

    public function targetingRoutes()
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can view alert targeting routes'
            ], 403);
        }

        $routes = $this->adminAlertTargetRoutes()
            ->map(fn ($route) => [
                'id' => $route->id,
                'name' => $route->name,
                'status' => $route->status,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'all_official_label' => $this->allOfficialRoutesLabel(),
            'routes' => $routes,
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

        $this->validateSuspensionPolicy($validated);

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

        $targeting = $this->normalizePublicAlertTargets($validated['affects']);
        $routeId = $targeting['route_id'];

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
            'affected_routes' => implode(',', $targeting['stored_affects']),
            'status' => 'active',
            'suspend_route' => !empty($validated['suspend_route']) && $validated['suspend_route'],
        ]);
        $alert->created_at = $createdAt;
        $alert->updated_at = Carbon::now();
        $alert->save();

        if (!empty($validated['suspend_route']) && $validated['suspend_route']) {
            Route::whereIn('name', $targeting['suspension_route_names'])->update(['status' => 'Suspended']);
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

        $this->validateSuspensionPolicy($validated, $alert);

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

        $targeting = $this->normalizePublicAlertTargets($validated['affects']);
        $routeId = $targeting['route_id'];

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
            'affected_routes' => implode(',', $targeting['stored_affects']),
            'suspend_route' => !empty($validated['suspend_route']) && $validated['suspend_route'],
        ]);
        $alert->created_at = $createdAt;
        $alert->updated_at = Carbon::now();
        $alert->save();

        if (!empty($validated['suspend_route']) && $validated['suspend_route']) {
            Route::whereIn('name', $targeting['suspension_route_names'])->update(['status' => 'Suspended']);
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
    public function resolve(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can resolve alerts'
            ], 403);
        }
        $alert = ServiceAlert::findOrFail($id);

        $isConfirmed = $request->boolean('confirm')
            || $request->boolean('confirm_active_trips')
            || $request->input('confirm') === 'true'
            || $request->input('confirm') === 1;

        if ($alert->suspend_route && ! $isConfirmed) {
            $routeNames = $this->alertSuspensionRouteNames($alert);
            $routeIds = Route::whereIn('name', $routeNames)->pluck('id')->all();

            if (! empty($routeIds)) {
                $ongoingTripsCount = Trip::whereIn('route_id', $routeIds)
                    ->where('status', 'ongoing')
                    ->count();

                if ($ongoingTripsCount > 0) {
                    $tripText = $ongoingTripsCount === 1 ? '1 ongoing trip' : "{$ongoingTripsCount} ongoing trips";
                    $message = "This route still has {$tripText}. Resolving the suspension will allow new dispatches. Continue?";

                    return response()->json([
                        'success' => false,
                        'requiresConfirmation' => true,
                        'remainingActiveTrips' => $ongoingTripsCount,
                        'message' => $message,
                    ], 200);
                }
            }
        }

        $alert->update([
            'status' => 'resolved',
            'updated_at' => Carbon::now()
        ]);

        if ($alert->suspend_route && !empty($alert->affected_routes)) {
            foreach ($this->alertSuspensionRouteNames($alert) as $routeName) {
                if (! $this->otherActiveSuspensionExistsForRoute($routeName, $alert->id)) {
                    Route::where('name', $routeName)->update(['status' => 'Active']);
                }
            }
        }

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

            if ($alert->suspend_route && !empty($alert->affected_routes)) {
                foreach ($this->alertSuspensionRouteNames($alert) as $routeName) {
                    if (! $this->otherActiveSuspensionExistsForRoute($routeName, $alert->id)) {
                        Route::where('name', $routeName)->update(['status' => 'Active']);
                    }
                }
            }

            // ISSUE-050 FIX: Notify affected drivers and admins upon resolving all active alerts
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

    public function historyLogs()
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can view alert history'
            ], 403);
        }

        $logs = ServiceAlertLog::orderByDesc('archived_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ServiceAlertLog $log) => [
                'id' => $log->id,
                'service_alert_id' => $log->service_alert_id,
                'title' => $log->title,
                'message' => $log->message,
                'type' => $log->type,
                'severity' => $log->severity,
                'affected_routes' => $this->splitAffectedRoutes($log->affected_routes),
                'status' => $log->status,
                'suspend_route' => (bool) $log->suspend_route,
                'alert_created_at' => $log->alert_created_at?->toJSON(),
                'resolved_at' => $log->resolved_at?->toJSON(),
                'archived_at' => $log->archived_at?->toJSON(),
            ]);

        return response()->json([
            'success' => true,
            'history' => $logs,
        ]);
    }

    /**
     * Archive the specified service alert.
     */
    public function destroy($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can archive alerts'
            ], 403);
        }

        $alert = ServiceAlert::find($id);

        if (! $alert) {
            return response()->json([
                'success' => false,
                'message' => 'Service Alert was already archived or no longer exists.',
            ], 404);
        }

        if ($alert->status === 'active' && (bool) $alert->suspend_route) {
            return response()->json([
                'success' => false,
                'message' => 'Operational suspension alerts must be resolved before they can be archived.',
                'errors' => [
                    'alert' => ['Operational suspension alerts must be resolved before they can be archived.'],
                ],
            ], 422);
        }

        DB::transaction(function () use ($id) {
            $alert = ServiceAlert::whereKey($id)->lockForUpdate()->firstOrFail();

            ServiceAlertLog::firstOrCreate(
                ['service_alert_id' => $alert->id],
                [
                    'title' => $alert->title,
                    'message' => $alert->message,
                    'type' => $alert->type,
                    'severity' => $alert->severity,
                    'affected_routes' => $alert->affected_routes,
                    'status' => $alert->status,
                    'suspend_route' => (bool) $alert->suspend_route,
                    'alert_created_at' => $alert->created_at,
                    'resolved_at' => $alert->status === 'resolved' ? $alert->updated_at : null,
                    'archived_at' => Carbon::now(),
                ]
            );

            $alert->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Alert successfully archived!'
        ]);
    }

    private function normalizePublicAlertTargets(array $affects): array
    {
        $allOfficialLabel = $this->allOfficialRoutesLabel();
        $canonicalRoutes = Route::canonicalProduction()
            ->whereNotIn('status', ['inactive', 'Inactive'])
            ->get(['id', 'name'])
            ->keyBy('name');
        $canonicalNames = $canonicalRoutes->keys()->all();
        $adminRoutes = $this->adminAlertTargetRoutes()->keyBy('name');

        $requested = collect($affects)
            ->map(fn ($routeName) => trim((string) $routeName))
            ->filter()
            ->unique()
            ->values();

        if ($requested->isEmpty()) {
            throw ValidationException::withMessages([
                'affects' => 'Select at least one official route target.',
            ]);
        }

        $containsAllOfficial = $requested->contains(fn ($routeName) => strcasecmp($routeName, $allOfficialLabel) === 0 || strcasecmp($routeName, 'All routes') === 0 || strcasecmp($routeName, 'All Routes') === 0);

        if ($containsAllOfficial) {
            return [
                'stored_affects' => [$allOfficialLabel],
                'suspension_route_names' => $canonicalNames,
                'route_id' => null,
            ];
        }

        $invalidTargets = $requested->reject(fn ($routeName) => $adminRoutes->has($routeName))->values();
        if ($invalidTargets->isNotEmpty()) {
            throw ValidationException::withMessages([
                'affects' => 'Service alert targeting is limited to official Route 1, Route 2, Route 3, and the active UAT suspend-route fixture when present.',
            ]);
        }

        return [
            'stored_affects' => $requested->all(),
            'suspension_route_names' => $requested->all(),
            'route_id' => $requested->count() === 1 ? $adminRoutes->get($requested->first())->id : null,
        ];
    }

    private function validateSuspensionPolicy(array $validated, ?ServiceAlert $existingAlert = null): void
    {
        $routeSuspensionRequested = !empty($validated['suspend_route']) || ($existingAlert?->suspend_route ?? false);

        if (! $routeSuspensionRequested) {
            return;
        }

        if (! $this->allowsRouteSuspension((string) $validated['type'], (string) $validated['severity'])) {
            throw ValidationException::withMessages([
                'suspend_route' => 'Suspend Route is allowed only for Suspension alerts, Emergency or High emergency alerts, and Emergency-severity weather, breakdown, or delay alerts.',
            ]);
        }
    }

    private function allowsRouteSuspension(string $type, string $severity): bool
    {
        $normalizedType = strtolower(str_replace([' ', '-'], '_', trim($type)));
        $normalizedSeverity = strtolower(trim($severity));

        return match ($normalizedType) {
            'suspension' => true,
            'weather', 'breakdown', 'delay' => $normalizedSeverity === 'emergency',
            'emergency' => in_array($normalizedSeverity, ['high', 'emergency'], true),
            default => false,
        };
    }

    private function adminAlertTargetRoutes()
    {
        $canonical = Route::canonicalProduction()
            ->whereNotIn('status', ['inactive', 'Inactive'])
            ->orderBy('id')
            ->get(['id', 'name', 'status']);

        $uatRoute = Route::where('name', UATSuspendRouteFixtureSeeder::ROUTE_NAME)
            ->whereNotIn('status', ['inactive', 'Inactive'])
            ->first(['id', 'name', 'status']);

        return $uatRoute
            ? $canonical->push($uatRoute)->unique('id')->values()
            : $canonical->values();
    }

    private function splitAffectedRoutes(?string $affectedRoutes): array
    {
        return collect(explode(',', (string) $affectedRoutes))
            ->map(fn ($routeName) => trim($routeName))
            ->filter()
            ->values()
            ->all();
    }
    private function alertSuspensionRouteNames(ServiceAlert $alert): array
    {
        $affectedRoutes = collect(explode(',', (string) $alert->affected_routes))
            ->map(fn ($routeName) => trim($routeName))
            ->filter()
            ->values();

        if ($affectedRoutes->contains(fn ($routeName) => strcasecmp($routeName, $this->allOfficialRoutesLabel()) === 0 || strcasecmp($routeName, 'All routes') === 0 || strcasecmp($routeName, 'All Routes') === 0)) {
            return Route::canonicalProduction()
                ->whereNotIn('status', ['inactive', 'Inactive'])
                ->pluck('name')
                ->all();
        }

        return $affectedRoutes->all();
    }

    private function otherActiveSuspensionExistsForRoute(string $routeName, int $excludedAlertId): bool
    {
        return ServiceAlert::where('status', 'active')
            ->where('id', '!=', $excludedAlertId)
            ->where('suspend_route', true)
            ->where(function ($q) use ($routeName) {
                $q->where('affected_routes', $routeName)
                    ->orWhere('affected_routes', $this->allOfficialRoutesLabel())
                    ->orWhere('affected_routes', 'All routes')
                    ->orWhere('affected_routes', 'All Routes')
                    ->orWhere('affected_routes', 'like', $routeName . ',%')
                    ->orWhere('affected_routes', 'like', '%,' . $routeName)
                    ->orWhere('affected_routes', 'like', '%,' . $routeName . ',%');
            })
            ->exists();
    }

    private function allOfficialRoutesLabel(): string
    {
        return 'All official routes';
    }
    /**
     * Show the service alerts history page.
     */
    public function history()
    {
        return redirect(route('admin.dashboard') . '#alerts-history');
    }
}

