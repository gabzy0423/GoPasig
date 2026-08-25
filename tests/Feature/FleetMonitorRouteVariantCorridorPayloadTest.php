<?php

namespace Tests\Feature;

use App\Models\RouteCorridor;
use App\Models\RouteVariant;
use App\Models\RouteVariantCorridor;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\OfficialPasigRouteSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetMonitorRouteVariantCorridorPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_fleet_monitor_payload_omits_corridor_runtime_data_and_preserves_stored_rows(): void
    {
        $this->prepareArchivedCorridors();
        $variant = $this->officialVariants()->firstOrFail();
        $trip = Trip::factory()->create([
            'route_id' => $variant->route_id,
            'route_variant_id' => $variant->id,
            'status' => 'ongoing',
            'started_at' => now()->subMinute(),
        ]);
        $trip->bus->update([
            'route_id' => $variant->route_id,
            'status' => 'operating',
            'lat' => 14.5602934,
            'lng' => 121.0797616,
        ]);

        $user = User::factory()->create(['role' => 'fleet_manager']);

        $response = $this->actingAs($user)->getJson(route('fleet.api.bus-gps-positions'));

        $response->assertOk();
        $response->assertJsonMissingPath('corridors');
        $response->assertJsonMissingPath('variant_corridors');
        $response->assertJsonStructure([
            'buses',
            'geofences',
        ]);

        $bus = collect($response->json('buses'))->firstWhere('trip_id', $trip->id);
        $this->assertNotNull($bus);
        $this->assertArrayNotHasKey('route_adherence', $bus);
        $this->assertArrayNotHasKey('corridor_distance', $bus);
        $this->assertSame(6, RouteVariantCorridor::count());
        $this->assertSame(3, RouteCorridor::count());
    }

    private function prepareArchivedCorridors(): void
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
                default => 2,
            };

            $coordinates = [];
            for ($i = 0; $i < $pointCount; $i++) {
                $coordinates[] = [
                    round(14.54 + ($variantIndex * 0.01) + ($i * 0.0001), 7),
                    round(121.05 + ($variantIndex * 0.01) + ($i * 0.0001), 7),
                ];
            }

            $variant->update([
                'polyline_coordinates' => $coordinates,
                'geometry_status' => 'schematic',
            ]);

            RouteVariantCorridor::create([
                'route_variant_id' => $variant->id,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => $coordinates,
                    'coordinate_order' => 'lat_lng',
                ],
                'geometry_hash' => hash('sha256', json_encode($coordinates, JSON_PRESERVE_ZERO_FRACTION)),
                'coordinate_count' => count($coordinates),
                'generated_at' => now()->subDay(),
                'generation_source' => 'archived_phase_1',
            ]);

            RouteCorridor::firstOrCreate(
                ['route_id' => $variant->route_id],
                [
                    'buffer_width' => 25.0,
                    'source_type' => 'ARCHIVED',
                    'measurement_method' => 'NEAREST_SEGMENT',
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => array_map(fn (array $point) => [$point[1], $point[0]], $coordinates),
                    ],
                ]
            );
        });

        $this->assertSame(6, RouteVariantCorridor::count());
        $this->assertSame(3, RouteCorridor::count());
    }

    private function officialVariants()
    {
        return RouteVariant::query()
            ->whereHas('route', fn ($query) => $query->whereIn('name', \App\Models\Route::canonicalProductionNames()))
            ->with('route')
            ->join('routes', 'routes.id', '=', 'route_variants.route_id')
            ->orderBy('routes.id')
            ->orderByRaw("case route_variants.direction when 'outbound' then 0 else 1 end")
            ->select('route_variants.*')
            ->get();
    }
}
