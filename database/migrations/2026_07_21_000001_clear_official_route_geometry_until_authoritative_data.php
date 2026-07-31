<?php

use App\Models\Route;
use App\Models\RouteGeometryVersion;
use App\Models\RouteVariant;
use App\Models\RouteVariantGeometryVersion;
use App\Models\RouteVariantStop;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const OFFICIAL_ROUTE_NAMES = ['Route 1', 'Route 2', 'Route 3'];

    public function up(): void
    {
        $routes = Route::whereIn('name', self::OFFICIAL_ROUTE_NAMES)->get();
        $routeIds = $routes->modelKeys();
        if (empty($routeIds)) {
            return;
        }

        $variantIds = RouteVariant::whereIn('route_id', $routeIds)->pluck('id');

        // Official coordinates and geometry remain unavailable until the beneficiary dataset arrives.
        RouteVariantStop::whereIn('route_variant_id', $variantIds)->update([
            'canonical_stop_id' => null,
            'lat' => null,
            'lng' => null,
            'coordinate_status' => 'pending',
            'coordinate_source' => null,
            'coordinates_verified_at' => null,
            'coordinates_verified_by_user_id' => null,
            'coordinate_notes' => null,
        ]);

        RouteVariant::whereIn('id', $variantIds)->update([
            'polyline_coordinates' => [],
            'geometry_version' => 0,
            'geometry_status' => 'pending',
        ]);

        // Remove only official-route geometry history so stale provisional geometry cannot be restored.
        RouteVariantGeometryVersion::whereIn('route_variant_id', $variantIds)->delete();
        RouteGeometryVersion::whereIn('route_id', $routeIds)->delete();
        Route::whereIn('id', $routeIds)->update([
            'polyline_coordinates' => [],
            'geometry_version' => 0,
        ]);
    }

    public function down(): void
    {
        // Official geometry is beneficiary-supplied data and cannot be reconstructed by rollback.
    }
};