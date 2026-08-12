<?php

namespace Tests\Feature;

use App\Enums\GeofenceType;
use App\Models\Geofence;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\User;
use App\Services\RouteMapGeometryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialStopGeofenceMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_stop_payload_contains_radius_meters_and_route_one_outbound_count(): void
    {
        [$route, $outbound] = $this->createOfficialRouteOneFixture();

        $payload = app(RouteMapGeometryService::class)->forRoute($route, collect());
        $outboundGeometry = collect($payload['variant_geometries'])
            ->firstWhere('route_variant_id', $outbound->id);

        $this->assertSame('route_variant', $payload['source']);
        $this->assertCount(21, $outboundGeometry['stops']);
        $this->assertSame(21, collect($outboundGeometry['stops'])->where('radius_meters', 35)->count());
        $this->assertSame(['id', 'name', 'lat', 'lng', 'sequence', 'stop_type', 'radius_meters'], array_keys($outboundGeometry['stops'][0]));
    }

    public function test_exact_variant_stop_geofence_filtering_uses_only_selected_direction(): void
    {
        [, $outbound, $inbound] = $this->createOfficialRouteOneFixture();

        $outboundStops = RouteVariantStop::where('route_variant_id', $outbound->id)->orderBy('sequence')->get();
        $inboundStops = RouteVariantStop::where('route_variant_id', $inbound->id)->orderBy('sequence')->get();

        $this->assertCount(21, $outboundStops);
        $this->assertCount(18, $inboundStops);
        $this->assertSame(21, $outbound->stops()->count());
        $this->assertFalse($outboundStops->pluck('id')->intersect($inboundStops->pluck('id'))->isNotEmpty());
    }

    public function test_fleet_operational_payload_excludes_legacy_stop_and_terminal_geofences_but_preserves_depot(): void
    {
        $this->createOfficialRouteOneFixture();

        Geofence::create([
            'name' => 'Route A Legacy Stop',
            'type' => GeofenceType::STOP,
            'radius' => 100,
            'geometry' => ['type' => 'Point'],
            'lat' => 14.5593,
            'lng' => 121.0805,
            'status' => 'active',
        ]);
        Geofence::create([
            'name' => 'Route D Legacy Terminal',
            'type' => GeofenceType::TERMINAL,
            'radius' => 120,
            'geometry' => ['type' => 'Point'],
            'lat' => 14.5500,
            'lng' => 121.0500,
            'status' => 'active',
        ]);
        Geofence::create([
            'name' => 'Pasig Central Depot',
            'type' => GeofenceType::DEPOT,
            'radius' => 60,
            'geometry' => ['type' => 'Point'],
            'lat' => 14.5670,
            'lng' => 121.0600,
            'status' => 'active',
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'fleet_manager']))
            ->getJson(route('fleet.api.bus-gps-positions'));

        $response->assertOk();
        $geofences = collect($response->json('geofences'));

        $this->assertSame(['Pasig Central Depot'], $geofences->pluck('name')->values()->all());
        $this->assertSame(['DEPOT'], $geofences->pluck('type')->values()->all());
        $this->assertSame(3, Geofence::where('status', 'active')->count());
    }

    public function test_route_map_ux_draws_official_stop_geofence_circles_with_shared_visibility_lifecycle(): void
    {
        $helper = file_get_contents(public_path('js/route-map-ux.js'));

        $this->assertStringContainsString('state.stopGeofences', $helper);
        $this->assertStringContainsString('L.circle([stop.lat, stop.lng]', $helper);
        $this->assertStringContainsString('visibleMemberships = stop.memberships.filter', $helper);
        $this->assertStringContainsString('directionVisible(state, item.direction)', $helper);
        $this->assertStringContainsString('_normalizedStops: normalizedStops', $helper);
    }

    public function test_duplicate_coordinate_stops_remain_distinct_in_payload_and_grouped_in_renderer(): void
    {
        [$route, $outbound] = $this->createOfficialRouteOneFixture(withDuplicateCoordinates: true);

        $payload = app(RouteMapGeometryService::class)->forRoute($route, collect());
        $outboundStops = collect($payload['variant_geometries'])
            ->firstWhere('route_variant_id', $outbound->id)['stops'];

        $duplicates = collect($outboundStops)
            ->where('lat', 14.5602)
            ->where('lng', 121.0802)
            ->values();

        $this->assertCount(2, $duplicates);
        $this->assertSame(['Kapasigan 1', 'Kapasigan 2'], $duplicates->pluck('name')->all());
        $this->assertStringContainsString('const key = `${lat.toFixed(7)},${lng.toFixed(7)}`;', file_get_contents(public_path('js/route-map-ux.js')));
    }

    private function createOfficialRouteOneFixture(bool $withDuplicateCoordinates = false): array
    {
        $route = Route::factory()->create([
            'name' => 'Route 2',
            'status' => 'Active',
            'polyline_coordinates' => [],
        ]);

        $outbound = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'outbound',
            'origin_name' => 'SPED',
            'destination_name' => 'Ligaya',
            'polyline_coordinates' => [[14.5600, 121.0800], [14.5620, 121.0820]],
            'geometry_version' => 1,
            'geometry_status' => 'schematic',
            'is_default' => true,
        ]);

        $inbound = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'inbound',
            'origin_name' => 'Ligaya',
            'destination_name' => 'SPED',
            'polyline_coordinates' => [[14.5620, 121.0820], [14.5600, 121.0800]],
            'geometry_version' => 1,
            'geometry_status' => 'schematic',
            'is_default' => false,
        ]);

        for ($i = 1; $i <= 21; $i++) {
            $lat = round(14.5600 + ($i * 0.0001), 7);
            $lng = round(121.0800 + ($i * 0.0001), 7);
            $name = "Route 2 OUT Stop {$i}";

            if ($withDuplicateCoordinates && in_array($i, [2, 3], true)) {
                $lat = 14.5602;
                $lng = 121.0802;
                $name = $i === 2 ? 'Kapasigan 1' : 'Kapasigan 2';
            }

            RouteVariantStop::create([
                'route_variant_id' => $outbound->id,
                'name' => $name,
                'lat' => $lat,
                'lng' => $lng,
                'radius_meters' => 35,
                'sequence' => $i,
                'stop_type' => $i === 1 ? 'pickup_point' : 'designated_stop',
            ]);
        }

        for ($i = 1; $i <= 18; $i++) {
            RouteVariantStop::create([
                'route_variant_id' => $inbound->id,
                'name' => "Route 2 IN Stop {$i}",
                'lat' => round(14.5620 - ($i * 0.0001), 7),
                'lng' => round(121.0820 - ($i * 0.0001), 7),
                'radius_meters' => 35,
                'sequence' => $i,
                'stop_type' => $i === 1 ? 'pickup_point' : 'designated_stop',
            ]);
        }

        return [$route, $outbound, $inbound];
    }
}
