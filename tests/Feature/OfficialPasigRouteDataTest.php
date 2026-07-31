<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Trip;
use App\Models\User;
use App\Models\VehiclePosition;
use App\Services\RouteVariantSelectionService;
use App\Services\Routing\AuthoritativeRouteResolver;
use Database\Seeders\OfficialPasigRouteSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfficialPasigRouteDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_routes_have_outbound_and_inbound_variants(): void
    {
        $this->seedOfficialRoutes();

        foreach (['Route 1', 'Route 2', 'Route 3'] as $routeName) {
            $route = Route::where('name', $routeName)->firstOrFail();
            $this->assertTrue($route->variants()->where('direction', 'outbound')->exists(), $routeName . ' outbound missing');
            $this->assertTrue($route->variants()->where('direction', 'inbound')->exists(), $routeName . ' inbound missing');
        }
    }

    public function test_official_directional_stop_sequences_are_independent(): void
    {
        $this->seedOfficialRoutes();

        $expectations = [
            'Route 1' => ['outbound_first' => 'SPED (Caruncho Ave.)', 'outbound_last' => 'Ligaya (Puregold)', 'inbound_first' => 'Ligaya (Puregold)', 'inbound_last' => 'SPED (Caruncho Ave.)'],
            'Route 2' => ['outbound_first' => 'SPED (Caruncho Ave.)', 'outbound_last' => 'Kenneth Road', 'inbound_first' => 'Kenneth Road', 'inbound_last' => 'SPED (Caruncho Ave.)'],
            'Route 3' => ['outbound_first' => 'SPED (Caruncho Ave.)', 'outbound_last' => 'One San Miguel', 'inbound_first' => 'One San Miguel', 'inbound_last' => 'SPED (Caruncho Ave.)'],
        ];

        foreach ($expectations as $routeName => $expected) {
            $route = Route::where('name', $routeName)->firstOrFail();
            $outbound = $route->variants()->where('direction', 'outbound')->with('stops')->firstOrFail();
            $inbound = $route->variants()->where('direction', 'inbound')->with('stops')->firstOrFail();

            $this->assertSame($expected['outbound_first'], $outbound->stops->first()->name);
            $this->assertSame($expected['outbound_last'], $outbound->stops->last()->name);
            $this->assertSame($expected['inbound_first'], $inbound->stops->first()->name);
            $this->assertSame($expected['inbound_last'], $inbound->stops->last()->name);
            $this->assertNotSame($outbound->stops->pluck('name')->all(), $inbound->stops->pluck('name')->all());
        }
    }

    public function test_official_stop_classifications_are_persisted_and_directional(): void
    {
        $this->seedOfficialRoutes();

        $route1 = Route::where('name', 'Route 1')->firstOrFail();
        $outbound = $route1->variants()->where('direction', 'outbound')->with('stops')->firstOrFail();
        $inbound = $route1->variants()->where('direction', 'inbound')->with('stops')->firstOrFail();

        $this->assertSame('pickup_point', $outbound->stops->firstWhere('name', 'SPED (Caruncho Ave.)')->stop_type);
        $this->assertSame('designated_stop', $outbound->stops->firstWhere('name', 'Ligaya (Puregold)')->stop_type);
        $this->assertSame('pickup_point', $inbound->stops->firstWhere('name', 'Ligaya (Puregold)')->stop_type);
        $this->assertSame('designated_stop', $inbound->stops->firstWhere('name', 'SPED (Caruncho Ave.)')->stop_type);
    }

    public function test_pickup_points_and_designated_stops_remain_ordered_route_variant_stops(): void
    {
        $this->seedOfficialRoutes();

        $variant = Route::where('name', 'Route 3')->firstOrFail()
            ->variants()->where('direction', 'outbound')->with('stops')->firstOrFail();

        $this->assertSame(range(1, $variant->stops->count()), $variant->stops->pluck('sequence')->all());
        $this->assertContains('pickup_point', $variant->stops->pluck('stop_type')->all());
        $this->assertContains('designated_stop', $variant->stops->pluck('stop_type')->all());
    }

    public function test_route_2_inbound_pending_geometry_cannot_dispatch(): void
    {
        $this->seedOfficialRoutes();

        $route = Route::where('name', 'Route 2')->firstOrFail();
        $inbound = $route->variants()->where('direction', 'inbound')->firstOrFail();

        $this->assertSame('pending', $inbound->geometry_status);
        $this->expectException(ValidationException::class);

        app(RouteVariantSelectionService::class)->resolveForDispatch($route, $inbound->id);
    }

    public function test_variant_assigned_trip_uses_route_variant_geometry_and_legacy_trip_falls_back(): void
    {
        $this->seedOfficialRoutes();

        $officialRoute = Route::where('name', 'Route 1')->firstOrFail();
        $variant = $officialRoute->variants()->where('direction', 'outbound')->firstOrFail();
        $variantTrip = Trip::factory()->create([
            'route_id' => $officialRoute->id,
            'route_variant_id' => $variant->id,
        ]);

        $variantPlan = app(AuthoritativeRouteResolver::class)->resolveForTrip($variantTrip);
        $this->assertSame('route_variant', $variantPlan->source);
        $this->assertSame($variant->id, $variantPlan->variant->id);
        $this->assertSame($variant->polyline_coordinates, $variantPlan->polylineCoordinates);
        $this->assertSame('pickup_point', $variantPlan->orderedStops->first()->stop_type);

        $legacyRoute = Route::where('name', 'Route A')->firstOrFail();
        $legacyTrip = Trip::factory()->create([
            'route_id' => $legacyRoute->id,
            'route_variant_id' => null,
        ]);

        $legacyPlan = app(AuthoritativeRouteResolver::class)->resolveForTrip($legacyTrip);
        $this->assertSame('legacy_route', $legacyPlan->source);
        $this->assertSame($legacyRoute->id, $legacyPlan->route->id);
    }

    public function test_admin_fleet_api_exposes_direction_metadata_and_keeps_marker_gps_based(): void
    {
        $this->seedOfficialRoutes();
        $admin = User::factory()->create(['role' => 'admin']);
        $route = Route::where('name', 'Route 3')->firstOrFail();
        $variant = $route->variants()->where('direction', 'outbound')->firstOrFail();
        $bus = Bus::factory()->create(['status' => 'operating', 'lat' => 14.1, 'lng' => 121.1]);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'assigned']);
        $trip = Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'status' => 'ongoing',
        ]);

        VehiclePosition::create([
            'bus_id' => $bus->id,
            'trip_id' => $trip->id,
            'lat' => 14.612345,
            'lng' => 121.123456,
            'speed' => 5,
            'status' => 'moving',
            'last_updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/api/fleet-data');

        $response->assertOk();
        $busPayload = collect($response->json('buses'))->firstWhere('id', $bus->id);
        $this->assertSame($variant->id, $busPayload['route_variant_id']);
        $this->assertSame('outbound', $busPayload['direction']);
        $this->assertEquals(14.612345, $busPayload['lat']);
        $this->assertEquals(121.123456, $busPayload['lng']);
    }

    public function test_official_route_identity_migration_preserves_legacy_route_references(): void
    {
        $this->seedOfficialRoutes();

        $legacyRoute = Route::findOrFail(1);
        $trip = Trip::factory()->create(['route_id' => $legacyRoute->id, 'route_variant_id' => null]);

        $this->assertSame('Route A', $legacyRoute->name);
        $this->assertSame($legacyRoute->id, $trip->fresh()->route_id);
        $this->assertNotNull(Route::where('name', 'Route 1')->first());
        $this->assertNotSame(Route::where('name', 'Route 1')->first()->id, $legacyRoute->id);
    }

    private function seedOfficialRoutes(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);
    }
}
