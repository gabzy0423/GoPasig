<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Services\BusinessLogicService;
use Illuminate\Http\Request;

class RouteVariantStopCoordinateController extends Controller
{
    public function index(RouteVariant $routeVariant)
    {
        $this->authorizeAdmin();

        return response()->json([
            'success' => true,
            'route_variant' => [
                'id' => $routeVariant->id,
                'route_id' => $routeVariant->route_id,
                'direction' => $routeVariant->direction,
                'origin_name' => $routeVariant->origin_name,
                'destination_name' => $routeVariant->destination_name,
            ],
            'stops' => $routeVariant->stops()->orderBy('sequence')->get(),
        ]);
    }

    public function saveCandidate(Request $request, RouteVariant $routeVariant, RouteVariantStop $routeVariantStop)
    {
        $this->authorizeAdmin();
        $this->assertBelongsToVariant($routeVariant, $routeVariantStop);
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'coordinate_source' => ['nullable', 'string', 'max:100'],
            'coordinate_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $validation = BusinessLogicService::validateCoordinates((float) $data['lat'], (float) $data['lng']);
        if (!$validation['valid']) {
            return response()->json(['success' => false, 'message' => $validation['error'] ?? 'Invalid coordinates.'], 422);
        }

        $routeVariantStop->update([
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'coordinate_status' => 'candidate',
            'coordinate_source' => $data['coordinate_source'] ?? null,
            'coordinates_verified_at' => null,
            'coordinates_verified_by_user_id' => null,
            'coordinate_notes' => $data['coordinate_notes'] ?? null,
        ]);

        return response()->json(['success' => true, 'stop' => $routeVariantStop->fresh()]);
    }

    public function verify(Request $request, RouteVariant $routeVariant, RouteVariantStop $routeVariantStop)
    {
        $this->authorizeAdmin();
        $this->assertBelongsToVariant($routeVariant, $routeVariantStop);
        $data = $request->validate([
            'coordinate_source' => ['nullable', 'string', 'max:100'],
            'coordinate_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (!is_numeric($routeVariantStop->lat) || !is_numeric($routeVariantStop->lng)) {
            return response()->json(['success' => false, 'message' => 'A valid candidate coordinate must be saved before verification.'], 422);
        }
        $validation = BusinessLogicService::validateCoordinates((float) $routeVariantStop->lat, (float) $routeVariantStop->lng);
        if (!$validation['valid']) {
            return response()->json(['success' => false, 'message' => $validation['error'] ?? 'Invalid coordinates.'], 422);
        }

        $routeVariantStop->update([
            'coordinate_status' => 'verified',
            'coordinate_source' => $data['coordinate_source'] ?? $routeVariantStop->coordinate_source,
            'coordinates_verified_at' => now(),
            'coordinates_verified_by_user_id' => auth()->id(),
            'coordinate_notes' => $data['coordinate_notes'] ?? $routeVariantStop->coordinate_notes,
        ]);

        return response()->json(['success' => true, 'stop' => $routeVariantStop->fresh()]);
    }

    public function reject(Request $request, RouteVariant $routeVariant, RouteVariantStop $routeVariantStop)
    {
        $this->authorizeAdmin();
        $this->assertBelongsToVariant($routeVariant, $routeVariantStop);
        $data = $request->validate(['coordinate_notes' => ['nullable', 'string', 'max:2000']]);
        $routeVariantStop->update([
            'lat' => null,
            'lng' => null,
            'coordinate_status' => 'rejected',
            'coordinate_source' => null,
            'coordinates_verified_at' => null,
            'coordinates_verified_by_user_id' => null,
            'coordinate_notes' => $data['coordinate_notes'] ?? $routeVariantStop->coordinate_notes,
        ]);

        return response()->json(['success' => true, 'stop' => $routeVariantStop->fresh()]);
    }

    private function assertBelongsToVariant(RouteVariant $routeVariant, RouteVariantStop $routeVariantStop): void
    {
        abort_unless((int) $routeVariantStop->route_variant_id === (int) $routeVariant->id, 404);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }
}
