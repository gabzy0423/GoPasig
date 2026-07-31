<?php

namespace Tests\Feature;

use App\Events\PositionUpdated;
use App\Listeners\ETAListener;
use App\Models\Bus;
use App\Models\Route;
use App\Models\RouteCorridor;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Stop;
use App\Models\Trip;
use App\Models\TripProgress;
use App\Models\VehiclePosition;
use App\Services\Routing\AuthoritativeRouteResolver;
use App\Services\Routing\TripProgressService;
use App\Services\Spatial\RouteCorridorEngine;
use App\Services\Testing\ControlledLocationIntelligenceHarness;
use App\Services\ValueObjects\Coordinate;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectionAwareLocationIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_direction_specific_variants_drive_geometry_stops_trip_progress_and_eta(): void
    {
        config(['fleet.spatial.corridor_default' => 25.0]);
        config(['fleet.stops.entry_radius_meters' => 35.0]);
        config(['fleet.stops.exit_radius_meters' => 50.0]);

        [$route, $outbound, $inbound] = $this->createDirectionalRouteFixture();

        $outboundTrip = Trip::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $outbound->id,
            'status' => 'ongoing',
        ]);
        $inboundTrip = Trip::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $inbound->id,
            'status' => 'ongoing',
        ]);

        $resolver = app(AuthoritativeRouteResolver::class);
        $outboundPlan = $resolver->resolveForTrip($outboundTrip);
        $inboundPlan = $resolver->resolveForTrip($inboundTrip);

        $this->assertSame($outbound->polyline_coordinates, $outboundPlan->polylineCoordinates);
        $this->assertSame(['Outbound A', 'Outbound B', 'Outbound C'], $outboundPlan->orderedStops->pluck('name')->all());
        $this->assertSame($inbound->polyline_coordinates, $inboundPlan->polylineCoordinates);
        $this->assertSame(['Inbound C', 'Inbound D', 'Inbound A'], $inboundPlan->orderedStops->pluck('name')->all());

        app(TripProgressService::class)->updateProgress($outboundTrip->id, new Coordinate(14.0000, 121.0000));
        app(TripProgressService::class)->updateProgress($inboundTrip->id, new Coordinate(14.0100, 121.0000));

        $outboundProgress = TripProgress::where('trip_id', $outboundTrip->id)->firstOrFail();
        $inboundProgress = TripProgress::where('trip_id', $inboundTrip->id)->firstOrFail();
        $this->assertSame($outboundPlan->orderedStops[0]->id, $outboundProgress->current_route_variant_stop_id);
        $this->assertSame($outboundPlan->orderedStops[1]->id, $outboundProgress->next_route_variant_stop_id);
        $this->assertSame($inboundPlan->orderedStops[0]->id, $inboundProgress->current_route_variant_stop_id);
        $this->assertSame($inboundPlan->orderedStops[1]->id, $inboundProgress->next_route_variant_stop_id);

        $corridor = RouteCorridor::create([
            'route_id' => $route->id,
            'buffer_width' => 25.0,
            'source_type' => 'manual',
            'measurement_method' => 'haversine',
            'geometry' => ['type' => 'LineString', 'coordinates' => []],
        ]);

        $inboundPosition = $this->positionForTrip($inboundTrip, 14.0150, 121.0000);
        app(RouteCorridorEngine::class)->check($inboundPosition, new Coordinate(14.0150, 121.0000), $corridor, $inboundTrip);
        $this->assertSame('On Route', TripProgress::where('trip_id', $inboundTrip->id)->firstOrFail()->route_adherence);

        $outboundPosition = $this->positionForTrip($outboundTrip, 14.0150, 121.0000);
        app(RouteCorridorEngine::class)->check($outboundPosition, new Coordinate(14.0150, 121.0000), $corridor, $outboundTrip);
        $this->assertSame('Critical Deviation', TripProgress::where('trip_id', $outboundTrip->id)->firstOrFail()->route_adherence);

        app(ETAListener::class)->handle(new PositionUpdated($inboundPosition));
        $inboundProgress->refresh();
        $this->assertSame($inboundPlan->orderedStops[1]->id, $inboundProgress->upcoming_etas[0]['stop_id']);
    }

    public function test_route_c_harness_remains_valid_with_default_variant_assignment(): void
    {
        $this->seed(RouteSeeder::class);

        $route = Route::with('defaultVariant')->findOrFail(3);
        $harness = app(ControlledLocationIntelligenceHarness::class);
        $run = $harness->run(['route_variant_id' => $route->defaultVariant->id]);

        $harness->assertRunProcessed($run);
        $this->assertSame($route->defaultVariant->id, $run['trip']['route_variant_id']);
        $this->assertGreaterThan(60, count($run['results']));

        $final = collect($run['results'])->last();
        $this->assertNull($final['fleet_api']['next_stop']);
        $this->assertNull($final['admin_api']['next_stop']);
        $this->assertSame($final['fleet_api']['corridor_distance'], $final['admin_api']['corridor_distance']);
        $this->assertSame($final['fleet_api']['route_adherence'], $final['admin_api']['route_adherence']);
        $this->assertNotNull(collect($run['results'])->first(fn (array $result) => ($result['trip_progress']['current_route_variant_stop_id'] ?? null) !== null));
    }

    /**
     * @return array{0: Route, 1: RouteVariant, 2: RouteVariant}
     */
    private function createDirectionalRouteFixture(): array
    {
        $route = Route::factory()->create([
            'name' => 'Route Direction Fixture',
            'polyline_coordinates' => [[13.9000, 121.0000], [13.9000, 121.0100]],
            'status' => 'Active',
        ]);

        Stop::create(['route_id' => $route->id, 'name' => 'Legacy A', 'lat' => 13.9000, 'lng' => 121.0000, 'sequence' => 1]);
        Stop::create(['route_id' => $route->id, 'name' => 'Legacy B', 'lat' => 13.9000, 'lng' => 121.0100, 'sequence' => 2]);

        $outbound = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'outbound',
            'origin_name' => 'Outbound Origin',
            'destination_name' => 'Outbound Destination',
            'polyline_coordinates' => [[14.0000, 121.0000], [14.0000, 121.0100], [14.0000, 121.0200]],
            'geometry_version' => 1,
            'geometry_status' => 'authoritative',
            'is_default' => true,
        ]);

        $inbound = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'inbound',
            'origin_name' => 'Inbound Origin',
            'destination_name' => 'Inbound Destination',
            'polyline_coordinates' => [[14.0100, 121.0000], [14.0200, 121.0000], [14.0300, 121.0000]],
            'geometry_version' => 1,
            'geometry_status' => 'authoritative',
            'is_default' => false,
        ]);

        foreach ([
            [$outbound, 'Outbound A', 14.0000, 121.0000, 1],
            [$outbound, 'Outbound B', 14.0000, 121.0100, 2],
            [$outbound, 'Outbound C', 14.0000, 121.0200, 3],
            [$inbound, 'Inbound C', 14.0100, 121.0000, 1],
            [$inbound, 'Inbound D', 14.0200, 121.0000, 2],
            [$inbound, 'Inbound A', 14.0300, 121.0000, 3],
        ] as [$variant, $name, $lat, $lng, $sequence]) {
            RouteVariantStop::create([
                'route_variant_id' => $variant->id,
                'name' => $name,
                'lat' => $lat,
                'lng' => $lng,
                'radius_meters' => 30,
                'sequence' => $sequence,
            ]);
        }

        return [$route, $outbound->fresh('stops'), $inbound->fresh('stops')];
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

