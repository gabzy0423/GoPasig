<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RouteVariant;
use App\Services\Routing\RouteVariantGeometryWorkflow;
use Illuminate\Http\Request;

class RouteVariantGeometryController extends Controller
{
    public function generatePreview(Request $request, RouteVariant $routeVariant, RouteVariantGeometryWorkflow $workflow)
    {
        $this->abortUnlessRouteGenerationMaintenanceEnabled();

        abort_unless(auth()->user()->role === 'admin', 403);
        $request->validate(['provider' => 'required|in:google']);

        try {
            $result = $workflow->generatePreview($routeVariant, 'google', auth()->id());
            return response()->json(['success' => true] + $result->toArray());
        } catch (\App\Exceptions\RoutingProviderException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function acceptPreview(Request $request, RouteVariant $routeVariant, RouteVariantGeometryWorkflow $workflow)
    {
        $this->abortUnlessRouteGenerationMaintenanceEnabled();

        abort_unless(auth()->user()->role === 'admin', 403);
        $data = $request->validate(['session_id' => 'required|uuid', 'last_geometry_version' => 'required|integer']);

        try {
            $updated = $workflow->acceptPreview($data['session_id'], $routeVariant, (int) $data['last_geometry_version'], auth()->id());
            return response()->json(['success' => true, 'route_variant' => $updated]);
        } catch (\App\Exceptions\GeometryConflictException $e) {
            return response()->json(['success' => false, 'conflict' => true, 'message' => $e->getMessage()], 409);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function rejectPreview(Request $request, RouteVariant $routeVariant, RouteVariantGeometryWorkflow $workflow)
    {
        $this->abortUnlessRouteGenerationMaintenanceEnabled();

        abort_unless(auth()->user()->role === 'admin', 403);
        $data = $request->validate(['session_id' => 'required|uuid']);
        $workflow->rejectPreview($data['session_id'], $routeVariant->id);
        return response()->json(['success' => true]);
    }

    public function history(RouteVariant $routeVariant)
    {
        abort_unless(auth()->user()->role === 'admin', 403);
        return response()->json(['success' => true, 'history' => $routeVariant->geometryVersions()->with('creator')->paginate(20)]);
    }

    private function abortUnlessRouteGenerationMaintenanceEnabled(): void
    {
        abort_unless((bool) config('routing.route_generation_maintenance_enabled', false), 404);
    }
}
