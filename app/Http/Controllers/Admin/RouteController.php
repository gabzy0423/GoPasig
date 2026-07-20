<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\Stop;
use App\Models\SystemSetting;
use App\Models\DefaultRouteSetting;
use App\Models\Terminal;
use App\Services\BusinessLogicService;
use App\Services\ValidationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\Contracts\RouteGeometryEngineInterface;
use App\Services\GeometryVersioningService;
use App\Services\ValueObjects\Polyline;
use App\Services\PolylineEncoder;
use App\Exceptions\GeometryConflictException;
use App\Models\RouteGeometryVersion;

class RouteController extends Controller
{
    private RouteGeometryEngineInterface $engine;
    private GeometryVersioningService $versioning;

    public function __construct(
        RouteGeometryEngineInterface $engine,
        GeometryVersioningService $versioning
    ) {
        $this->engine = $engine;
        $this->versioning = $versioning;
    }
    /**
     * Store a newly created route in the database.
     * Issue 3.2.1: Now validates polyline coordinates
     */
    public function store(Request $request)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can create routes'
            ], 403);
        }
        $count = Route::count() + 1;
        $defaultPrefix = SystemSetting::get('default_route_name_prefix', 'Route ');

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'description' => 'sometimes|nullable|string',
            'color' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|string',
            'target_on_time_rate' => 'sometimes|required|integer|min:0|max:100',
            'target_headway_minutes' => 'sometimes|required|integer|min:1',
            'polyline_coordinates' => 'sometimes|nullable|array',
        ]);

        // NEW: Validate polyline if provided
        // Issue 3.2.1: Route polyline not validated
        if (!empty($request->polyline_coordinates)) {
            $polylineValidation = BusinessLogicService::validateRoutePolyline($request->polyline_coordinates);
            if (!$polylineValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid route polyline: ' . $polylineValidation['error']
                ], 422);
            }
        }

        $route = Route::create([
            'name' => $request->name ?? ($defaultPrefix . $count),
            'description' => $request->description,
            'color' => $request->color ?? SystemSetting::get('default_route_color', '#003F87'),
            'polyline_coordinates' => $request->polyline_coordinates ?? [],
            'status' => $request->status ?? SystemSetting::get('default_route_status', 'Active'),
            'target_on_time_rate' => $request->target_on_time_rate ?? (int) SystemSetting::get('default_route_on_time_target', 85),
            'target_headway_minutes' => $request->target_headway_minutes ?? (int) SystemSetting::get('default_route_headway_minutes', 15),
        ]);

        // Create default stops for the route
        $routeDefaults = DefaultRouteSetting::first();
        $defaultOriginLabel = $routeDefaults?->default_origin_label
            ?? SystemSetting::get('default_route_origin_label', Terminal::getDefaultName());
        $defaultDestinationLabel = $routeDefaults?->default_destination_label
            ?? SystemSetting::get('default_route_destination_label', Terminal::findByName('New Terminus', 'New Terminus'));

        $fallbackLat = $routeDefaults?->default_latitude ?? SystemSetting::get('map_default_latitude', 14.5593);
        $fallbackLng = $routeDefaults?->default_longitude ?? SystemSetting::get('map_default_longitude', 121.0805);

        $originStop = Stop::create([
            'route_id' => $route->id,
            'name' => $defaultOriginLabel,
            'lat' => $fallbackLat,
            'lng' => $fallbackLng,
            'radius_meters' => (int) SystemSetting::get('stop_default_radius_meters', 50),
            'sequence' => 1
        ]);

        $destinationStop = Stop::create([
            'route_id' => $route->id,
            'name' => $defaultDestinationLabel,
            'lat' => $fallbackLat + 0.002,
            'lng' => $fallbackLng + 0.002,
            'radius_meters' => (int) SystemSetting::get('stop_default_radius_meters', 50),
            'sequence' => 2
        ]);

        // Set initial polyline from stops if not provided
        if (empty($request->polyline_coordinates)) {
            $route->update([
                'polyline_coordinates' => [
                    [$originStop->lat, $originStop->lng],
                    [$destinationStop->lat, $destinationStop->lng]
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Route successfully created!',
            'route' => $route
        ], 201);
    }

    /**
     * Update the specified route details or status in the database.
     */
    public function update(Request $request, Route $route)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can update routes'
            ], 403);
        }

        $allowedStatuses = explode(',', SystemSetting::get('allowed_route_statuses', 'Active,Suspended'));

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'description' => 'sometimes|nullable|string',
            'status' => ['sometimes', 'required', Rule::in($allowedStatuses)],
            'target_on_time_rate' => 'sometimes|required|integer|min:0|max:100',
            'target_headway_minutes' => 'sometimes|required|integer|min:1',
            'polyline_coordinates' => 'sometimes|nullable|array',
        ]);

        // NEW: Validate polyline if provided
        // Issue 3.2.1: Route polyline not validated
        if (isset($request->polyline_coordinates)) {
            $polylineValidation = BusinessLogicService::validateRoutePolyline($request->polyline_coordinates);
            if (!$polylineValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid route polyline: ' . $polylineValidation['error']
                ], 422);
            }
        }

        $route->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Route successfully updated!',
            'route' => $route
        ]);
    }

    /**
     * Remove the specified route from the database.
     */
    public function destroy(Route $route)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can delete routes'
            ], 403);
        }
        $route->delete();

        return response()->json([
            'success' => true,
            'message' => 'Route successfully deleted!'
        ]);
    }

    /**
     * Update route geometry with optimistic locking.
     */
    public function updateGeometry(Request $request, Route $route)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'polyline_coordinates' => 'required|array',
            'last_geometry_version' => 'required|integer',
        ]);

        try {
            $polyline = Polyline::fromArray($request->polyline_coordinates);
            $clientVersion = (int) $request->last_geometry_version;

            $updatedPolyline = $this->engine->updateGeometry($route->id, $polyline, $clientVersion);
            $metrics = $this->engine->computeMetrics($route->id, $updatedPolyline);

            return response()->json([
                'success' => true,
                'message' => 'Route geometry successfully updated!',
                'geometry_version' => $route->fresh()->geometry_version,
                'polyline_coordinates' => $updatedPolyline->toLatLngs(),
                'metrics' => $metrics,
            ]);
        } catch (GeometryConflictException $e) {
            return response()->json([
                'success' => false,
                'conflict' => true,
                'message' => $e->getMessage(),
                'current_version' => $route->fresh()->geometry_version,
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Import route geometry (GeoJSON or Encoded Polyline).
     */
    public function importGeometry(Request $request, Route $route)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $polyline = null;

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $extension = strtolower($file->getClientOriginalExtension());
                $content = file_get_contents($file->getRealPath());

                if ($extension === 'geojson' || $extension === 'json') {
                    $data = json_decode($content, true);
                    $coords = [];
                    if (isset($data['geometry']['coordinates'])) {
                        $rawCoords = $data['geometry']['coordinates'];
                    } elseif (isset($data['coordinates'])) {
                        $rawCoords = $data['coordinates'];
                    } elseif (isset($data['features'][0]['geometry']['coordinates'])) {
                        $rawCoords = $data['features'][0]['geometry']['coordinates'];
                    } else {
                        throw new \InvalidArgumentException("Invalid GeoJSON structure.");
                    }
                    foreach ($rawCoords as $coord) {
                        $coords[] = [(float) $coord[1], (float) $coord[0]]; // GeoJSON [lng, lat] -> [lat, lng]
                    }
                    $polyline = Polyline::fromArray($coords);
                } else {
                    $polyline = PolylineEncoder::decode(trim($content));
                }
            } else {
                $polylineString = $request->input('polyline_string');
                if ($polylineString) {
                    $polyline = PolylineEncoder::decode(trim($polylineString));
                } else {
                    throw new \InvalidArgumentException("No import content provided.");
                }
            }

            if (!$polyline || $polyline->isEmpty()) {
                throw new \InvalidArgumentException("Imported geometry is empty or invalid.");
            }

            $clientVersion = (int) $request->input('last_geometry_version', $route->geometry_version);
            $updatedPolyline = $this->engine->updateGeometry($route->id, $polyline, $clientVersion);
            $metrics = $this->engine->computeMetrics($route->id, $updatedPolyline);

            return response()->json([
                'success' => true,
                'message' => 'Route geometry successfully imported!',
                'geometry_version' => $route->fresh()->geometry_version,
                'polyline_coordinates' => $updatedPolyline->toLatLngs(),
                'metrics' => $metrics,
            ]);
        } catch (GeometryConflictException $e) {
            return response()->json([
                'success' => false,
                'conflict' => true,
                'message' => $e->getMessage(),
                'current_version' => $route->fresh()->geometry_version,
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get geometry version history.
     */
    public function getGeometryHistory(Request $request, Route $route)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($request->has('version_id')) {
            $version = RouteGeometryVersion::where('route_id', $route->id)
                ->with('creator')
                ->findOrFail($request->query('version_id'));
            return response()->json([
                'success' => true,
                'version' => $version,
            ]);
        }

        $perPage = min((int) $request->query('per_page', 10), 50);
        $history = RouteGeometryVersion::where('route_id', $route->id)
            ->with('creator')
            ->orderByDesc('id')
            ->paginate($perPage, [
                'id', 'vertex_count', 'length_km', 'label',
                'created_by_user_id', 'restored_from_version', 'created_at'
            ]);

        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }

    /**
     * Restore route geometry to a specific version.
     */
    public function restoreGeometryVersion(Request $request, Route $route)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'version_id' => 'required|integer',
        ]);

        try {
            $versionId = (int) $request->version_id;
            $restoredPolyline = $this->versioning->restore($route->id, $versionId);
            $metrics = $this->engine->computeMetrics($route->id, $restoredPolyline);

            // Get the newly created version snapshot ID
            $newVersion = RouteGeometryVersion::where('route_id', $route->id)
                ->orderByDesc('id')
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Geometry restored as new version.',
                'version' => $newVersion ? $newVersion->id : null,
                'restored_from' => $versionId,
                'geometry_version' => $route->fresh()->geometry_version,
                'polyline_coordinates' => $restoredPolyline->toLatLngs(),
                'metrics' => $metrics,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate preview geometry for a route using the chosen provider.
     */
    public function generatePreview(Request $request, Route $route, \App\Services\Routing\IntelligentRoutingEngine $routingEngine)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'provider' => 'required|string|in:google,osrm,manual',
        ]);

        try {
            $result = $routingEngine->generatePreview($route, $request->provider, auth()->id());
            return response()->json([
                'success' => true,
                'session_id' => $result->sessionId,
                'generated_geometry' => $result->generatedGeometry,
                'comparison' => $result->comparisonMetrics,
                'quality' => $result->qualityResult,
                'provider' => $result->provider,
                'expires_at' => $result->expiresAt,
            ]);
        } catch (\App\Exceptions\RoutingProviderException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating route: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Accept the generated preview session and update the route's official geometry.
     */
    public function acceptPreview(Request $request, Route $route, \App\Services\Routing\IntelligentRoutingEngine $routingEngine)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'session_id' => 'required|string|uuid',
            'last_geometry_version' => 'required|integer',
        ]);

        try {
            $routingEngine->acceptPreview($request->session_id, (int) $request->last_geometry_version);

            return response()->json([
                'success' => true,
                'message' => 'Route geometry successfully updated from generated preview.',
                'geometry_version' => $route->fresh()->geometry_version,
                'polyline_coordinates' => $route->fresh()->polyline_coordinates,
            ]);
        } catch (\App\Exceptions\GeometryConflictException $e) {
            return response()->json([
                'success' => false,
                'conflict' => true,
                'message' => $e->getMessage(),
                'current_version' => $route->fresh()->geometry_version,
            ], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject the generated preview session.
     */
    public function rejectPreview(Request $request, Route $route, \App\Services\Routing\IntelligentRoutingEngine $routingEngine)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'session_id' => 'required|string|uuid',
        ]);

        try {
            $routingEngine->rejectPreview($request->session_id);
            return response()->json([], 204);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Run advanced Fréchet shape analysis on a preview session.
     */
    public function runAdvancedAnalysis(Request $request, Route $route, \App\Services\Routing\IntelligentRoutingEngine $routingEngine)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'session_id' => 'required|string|uuid',
        ]);

        try {
            $result = $routingEngine->runAdvancedAnalysis($route, $request->session_id);
            return response()->json([
                'success' => true,
                'comparison' => $result['comparison'],
                'quality' => $result['quality'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get routing providers health telemetry snapshot for dashboard widgets.
     */
    public function getTelemetry(\App\Services\Routing\ProviderHealthService $healthSvc, \App\Services\Routing\ProviderQuotaService $quotaSvc)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $telemetry = [];
        $providers = ['google', 'osrm', 'manual'];

        foreach ($providers as $provider) {
            $snapshot = $healthSvc->getSnapshot($provider);
            $telemetry[$provider] = [
                'provider' => $snapshot->provider,
                'average_latency' => round($snapshot->averageLatencyMs, 1),
                'failure_rate' => round($snapshot->failureRate, 1),
                'total_requests' => $snapshot->totalRequests,
                'state' => $snapshot->state,
                'quota_remaining' => $quotaSvc->getRemainingQuota($provider),
                'daily_cost' => round($quotaSvc->getBillingEstimate($provider), 2),
            ];
        }

        return response()->json([
            'success' => true,
            'telemetry' => $telemetry,
        ]);
    }
}
