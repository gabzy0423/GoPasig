<?php

namespace Tests\Feature;

use App\Events\PositionUpdated;
use App\Events\VehicleOnline;
use App\Models\Route;
use App\Models\RouteCorridor;
use App\Models\RouteDeviation;
use App\Models\RouteVariant;
use App\Models\RouteVariantCorridor;
use App\Models\Trip;
use App\Models\VehiclePosition;
use App\Services\Spatial\SpatialContextResolver;
use App\Services\Spatial\SpatialMonitoringEngine;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GpsRouteVariantCorridorRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_spatial_context_no_longer_resolves_corridors_but_preserves_stored_geometry(): void
    {
        $fixture = $this->createFixture();
        $trip = $this->tripForVariant($fixture['route'], $fixture['variant']);
        $position = $this->positionForTrip($trip, 14.0100, 121.0100);

        $context = app(SpatialContextResolver::class)->resolve($position);

        $this->assertFalse(property_exists($context, 'corridor'));
        $this->assertFalse(property_exists($context, 'corridorSource'));
        $this->assertDatabaseHas('route_variant_corridors', [
            'id' => $fixture['variant_corridor']->id,
            'route_variant_id' => $fixture['variant']->id,
        ]);
        $this->assertDatabaseHas('route_corridors', [
            'id' => $fixture['legacy_corridor']->id,
            'route_id' => $fixture['route']->id,
        ]);
    }

    public function test_spatial_monitoring_does_not_create_or_resolve_route_deviations(): void
    {
        Event::fake([VehicleOnline::class]);
        $fixture = $this->createFixture();
        $trip = $this->tripForVariant($fixture['route'], $fixture['variant']);
        $position = $this->positionForTrip($trip, 14.0500, 121.0500);
        $historicalDeviation = RouteDeviation::create([
            'trip_id' => $trip->id,
            'lat' => 14.0400,
            'lng' => 121.0400,
            'distance_meters' => 450.0,
            'severity' => 'Critical',
            'detected_at' => now()->subMinute(),
        ]);

        $context = app(SpatialContextResolver::class)->resolve($position);
        app(SpatialMonitoringEngine::class)->process($position, $context);

        $this->assertSame(1, RouteDeviation::where('trip_id', $trip->id)->count());
        $this->assertNull($historicalDeviation->fresh()->resolved_at);
    }

    public function test_position_updated_runtime_keeps_valid_listeners_without_writing_deviations(): void
    {
        Event::fake([VehicleOnline::class]);
        $fixture = $this->createFixture();
        $trip = $this->tripForVariant($fixture['route'], $fixture['variant']);
        $position = $this->positionForTrip($trip, 14.0500, 121.0500);

        event(new PositionUpdated($position));

        $this->assertDatabaseHas('vehicle_positions', ['id' => $position->id]);
        $this->assertDatabaseMissing('route_deviations', ['trip_id' => $trip->id]);
    }

    public function test_retired_generator_command_and_route_seeder_cannot_create_corridors(): void
    {
        $this->assertArrayNotHasKey('gopasig:generate-route-variant-corridors', Artisan::all());

        $this->seed(RouteSeeder::class);

        $this->assertSame(0, RouteCorridor::count());
        $this->assertSame(0, RouteVariantCorridor::count());
    }

    private function createFixture(): array
    {
        $route = Route::factory()->create([
            'name' => 'Route 2',
            'status' => 'Active',
            'polyline_coordinates' => [[14.0000, 121.0000], [14.0000, 121.0200]],
        ]);
        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'outbound',
            'origin_name' => 'SPED',
            'destination_name' => 'Ligaya',
            'polyline_coordinates' => [[14.0000, 121.0000], [14.0000, 121.0200]],
            'geometry_version' => 1,
            'geometry_status' => 'schematic',
            'is_default' => true,
        ]);
        $coordinates = $variant->polyline_coordinates;
        $variantCorridor = RouteVariantCorridor::create([
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
        $legacyCorridor = RouteCorridor::create([
            'route_id' => $route->id,
            'buffer_width' => 30.0,
            'source_type' => 'manual',
            'measurement_method' => 'haversine',
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => [[121.0000, 14.0000], [121.0200, 14.0000]],
            ],
        ]);

        return [
            'route' => $route,
            'variant' => $variant,
            'variant_corridor' => $variantCorridor,
            'legacy_corridor' => $legacyCorridor,
        ];
    }

    private function tripForVariant(Route $route, RouteVariant $variant): Trip
    {
        return Trip::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'status' => 'ongoing',
            'started_at' => now()->subMinute(),
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
}
