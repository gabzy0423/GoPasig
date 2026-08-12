<?php

namespace Tests\Feature;

use App\Events\RouteDeviationDetected;
use App\Events\RouteRecovered;
use App\Events\VehicleOnline;
use App\Models\Bus;
use App\Models\Route;
use App\Models\RouteCorridor;
use App\Models\RouteDeviation;
use App\Models\RouteVariant;
use App\Models\RouteVariantCorridor;
use App\Models\Trip;
use App\Models\TripProgress;
use App\Models\VehiclePosition;
use App\Services\Spatial\SpatialContextResolver;
use App\Services\Spatial\SpatialMonitoringEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GpsRouteVariantCorridorRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['fleet.spatial.corridor_default' => 25.0]);
    }

    public function test_official_outbound_trip_resolves_exact_outbound_route_variant_corridor(): void
    {
        $fixture = $this->createDirectionalFixture();
        $trip = $this->tripForVariant($fixture['route'], $fixture['outbound']);
        $position = $this->positionForTrip($trip, 14.0000, 121.0000);
        $position->update(['corridor_distance' => 77.7]);

        $context = app(SpatialContextResolver::class)->resolve($position);

        $this->assertSame('route_variant_corridor', $context->corridorSource);
        $this->assertInstanceOf(RouteVariantCorridor::class, $context->corridor);
        $this->assertSame($fixture['outbound_corridor']->id, $context->corridor->id);
        $this->assertSame($fixture['outbound']->id, $context->corridor->route_variant_id);
    }

    public function test_official_inbound_trip_resolves_exact_inbound_route_variant_corridor(): void
    {
        $fixture = $this->createDirectionalFixture();
        $trip = $this->tripForVariant($fixture['route'], $fixture['inbound']);
        $position = $this->positionForTrip($trip, 14.0100, 121.0000);

        $context = app(SpatialContextResolver::class)->resolve($position);

        $this->assertSame('route_variant_corridor', $context->corridorSource);
        $this->assertInstanceOf(RouteVariantCorridor::class, $context->corridor);
        $this->assertSame($fixture['inbound_corridor']->id, $context->corridor->id);
        $this->assertSame($fixture['inbound']->id, $context->corridor->route_variant_id);
    }

    public function test_official_trip_never_falls_back_to_opposite_direction_variant_corridor(): void
    {
        $fixture = $this->createDirectionalFixture();
        $fixture['outbound_corridor']->delete();

        $trip = $this->tripForVariant($fixture['route'], $fixture['outbound']);
        $position = $this->positionForTrip($trip, 14.0000, 121.0000);
        $position->update(['corridor_distance' => 77.7]);

        $context = app(SpatialContextResolver::class)->resolve($position);

        $this->assertSame('missing_variant_corridor', $context->corridorSource);
        $this->assertNull($context->corridor);
        $this->assertDatabaseHas('route_variant_corridors', ['route_variant_id' => $fixture['inbound']->id]);
    }

    public function test_legacy_historical_trip_without_variant_preserves_route_corridor_fallback(): void
    {
        $fixture = $this->createDirectionalFixture();
        $legacyCorridor = RouteCorridor::create([
            'route_id' => $fixture['route']->id,
            'buffer_width' => 30.0,
            'source_type' => 'manual',
            'measurement_method' => 'haversine',
            'geometry' => ['type' => 'LineString', 'coordinates' => [[121.0000, 14.0000], [121.0200, 14.0000]]],
        ]);
        $trip = Trip::factory()->create([
            'route_id' => $fixture['route']->id,
            'route_variant_id' => null,
            'status' => 'ongoing',
        ]);
        $position = $this->positionForTrip($trip, 14.0000, 121.0000);

        $context = app(SpatialContextResolver::class)->resolve($position);

        $this->assertSame('legacy_route_corridor', $context->corridorSource);
        $this->assertInstanceOf(RouteCorridor::class, $context->corridor);
        $this->assertSame($legacyCorridor->id, $context->corridor->id);
    }

    public function test_missing_official_variant_corridor_does_not_fall_back_to_legacy_route_geometry(): void
    {
        $fixture = $this->createDirectionalFixture();
        $fixture['outbound_corridor']->delete();
        RouteCorridor::create([
            'route_id' => $fixture['route']->id,
            'buffer_width' => 30.0,
            'source_type' => 'manual',
            'measurement_method' => 'haversine',
            'geometry' => ['type' => 'LineString', 'coordinates' => [[121.0000, 14.0000], [121.0200, 14.0000]]],
        ]);
        $trip = $this->tripForVariant($fixture['route'], $fixture['outbound']);
        $position = $this->positionForTrip($trip, 14.0000, 121.0000);
        $position->update(['corridor_distance' => 77.7]);

        $context = app(SpatialContextResolver::class)->resolve($position);
        app(SpatialMonitoringEngine::class)->process($position, $context);

        $this->assertSame('missing_variant_corridor', $context->corridorSource);
        $this->assertNull($context->corridor);
        $this->assertSame(77.7, $position->fresh()->corridor_distance);
        $this->assertSame(0, TripProgress::where('trip_id', $trip->id)->count());
    }
    public function test_corridor_distance_and_vehicle_position_output_are_preserved_with_variant_corridor(): void
    {
        Event::fake([VehicleOnline::class, RouteDeviationDetected::class, RouteRecovered::class]);
        $fixture = $this->createDirectionalFixture();
        $trip = $this->tripForVariant($fixture['route'], $fixture['outbound']);
        $position = $this->positionForTrip($trip, 14.0000, 121.0100);

        $context = app(SpatialContextResolver::class)->resolve($position);
        app(SpatialMonitoringEngine::class)->process($position, $context);

        $position->refresh();
        $this->assertSame('route_variant_corridor', $context->corridorSource);
        $this->assertSame(0.0, $position->corridor_distance);
        $this->assertSame('On Route', TripProgress::where('trip_id', $trip->id)->firstOrFail()->route_adherence);
        $this->assertDatabaseMissing('route_deviations', ['trip_id' => $trip->id]);
    }

    public function test_existing_deviation_rules_continue_with_variant_corridor_geometry(): void
    {
        Event::fake([VehicleOnline::class, RouteDeviationDetected::class, RouteRecovered::class]);
        $fixture = $this->createDirectionalFixture();
        $trip = $this->tripForVariant($fixture['route'], $fixture['outbound']);
        $position = $this->positionForTrip($trip, 14.0100, 121.0100);

        $context = app(SpatialContextResolver::class)->resolve($position);
        app(SpatialMonitoringEngine::class)->process($position, $context);

        $this->assertSame('route_variant_corridor', $context->corridorSource);
        $this->assertSame('Critical Deviation', TripProgress::where('trip_id', $trip->id)->firstOrFail()->route_adherence);
        $this->assertDatabaseHas('route_deviations', [
            'trip_id' => $trip->id,
            'severity' => 'Critical',
            'resolved_at' => null,
        ]);
        Event::assertDispatched(RouteDeviationDetected::class);
    }

    public function test_missing_variant_corridor_preserves_gps_persistence_and_legacy_data(): void
    {
        $fixture = $this->createDirectionalFixture();
        $fixture['outbound_corridor']->delete();
        $this->createFourLegacyCorridors();

        $trip = $this->tripForVariant($fixture['route'], $fixture['outbound']);
        $position = $this->positionForTrip($trip, 14.0000, 121.0000);
        $position->update(['corridor_distance' => 88.8]);

        $context = app(SpatialContextResolver::class)->resolve($position);
        app(SpatialMonitoringEngine::class)->process($position, $context);

        $this->assertDatabaseHas('vehicle_positions', ['id' => $position->id]);
        $this->assertSame('missing_variant_corridor', $context->corridorSource);
        $this->assertSame(4, RouteCorridor::count());
        $this->assertSame(88.8, $position->fresh()->corridor_distance);
        $this->assertSame(0, RouteDeviation::where('trip_id', $trip->id)->count());
    }

    private function createDirectionalFixture(): array
    {
        $route = Route::factory()->create([
            'name' => 'Route 1',
            'status' => 'Active',
            'polyline_coordinates' => [[13.9000, 121.0000], [13.9000, 121.0100]],
        ]);

        $outbound = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'outbound',
            'origin_name' => 'Outbound Origin',
            'destination_name' => 'Outbound Destination',
            'polyline_coordinates' => [[14.0000, 121.0000], [14.0000, 121.0200]],
            'geometry_version' => 1,
            'geometry_status' => 'schematic',
            'is_default' => true,
        ]);

        $inbound = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'inbound',
            'origin_name' => 'Inbound Origin',
            'destination_name' => 'Inbound Destination',
            'polyline_coordinates' => [[14.0100, 121.0000], [14.0300, 121.0000]],
            'geometry_version' => 1,
            'geometry_status' => 'schematic',
            'is_default' => false,
        ]);

        $outboundCorridor = $this->variantCorridor($outbound);
        $inboundCorridor = $this->variantCorridor($inbound);

        return compact('route', 'outbound', 'inbound', 'outboundCorridor', 'inboundCorridor') + [
            'outbound_corridor' => $outboundCorridor,
            'inbound_corridor' => $inboundCorridor,
        ];
    }

    private function variantCorridor(RouteVariant $variant): RouteVariantCorridor
    {
        $coordinates = $variant->polyline_coordinates;

        return RouteVariantCorridor::create([
            'route_variant_id' => $variant->id,
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => $coordinates,
                'coordinate_order' => 'lat_lng',
            ],
            'geometry_hash' => hash('sha256', json_encode($coordinates, JSON_PRESERVE_ZERO_FRACTION)),
            'coordinate_count' => count($coordinates),
            'generated_at' => now(),
            'generation_source' => 'test',
        ]);
    }

    private function tripForVariant(Route $route, RouteVariant $variant): Trip
    {
        return Trip::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'status' => 'ongoing',
        ]);
    }

    private function positionForTrip(Trip $trip, float $lat, float $lng): VehiclePosition
    {
        return VehiclePosition::create([
            'bus_id' => $trip->bus_id,
            'trip_id' => $trip->id,
            'lat' => $lat,
            'lng' => $lng,
            'heading' => 0,
            'speed' => 6.0,
            'status' => 'Moving',
            'last_updated_at' => now(),
        ]);
    }

    private function createFourLegacyCorridors(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $route = Route::factory()->create(['name' => "Route Legacy {$i}"]);
            RouteCorridor::create([
                'route_id' => $route->id,
                'buffer_width' => 30.0,
                'source_type' => 'manual',
                'measurement_method' => 'haversine',
                'geometry' => ['type' => 'LineString', 'coordinates' => [[121.0000, 14.0000], [121.0200, 14.0000]]],
            ]);
        }
    }
}
