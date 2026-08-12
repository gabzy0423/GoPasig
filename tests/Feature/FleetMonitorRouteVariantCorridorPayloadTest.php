<?php

namespace Tests\Feature;

use App\Models\RouteCorridor;
use App\Models\RouteVariant;
use App\Models\RouteVariantCorridor;
use App\Models\User;
use Database\Seeders\OfficialPasigRouteSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FleetMonitorRouteVariantCorridorPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_fleet_monitor_gps_payload_returns_only_official_route_variant_corridors(): void
    {
        $this->prepareOfficialVariantCorridors();

        $user = User::factory()->create(['role' => 'fleet_manager']);

        $response = $this->actingAs($user)->getJson(route('fleet.api.bus-gps-positions'));

        $response->assertOk();
        $response->assertJsonMissingPath('corridors');
        $response->assertJsonStructure([
            'buses',
            'geofences',
            'variant_corridors' => [
                '*' => [
                    'route_variant_id',
                    'direction',
                    'geometry',
                    'geometry_hash',
                ],
            ],
        ]);

        $payloadCorridors = $response->json('variant_corridors');
        $officialVariants = $this->officialVariants();

        $this->assertCount(6, $payloadCorridors);
        $this->assertSame(
            $officialVariants->pluck('id')->values()->all(),
            collect($payloadCorridors)->pluck('route_variant_id')->values()->all()
        );
        $this->assertSame(['outbound', 'inbound', 'outbound', 'inbound', 'outbound', 'inbound'], collect($payloadCorridors)->pluck('direction')->values()->all());
        $this->assertSame(4, RouteCorridor::count());

        foreach ($payloadCorridors as $corridor) {
            $this->assertSame('LineString', $corridor['geometry']['type']);
            $this->assertSame('lat_lng', $corridor['geometry']['coordinate_order']);
            $this->assertNotEmpty($corridor['geometry_hash']);
        }
    }

    private function prepareOfficialVariantCorridors(): void
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
        });

        $this->assertSame(0, Artisan::call('gopasig:generate-route-variant-corridors', ['--apply' => true]));
        $this->assertSame(6, RouteVariantCorridor::count());
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
