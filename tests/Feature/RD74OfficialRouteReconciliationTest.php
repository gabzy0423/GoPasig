<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantCorridor;
use App\Models\RouteVariantStop;
use App\Models\Trip;
use App\Models\User;
use App\Services\RouteVariantSelectionService;
use App\Services\TripService;
use Database\Seeders\OfficialPasigRouteSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RD74OfficialRouteReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_controlled_rollback_removes_bridgetown_and_keeps_route_two_to_four(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seedOldOfficialRoutes();
        $this->runMigration('2026_07_30_000001_reconcile_official_route_1_4_identity.php');

        $routeOne = Route::where('name', 'Route 1')->where('description', 'like', '%Temporary Pasig City Hall%')->firstOrFail();
        $variantIds = $routeOne->variants()->pluck('id')->all();

        $this->runMigration('2026_08_06_000001_remove_bridgetown_from_operational_runtime.php');
        $this->runMigration('2026_08_06_000001_remove_bridgetown_from_operational_runtime.php');

        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], Route::canonicalProductionNames());
        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], Route::getCanonicalProductionCached()->pluck('name')->values()->all());
        $this->assertDatabaseMissing('routes', ['id' => $routeOne->id]);
        $this->assertSame(0, RouteVariant::where('route_id', $routeOne->id)->count());
        $this->assertSame(0, RouteVariantStop::whereIn('route_variant_id', $variantIds)->count());
        $this->assertSame(0, RouteVariantCorridor::whereIn('route_variant_id', $variantIds)->count());

        $this->assertSame('Route 2', Route::findOrFail(5)->name);
        $this->assertStringContainsString('Ligaya', Route::findOrFail(5)->description);
        $this->assertSame('Route 4', Route::findOrFail(6)->name);
        $this->assertStringContainsString('Nagpayong', Route::findOrFail(6)->description);
        $this->assertSame('Route 3', Route::findOrFail(7)->name);

        $this->assertSame('Route A', Route::findOrFail(1)->name);
        $historicalTrip = Trip::factory()->create(['route_id' => 1, 'route_variant_id' => null]);
        $this->assertSame(1, $historicalTrip->fresh()->route_id);
    }

    public function test_rollback_aborts_when_bridgetown_has_unexpected_trip_reference(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seedOldOfficialRoutes();
        $this->runMigration('2026_07_30_000001_reconcile_official_route_1_4_identity.php');

        $routeOne = Route::where('name', 'Route 1')->where('description', 'like', '%Temporary Pasig City Hall%')->firstOrFail();
        $variant = $routeOne->variants()->firstOrFail();
        Trip::factory()->create(['route_id' => $routeOne->id, 'route_variant_id' => $variant->id]);

        $this->expectException(\RuntimeException::class);
        $this->runMigration('2026_08_06_000001_remove_bridgetown_from_operational_runtime.php');
    }

    public function test_official_routes_are_route_two_to_four_payload_visible_and_dispatchable(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);

        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], Route::canonicalProductionNames());
        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], Route::getCanonicalProductionCached()->pluck('name')->values()->all());
        $this->assertFalse(Route::getCanonicalProductionCached()->contains('name', 'Route 1'));

        foreach (Route::getCanonicalProductionCached() as $route) {
            $variant = $route->variants()->where('direction', 'outbound')->with('stops')->firstOrFail();
            $this->makeDispatchable($variant);
            $this->assertTrue(app(RouteVariantSelectionService::class)->isUsableForLiveDispatch($variant->fresh('stops')));

            $trip = TripService::startTrip(Bus::factory()->create(), Driver::factory()->create(), $route, 0, $variant->fresh('stops'));
            $this->assertSame($variant->id, $trip->route_variant_id);
        }

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/admin/api/fleet-data');
        $response->assertOk();
        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], collect($response->json('routes'))->pluck('name')->values()->all());
        $this->assertNull(collect($response->json('routes'))->firstWhere('name', 'Route 1'));
        $this->assertStringNotContainsString('Bridgetown', $response->getContent());
    }

    private function runMigration(string $file): void
    {
        $migration = require database_path('migrations/' . $file);
        $migration->up();
    }

    private function makeDispatchable(RouteVariant $variant): void
    {
        $coordinates = $variant->stops->values()->map(function (RouteVariantStop $stop, int $index): array {
            $lat = $stop->lat ?? 14.54 + ($index * 0.001);
            $lng = $stop->lng ?? 121.10 + ($index * 0.001);
            $stop->update(['lat' => $lat, 'lng' => $lng]);

            return [(float) $lat, (float) $lng];
        })->all();

        $variant->update(['polyline_coordinates' => $coordinates, 'geometry_status' => 'schematic']);
    }

    private function seedOldOfficialRoutes(): void
    {
        $definitions = [
            5 => ['Route 1', 'SPED (Caruncho Ave.) to Ligaya', 'Ligaya', [[14.56, 121.07], [14.61, 121.09]]],
            6 => ['Route 2', 'SPED (Caruncho Ave.) to Nagpayong', 'Nagpayong', [[14.56, 121.07], [14.54, 121.10]]],
            7 => ['Route 3', 'SPED (Caruncho Ave.) to One San Miguel Ave.', 'One San Miguel', [[14.56, 121.07], [14.57, 121.05]]],
        ];

        foreach ($definitions as $id => [$name, $description, $terminal, $outGeometry]) {
            $route = Route::create(['id' => $id, 'name' => $name, 'description' => $description, 'status' => 'Active', 'polyline_coordinates' => []]);
            foreach ([['outbound', 'SPED', $terminal, $outGeometry], ['inbound', $terminal, 'SPED', array_reverse($outGeometry)]] as [$direction, $origin, $destination, $geometry]) {
                $variant = RouteVariant::create([
                    'route_id' => $route->id,
                    'direction' => $direction,
                    'origin_name' => $origin,
                    'destination_name' => $destination,
                    'polyline_coordinates' => $geometry,
                    'geometry_status' => 'schematic',
                    'is_default' => $direction === 'outbound',
                ]);
                foreach ([[$origin, $geometry[0]], [$destination, $geometry[1]]] as $index => [$stopName, $point]) {
                    RouteVariantStop::create([
                        'route_variant_id' => $variant->id,
                        'name' => $stopName,
                        'lat' => $point[0],
                        'lng' => $point[1],
                        'sequence' => $index + 1,
                        'radius_meters' => 100,
                        'stop_type' => $index === 0 ? 'pickup_point' : 'designated_stop',
                    ]);
                }
                RouteVariantCorridor::create([
                    'route_variant_id' => $variant->id,
                    'geometry' => ['type' => 'LineString', 'coordinates' => $geometry, 'coordinate_order' => 'lat_lng'],
                    'geometry_hash' => hash('sha256', json_encode($geometry, JSON_PRESERVE_ZERO_FRACTION)),
                    'coordinate_count' => count($geometry),
                    'generated_at' => now(),
                    'generation_source' => 'route_variant.polyline_coordinates',
                ]);
            }
        }
    }
}
