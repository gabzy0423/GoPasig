<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Route;
use App\Models\RouteCorridor;
use App\Models\RouteVariant;
use App\Models\RouteVariantCorridor;
use App\Models\Trip;
use App\Models\VehiclePosition;
use App\Services\RouteVariantCorridorGenerator;
use App\Services\Spatial\SpatialContextResolver;
use Database\Seeders\OfficialPasigRouteSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RouteVariantCorridorGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_official_route_variant_corridors_without_touching_legacy_corridors(): void
    {
        $this->prepareOfficialVariantGeometry();

        $legacyBefore = RouteCorridor::orderBy('id')->get()->map(fn (RouteCorridor $corridor) => $corridor->only([
            'id', 'route_id', 'buffer_width', 'source_type', 'measurement_method', 'geometry',
        ]))->values()->all();

        $exitCode = Artisan::call('gopasig:generate-route-variant-corridors', ['--apply' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertCount(6, RouteVariantCorridor::all());
        $this->assertSame($legacyBefore, RouteCorridor::orderBy('id')->get()->map(fn (RouteCorridor $corridor) => $corridor->only([
            'id', 'route_id', 'buffer_width', 'source_type', 'measurement_method', 'geometry',
        ]))->values()->all());

        $variants = $this->officialVariants();
        foreach ($variants as $variant) {
            $corridor = RouteVariantCorridor::where('route_variant_id', $variant->id)->firstOrFail();
            $coordinates = $variant->polyline_coordinates;

            $this->assertSame('LineString', $corridor->geometry['type']);
            $this->assertSame('lat_lng', $corridor->geometry['coordinate_order']);
            $this->assertSame($coordinates, $corridor->geometry['coordinates']);
            $this->assertSame(count($coordinates), $corridor->coordinate_count);
            $this->assertSame(hash('sha256', json_encode($coordinates, JSON_PRESERVE_ZERO_FRACTION)), $corridor->geometry_hash);
            $this->assertSame(RouteVariantCorridorGenerator::GENERATION_SOURCE, $corridor->generation_source);
            $this->assertNotNull($corridor->generated_at);
            $this->assertSame($coordinates[0], $corridor->geometry['coordinates'][0]);
            $this->assertSame($coordinates[count($coordinates) - 1], $corridor->geometry['coordinates'][$corridor->coordinate_count - 1]);
        }
    }

    public function test_rejects_invalid_official_variant_geometry_without_partial_writes(): void
    {
        $this->prepareOfficialVariantGeometry();

        $variant = $this->officialVariants()->firstOrFail();
        $variant->update(['polyline_coordinates' => [[14.5602934, 121.0797616]]]);

        $exitCode = Artisan::call('gopasig:generate-route-variant-corridors', ['--apply' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertSame(0, RouteVariantCorridor::count());
    }

    public function test_runtime_spatial_resolver_uses_generated_route_variant_corridors(): void
    {
        $this->prepareOfficialVariantGeometry();
        $this->assertSame(0, Artisan::call('gopasig:generate-route-variant-corridors', ['--apply' => true]));

        $route = Route::where('name', 'Route 2')->firstOrFail();
        $variant = RouteVariant::where('route_id', $route->id)->where('direction', 'outbound')->firstOrFail();
        $bus = Bus::factory()->create(['status' => 'active']);
        $trip = Trip::factory()->create([
            'bus_id' => $bus->id,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'status' => 'ongoing',
        ]);
        $position = VehiclePosition::create([
            'bus_id' => $bus->id,
            'trip_id' => $trip->id,
            'lat' => $variant->polyline_coordinates[0][0],
            'lng' => $variant->polyline_coordinates[0][1],
            'heading' => 0,
            'speed' => 0,
            'status' => 'Moving',
            'last_updated_at' => now(),
        ]);

        $context = app(SpatialContextResolver::class)->resolve($position);

        $this->assertInstanceOf(RouteVariantCorridor::class, $context->corridor);
        $this->assertSame('route_variant_corridor', $context->corridorSource);
        $this->assertSame($variant->id, $context->corridor->route_variant_id);
        $this->assertDatabaseHas('route_variant_corridors', ['route_variant_id' => $variant->id]);
        $this->assertDatabaseMissing('route_corridors', ['route_id' => $route->id]);
    }

    private function prepareOfficialVariantGeometry(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);

        $this->officialVariants()->values()->each(function (RouteVariant $variant, int $variantIndex): void {
            $pointCount = match ([$variant->route->name, $variant->direction]) {
                ['Route 2', 'outbound'] => 21,
                ['Route 2', 'inbound'] => 18,
                ['Route 3', 'outbound'] => 14,
                ['Route 3', 'inbound'] => 15,
                ['Route 4', 'outbound'] => 8,
                ['Route 4', 'inbound'] => 9,
            };

            $coordinates = [];
            for ($i = 0; $i < $pointCount; $i++) {
                $coordinates[] = [
                    round(14.54 + ($variantIndex * 0.01) + ($i * 0.0001), 7),
                    round(121.05 + ($variantIndex * 0.01) + ($i * 0.0001), 7),
                ];
            }

            if ($variant->route->name === 'Route 2' && $variant->direction === 'outbound') {
                $coordinates[3] = $coordinates[2];
            }

            $variant->update([
                'polyline_coordinates' => $coordinates,
                'geometry_status' => 'schematic',
            ]);
        });
    }

    private function officialVariants()
    {
        return RouteVariant::query()
            ->whereHas('route', fn ($query) => $query->whereIn('name', Route::canonicalProductionNames()))
            ->with('route')
            ->join('routes', 'routes.id', '=', 'route_variants.route_id')
            ->orderBy('routes.id')
            ->orderByRaw("case route_variants.direction when 'outbound' then 0 else 1 end")
            ->select('route_variants.*')
            ->get();
    }
}
