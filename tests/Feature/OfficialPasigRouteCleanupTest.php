<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\User;
use App\Services\RouteMapGeometryService;
use Database\Seeders\OfficialPasigRouteSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialPasigRouteCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoutes(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);
    }

    public function test_six_official_variants_and_fixed_stop_definitions_remain_present(): void
    {
        $this->seedRoutes();

        $routes = Route::whereIn('name', ['Route 1', 'Route 2', 'Route 3'])->with('variants.stops')->get();
        $this->assertCount(3, $routes);
        $this->assertSame(6, $routes->pluck('variants')->flatten()->count());
        $this->assertSame(82, $routes->pluck('variants')->flatten()->pluck('stops')->flatten()->count());
        $this->assertSame(82, $routes->pluck('variants')->flatten()->pluck('stops')->flatten()->filter(fn ($stop) => in_array($stop->stop_type, ['pickup_point', 'designated_stop'], true))->count());
        $this->assertSame(20, $routes->firstWhere('name', 'Route 1')->variants->firstWhere('direction', 'outbound')->stops->count());
        $this->assertSame(18, $routes->firstWhere('name', 'Route 1')->variants->firstWhere('direction', 'inbound')->stops->count());
    }

    public function test_official_coordinates_geometry_and_history_are_empty_and_pending(): void
    {
        $this->seedRoutes();

        $official = Route::whereIn('name', ['Route 1', 'Route 2', 'Route 3'])->with('variants.stops')->get();
        foreach ($official as $route) {
            $this->assertEmpty($route->polyline_coordinates);
            foreach ($route->variants as $variant) {
                $this->assertSame('pending', $variant->geometry_status);
                $this->assertSame(0, $variant->geometry_version);
                $this->assertEmpty($variant->polyline_coordinates);
                foreach ($variant->stops as $stop) {
                    $this->assertNull($stop->lat);
                    $this->assertNull($stop->lng);
                    $this->assertSame('pending', $stop->coordinate_status);
                    $this->assertNull($stop->coordinate_source);
                    $this->assertNull($stop->coordinates_verified_at);
                    $this->assertNull($stop->coordinates_verified_by_user_id);
                    $this->assertNull($stop->coordinate_notes);
                }
            }

            $map = app(RouteMapGeometryService::class)->forRoute($route, collect());
            $this->assertSame('route_variant', $map['source']);
            $this->assertSame('pending', $map['geometry_status']);
            $this->assertSame([], $map['polyline_coordinates']);
        }
    }

    public function test_legacy_routes_remain_usable_and_official_definitions_are_protected(): void
    {
        $this->seedRoutes();
        $admin = User::factory()->create(['role' => 'admin']);
        $official = Route::where('name', 'Route 1')->firstOrFail();

        $this->assertGreaterThan(0, Route::whereIn('name', ['Route A', 'Route B', 'Route C', 'Route D'])->withCount('stops')->get()->sum('stops_count'));
        $this->assertGreaterThan(0, Route::whereIn('name', ['Route A', 'Route B', 'Route C', 'Route D'])->get()->sum(fn ($route) => count($route->polyline_coordinates ?: [])));

        $this->actingAs($admin)->putJson('/admin/api/routes/' . $official->id, [
            'description' => 'Changed official identity',
        ])->assertStatus(422);

        $this->actingAs($admin)->postJson('/admin/api/stops', [
            'route_id' => $official->id,
            'name' => 'Unauthorized official stop',
        ])->assertStatus(422);

        $officialRouteIds = Route::whereIn('name', ['Route 1', 'Route 2', 'Route 3'])->pluck('id');
        $this->assertSame(82, RouteVariantStop::whereIn('route_variant_id', RouteVariant::whereIn('route_id', $officialRouteIds)->pluck('id'))->count());
    }
}