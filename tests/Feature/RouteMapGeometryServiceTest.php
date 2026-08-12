<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Services\RouteMapGeometryService;
use App\Services\RouteVariantSelectionService;
use Database\Seeders\OfficialPasigRouteSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RouteMapGeometryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function prepareOfficialVariants(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);

        RouteVariant::query()->whereHas('route', fn ($query) => $query->whereIn('name', Route::canonicalProductionNames()))
            ->with('stops')
            ->get()
            ->each(function (RouteVariant $variant, int $variantIndex): void {
                $variant->stops->each(function ($stop, int $stopIndex): void {
                    $stop->update([
                        'lat' => 14.55 + ($stopIndex * 0.001),
                        'lng' => 121.07 + ($stopIndex * 0.001),
                    ]);
                });
                $variant->update([
                    'polyline_coordinates' => [[14.55, 121.07], [14.56, 121.08]],
                    'geometry_status' => 'schematic',
                ]);
            });
    }

    public function test_operational_geometry_rejects_schematic_but_official_selection_accepts_it(): void
    {
        $this->prepareOfficialVariants();
        $variant = RouteVariant::where('geometry_status', 'schematic')->firstOrFail();

        $this->assertSame([], app(RouteMapGeometryService::class)->operationalPolyline($variant));
        $this->assertTrue(app(RouteVariantSelectionService::class)->isUsableForLiveDispatch($variant));
    }

    public function test_map_geometry_accepts_schematic(): void
    {
        $this->prepareOfficialVariants();
        $variant = RouteVariant::where('geometry_status', 'schematic')->firstOrFail();

        $this->assertSame([[14.55, 121.07], [14.56, 121.08]], app(RouteMapGeometryService::class)->mapPolyline($variant));
    }

    public function test_canonical_map_payload_contains_all_official_variants_without_active_trips(): void
    {
        $this->prepareOfficialVariants();
        $service = app(RouteMapGeometryService::class);

        foreach (Route::canonicalProductionNames() as $name) {
            $payload = $service->forRoute(Route::where('name', $name)->firstOrFail(), collect());
            $this->assertSame('route_variant', $payload['source']);
            $this->assertSame('available', $payload['geometry_status']);
            $this->assertCount(2, $payload['variant_geometries']);
            $this->assertCount(2, $payload['route_variant_ids']);
            $this->assertSame([], $payload['polyline_coordinates']);
            $this->assertTrue(collect($payload['variant_geometries'])->every(fn (array $geometry) => $geometry['geometry_status'] === 'schematic' && count($geometry['polyline_coordinates']) === 2));
        }
    }

    private function seedOfficialRoutes(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);
        $this->replaceRoute2WithImportedStops();
    }

    private function replaceRoute2WithImportedStops(): void
    {
        $route = Route::where('name', 'Route 2')->firstOrFail();
        $outbound = RouteVariant::where('route_id', $route->id)->where('direction', 'outbound')->firstOrFail();
        $inbound = RouteVariant::where('route_id', $route->id)->where('direction', 'inbound')->firstOrFail();

        $outboundStops = [
            ['SPED', 14.5602934, 121.0797616],
            ['Caruncho (Dunkin)', 14.5609158, 121.0777842],
            ['Kapasigan 1 (Landbank)', 14.5619548, 121.0767738],
            ['Kapasigan 2 (after Meralco)', 14.5619548, 121.0767738],
            ['Rotonda', 14.5663005, 121.0770961],
            ['Caniogan 1 (Eastern Police)', 14.5670804, 121.0763202],
            ['Caniogan 2 (Rizal High)', 14.5672658, 121.0763419],
            ['Caniogan (Pag-Asa St)', 14.5737193, 121.0786707],
            ['Stella Maris', 14.5783717, 121.0817549],
            ['Sandoval Bridge', 14.5812031, 121.0833961],
            ['Bernal 1', 14.5862581, 121.0844792],
            ['Bernal 2', 14.5856107, 121.0881917],
            ['Tramo', 14.5902478, 121.0895786],
            ["Jenny's", 14.5899974, 121.0913753],
            ['Amang Road', 14.5965974, 121.0903844],
            ['Manggahan (Brgy. Hall - Petron Mangahan)', 14.5996806, 121.091594],
            ['Mabini (Pasig Doctors)', 14.60094, 121.0920859],
            ['Magsaysay (Simbahan)', 14.6033923, 121.0921975],
            ['Santolan 1 (Green Park Vill)', 14.6069666, 121.0922965],
            ['Santolan 2 (tapat ng Jollibee)', 14.6096595, 121.0919772],
            ['Ligaya (Puregold)', 14.6185612, 121.0925442],
        ];
        $inboundStops = [
            ['Ligaya (Puregold)', 14.6185612, 121.0925442],
            ['Santolan 2 (tapat ng Jollibee)', 14.6096595, 121.0919772],
            ['Santolan 1 (Green Park Vill)', 14.6069666, 121.0922965],
            ['Magsaysay (Simbahan)', 14.6033923, 121.0921975],
            ['Mabini (Pasig Doctors)', 14.60094, 121.0920859],
            ['Manggahan (Brgy. Hall - Petron Mangahan)', 14.5996806, 121.091594],
            ['Amang Road', 14.5965974, 121.0903844],
            ['Tramo', 14.5902478, 121.0895786],
            ['Bernal 2', 14.5856107, 121.0881917],
            ['Bernal 1', 14.5862581, 121.0844792],
            ['Sandoval Bridge', 14.5812031, 121.0833961],
            ['Stella Maris', 14.5783717, 121.0817549],
            ['Caniogan (Pag-Asa St)', 14.5737193, 121.0786707],
            ['Rotonda', 14.5663005, 121.0770961],
            ['Kapasigan 2 (after Meralco)', 14.5619548, 121.0767738],
            ['Kapasigan 1 (Landbank)', 14.5619548, 121.0767738],
            ['Caruncho (Dunkin)', 14.5609158, 121.0777842],
            ['SPED', 14.5602934, 121.0797616],
        ];

        foreach ([[$outbound, $outboundStops], [$inbound, $inboundStops]] as [$variant, $stops]) {
            RouteVariantStop::where('route_variant_id', $variant->id)->delete();
            foreach ($stops as $index => [$name, $lat, $lng]) {
                RouteVariantStop::create([
                    'route_variant_id' => $variant->id,
                    'name' => $name,
                    'lat' => $lat,
                    'lng' => $lng,
                    'sequence' => $index + 1,
                    'stop_type' => ($index === 0 || $index === count($stops) - 1) ? 'pickup_point' : 'designated_stop',
                    'radius_meters' => 100,
                    'coordinate_status' => 'verified',
                    'coordinate_source' => 'official beneficiary workbook',
                ]);
            }
        }
    }

    public function test_canonical_map_payload_includes_ordered_route_variant_stops(): void
    {
        $this->seedOfficialRoutes();
        $service = app(RouteMapGeometryService::class);
        $route = Route::where('name', 'Route 2')->firstOrFail();

        $payload = $service->forRoute($route, collect());
        $geometries = collect($payload['variant_geometries']);
        $outbound = $geometries->firstWhere('direction', 'outbound');
        $inbound = $geometries->firstWhere('direction', 'inbound');

        $this->assertTrue($geometries->every(fn (array $geometry) => array_key_exists('stops', $geometry) && is_array($geometry['stops'])));
        $this->assertCount(21, $outbound['stops']);
        $this->assertCount(18, $inbound['stops']);

        $this->assertSame('SPED', $outbound['stops'][0]['name']);
        $this->assertSame(1, $outbound['stops'][0]['sequence']);
        $this->assertSame('Ligaya (Puregold)', $outbound['stops'][20]['name']);
        $this->assertSame(21, $outbound['stops'][20]['sequence']);

        $this->assertSame('Ligaya (Puregold)', $inbound['stops'][0]['name']);
        $this->assertSame(1, $inbound['stops'][0]['sequence']);
        $this->assertSame('SPED', $inbound['stops'][17]['name']);
        $this->assertSame(18, $inbound['stops'][17]['sequence']);
    }

    public function test_map_stop_payload_preserves_required_fields_and_duplicate_kapasigan_stops(): void
    {
        $this->seedOfficialRoutes();
        $route = Route::where('name', 'Route 2')->firstOrFail();

        $payload = app(RouteMapGeometryService::class)->forRoute($route, collect());
        $outboundStops = collect($payload['variant_geometries'])
            ->firstWhere('direction', 'outbound')['stops'];

        foreach ($outboundStops as $stop) {
            foreach (['id', 'name', 'lat', 'lng', 'sequence', 'stop_type', 'radius_meters'] as $key) {
                $this->assertArrayHasKey($key, $stop);
            }
            $this->assertIsNumeric($stop['lat']);
            $this->assertIsNumeric($stop['lng']);
        }

        $kapasiganStops = collect($outboundStops)->filter(fn (array $stop) => str_starts_with($stop['name'], 'Kapasigan'));
        $this->assertCount(2, $kapasiganStops);
        $this->assertSame(['Kapasigan 1 (Landbank)', 'Kapasigan 2 (after Meralco)'], $kapasiganStops->pluck('name')->values()->all());
        $this->assertSame(3, $kapasiganStops->first()['sequence']);
        $this->assertSame(4, $kapasiganStops->last()['sequence']);
        $this->assertSame($kapasiganStops->first()['lat'], $kapasiganStops->last()['lat']);
        $this->assertSame($kapasiganStops->first()['lng'], $kapasiganStops->last()['lng']);
    }

    public function test_official_map_stops_are_available_without_active_trips_and_use_single_stop_query(): void
    {
        $this->seedOfficialRoutes();
        $route = Route::where('name', 'Route 2')->firstOrFail();
        $service = app(RouteMapGeometryService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $payload = $service->forRoute($route, collect());
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame('route_variant', $payload['source']);
        $this->assertCount(2, $payload['variant_geometries']);
        $this->assertTrue(collect($payload['variant_geometries'])->every(fn (array $geometry) => count($geometry['stops']) > 0));

        $stopQueries = collect($queries)->filter(fn (array $query) => str_contains($query['query'], 'route_variant_stops'));
        $this->assertCount(1, $stopQueries);
    }
    public function test_legacy_route_payload_remains_unchanged(): void
    {
        $this->prepareOfficialVariants();
        $legacy = Route::whereNotIn('name', Route::canonicalProductionNames())->firstOrFail();
        $legacyGeometry = [[14.1, 121.1], [14.2, 121.2]];
        $legacy->update(['polyline_coordinates' => $legacyGeometry]);

        $payload = app(RouteMapGeometryService::class)->forRoute($legacy->fresh(), collect());
        $this->assertSame('legacy_route', $payload['source']);
        $this->assertSame('legacy', $payload['geometry_status']);
        $this->assertSame($legacyGeometry, $payload['polyline_coordinates']);
        $this->assertSame([], $payload['variant_geometries']);
    }
}