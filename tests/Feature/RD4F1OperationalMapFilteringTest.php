<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\Trip;
use App\Services\Routing\AuthoritativeRouteResolver;
use Database\Seeders\OfficialPasigRouteSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RD4F1OperationalMapFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_fleet_operational_map_payloads_include_only_canonical_routes(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);

        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $fleet = \App\Models\User::factory()->create(['role' => 'fleet_manager']);

        $adminRoutes = collect($this->actingAs($admin)->getJson('/admin/api/fleet-data')
            ->assertOk()->json('routes'));
        $fleetRoutes = collect($this->actingAs($fleet)->getJson('/fleet/api/overview-data')
            ->assertOk()->json('routes'));

        foreach ([$adminRoutes, $fleetRoutes] as $routes) {
            $this->assertSame(['Route 2', 'Route 3', 'Route 4'], $routes->pluck('name')->values()->all());
            $this->assertCount(6, $routes->flatMap(fn (array $route) => $route['map_variant_geometries'] ?? []));
        }

        $this->assertSame(
            $adminRoutes->pluck('name')->values()->all(),
            $fleetRoutes->pluck('name')->values()->all()
        );
    }

    public function test_legacy_route_geometry_and_historical_resolution_remain_available(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);

        $legacy = Route::where('name', 'Route C')->firstOrFail();
        $originalGeometry = $legacy->polyline_coordinates;
        $trip = Trip::factory()->create([
            'route_id' => $legacy->id,
            'route_variant_id' => null,
        ]);

        $plan = app(AuthoritativeRouteResolver::class)->resolveForTrip($trip);

        $this->assertSame('legacy_route', $plan->source);
        $this->assertSame($legacy->id, $plan->route->id);
        $this->assertSame($originalGeometry, $legacy->fresh()->polyline_coordinates);
    }
}
