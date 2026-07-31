<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\OfficialPasigRouteSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteMapGeometryGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_map_does_not_fallback_to_legacy_geometry_for_pending_variant(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $route = Route::where('name', 'Route 2')->firstOrFail();
        $variant = $route->variants()->where('direction', 'inbound')->firstOrFail();
        $bus = Bus::factory()->create(['status' => 'operating']);
        $driver = Driver::factory()->create();
        Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'status' => 'ongoing',
        ]);

        $routePayload = collect($this->actingAs($admin)->getJson('/admin/api/fleet-data')->json('routes'))
            ->firstWhere('id', $route->id);

        $this->assertSame('route_variant', $routePayload['map_geometry_source']);
        $this->assertSame('pending', $routePayload['map_geometry_status']);
        $this->assertSame([], $routePayload['polyline_coordinates']);
        $this->assertSame([], $routePayload['map_variant_geometries'][0]['polyline_coordinates']);
    }
}
