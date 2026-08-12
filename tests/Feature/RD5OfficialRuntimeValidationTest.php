<?php

namespace Tests\Feature;

use App\Events\PositionUpdated;
use App\Models\Bus;
use App\Models\GPSLog;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Trip;
use App\Models\TripProgress;
use App\Models\VehiclePosition;
use App\Services\CommuterEtaProvenanceService;
use App\Services\Routing\AuthoritativeRouteResolver;
use App\Services\Routing\TripProgressService;
use App\Services\TelemetryProcessingService;
use App\Services\ValueObjects\Coordinate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RD5OfficialRuntimeValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_one_outbound_and_inbound_resolve_independent_official_contexts(): void
    {
        [$route, $outbound, $inbound] = $this->createRouteOneFixture();

        $outTrip = $this->tripForVariant($route, $outbound);
        $inTrip = $this->tripForVariant($route, $inbound);
        $resolver = app(AuthoritativeRouteResolver::class);

        $outPlan = $resolver->resolveForTrip($outTrip);
        $inPlan = $resolver->resolveForTrip($inTrip);

        $this->assertSame('route_variant', $outPlan->source);
        $this->assertSame($outbound->id, $outPlan->variant->id);
        $this->assertSame(['SPED', 'Caruncho', 'Kapasigan 1', 'Kapasigan 2', 'Rotonda', 'Ligaya'], $outPlan->orderedStops->pluck('name')->all());
        $this->assertSame($outbound->polyline_coordinates, $outPlan->polylineCoordinates);

        $this->assertSame('route_variant', $inPlan->source);
        $this->assertSame($inbound->id, $inPlan->variant->id);
        $this->assertSame(['Ligaya', 'Rotonda IN', 'Kapasigan IN', 'Caruncho IN', 'SPED'], $inPlan->orderedStops->pluck('name')->all());
        $this->assertSame($inbound->polyline_coordinates, $inPlan->polylineCoordinates);
    }

    public function test_trip_progress_advances_monotonically_and_does_not_regress_from_gps_noise(): void
    {
        [$route, $outbound] = $this->createRouteOneFixture();
        $trip = $this->tripForVariant($route, $outbound);
        $service = app(TripProgressService::class);

        $service->updateProgress($trip->id, new Coordinate(14.5600, 121.0800));
        $service->updateProgress($trip->id, new Coordinate(14.5605, 121.0805));
        $service->updateProgress($trip->id, new Coordinate(14.5600, 121.0800));

        $progress = TripProgress::with('nextRouteVariantStop')->where('trip_id', $trip->id)->firstOrFail();

        $this->assertSame(2, $progress->completed_stops_count);
        $this->assertSame('Kapasigan 1', $progress->nextRouteVariantStop->name);
    }

    public function test_duplicate_kapasigan_coordinates_advance_deterministically_without_merging_records(): void
    {
        [$route, $outbound] = $this->createRouteOneFixture();
        $trip = $this->tripForVariant($route, $outbound);
        $service = app(TripProgressService::class);

        foreach ([[14.5600, 121.0800], [14.5605, 121.0805], [14.5610, 121.0810], [14.5610, 121.0810]] as [$lat, $lng]) {
            $service->updateProgress($trip->id, new Coordinate($lat, $lng));
        }

        $progress = TripProgress::with('nextRouteVariantStop')->where('trip_id', $trip->id)->firstOrFail();
        $kapasiganStops = RouteVariantStop::where('route_variant_id', $outbound->id)
            ->whereIn('name', ['Kapasigan 1', 'Kapasigan 2'])
            ->orderBy('sequence')
            ->get();

        $this->assertCount(2, $kapasiganStops);
        $this->assertSame($kapasiganStops[0]->lat, $kapasiganStops[1]->lat);
        $this->assertSame(4, $progress->completed_stops_count);
        $this->assertSame('Rotonda', $progress->nextRouteVariantStop->name);
    }

    public function test_position_updated_event_creates_trip_progress_before_eta_calculation(): void
    {
        [$route, $outbound] = $this->createRouteOneFixture();
        $trip = $this->tripForVariant($route, $outbound);
        $position = VehiclePosition::create([
            'bus_id' => $trip->bus_id,
            'trip_id' => $trip->id,
            'lat' => 14.5600,
            'lng' => 121.0800,
            'speed' => 3.0,
            'heading' => 0,
            'status' => 'Moving',
            'last_updated_at' => now(),
        ]);

        event(new PositionUpdated($position));

        $progress = TripProgress::where('trip_id', $trip->id)->firstOrFail();
        $this->assertSame($outbound->stops()->where('sequence', 2)->first()->id, $progress->next_route_variant_stop_id);
        $this->assertNotEmpty($progress->upcoming_etas);
        $this->assertSame($progress->next_route_variant_stop_id, $progress->upcoming_etas[0]['stop_id']);
    }

    public function test_telemetry_syncs_bus_next_stop_and_eta_from_canonical_trip_progress(): void
    {
        [$route, $outbound] = $this->createRouteOneFixture();
        $bus = Bus::factory()->create([
            'status' => 'active',
            'route_id' => $route->id,
            'next_stop' => 'Legacy Route A Stop',
            'eta' => 99,
            'lat' => 14.5600,
            'lng' => 121.0800,
        ]);
        $trip = $this->tripForVariant($route, $outbound, ['bus_id' => $bus->id]);

        $log = GPSLog::create([
            'trip_id' => $trip->id,
            'lat' => 14.5600,
            'lng' => 121.0800,
            'speed' => 3.0,
            'heading' => 0,
            'accuracy' => 5.0,
            'timestamp' => now(),
            'received_at' => now(),
            'gps_fix_timestamp' => now(),
            'gps_fix_age_ms' => 0,
            'is_cached_fix' => false,
            'speed_source' => 'native',
            'processing_status' => 'pending',
        ]);

        $result = app(TelemetryProcessingService::class)->processGpsLog($log->id);

        $this->assertSame('processed', $result['status']);
        $bus->refresh();
        $progress = TripProgress::with('nextRouteVariantStop')->where('trip_id', $trip->id)->firstOrFail();
        $this->assertSame($progress->nextRouteVariantStop->name, $bus->next_stop);
        $this->assertNotSame('Legacy Route A Stop', $bus->next_stop);
        $this->assertIsInt($bus->eta);
    }

    public function test_commuter_eta_provenance_accepts_schematic_official_geometry(): void
    {
        [$route, $outbound] = $this->createRouteOneFixture();
        $bus = Bus::factory()->create(['status' => 'active', 'route_id' => $route->id, 'eta' => 12]);
        $trip = $this->tripForVariant($route, $outbound, ['bus_id' => $bus->id]);
        $stop = $outbound->stops()->where('sequence', 2)->firstOrFail();
        TripProgress::create([
            'trip_id' => $trip->id,
            'completed_stops_count' => 1,
            'remaining_stops_count' => 5,
            'trip_percentage' => 16.7,
            'route_adherence' => 'On Route',
            'current_delay_minutes' => 0,
            'next_route_variant_stop_id' => $stop->id,
            'upcoming_etas' => [[
                'stop_id' => $stop->id,
                'eta_timestamp' => now()->addMinutes(4)->toIso8601String(),
                'distance_remaining_meters' => 250.0,
                'delay_minutes' => 0,
            ]],
        ]);

        $eta = app(CommuterEtaProvenanceService::class)->forBus($bus);

        $this->assertSame(CommuterEtaProvenanceService::AUTHORITATIVE, $eta->state);
        $this->assertTrue($eta->is_authoritative);
    }

    public function test_new_inbound_leg_uses_inbound_variant_and_resets_progress(): void
    {
        [$route, $outbound, $inbound] = $this->createRouteOneFixture();
        $outTrip = $this->tripForVariant($route, $outbound, ['status' => 'completed']);
        TripProgress::create([
            'trip_id' => $outTrip->id,
            'completed_stops_count' => 6,
            'remaining_stops_count' => 0,
            'trip_percentage' => 100,
            'route_adherence' => 'On Route',
            'current_delay_minutes' => 0,
        ]);

        $inTrip = $this->tripForVariant($route, $inbound);
        app(TripProgressService::class)->updateProgress($inTrip->id, new Coordinate(14.5650, 121.0850));

        $progress = TripProgress::with('nextRouteVariantStop')->where('trip_id', $inTrip->id)->firstOrFail();
        $this->assertSame(1, $progress->completed_stops_count);
        $this->assertSame('Rotonda IN', $progress->nextRouteVariantStop->name);
    }

    private function createRouteOneFixture(): array
    {
        $route = Route::factory()->create([
            'name' => 'Route 2',
            'status' => 'Active',
            'polyline_coordinates' => [[14.0, 121.0], [14.1, 121.1]],
        ]);

        $outbound = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'outbound',
            'origin_name' => 'SPED',
            'destination_name' => 'Ligaya',
            'polyline_coordinates' => [
                [14.5600, 121.0800],
                [14.5605, 121.0805],
                [14.5610, 121.0810],
                [14.5610, 121.0810],
                [14.5630, 121.0830],
                [14.5650, 121.0850],
            ],
            'geometry_version' => 1,
            'geometry_status' => 'schematic',
            'is_default' => true,
        ]);

        $inbound = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'inbound',
            'origin_name' => 'Ligaya',
            'destination_name' => 'SPED',
            'polyline_coordinates' => [
                [14.5650, 121.0850],
                [14.5630, 121.0830],
                [14.5610, 121.0810],
                [14.5605, 121.0805],
                [14.5600, 121.0800],
            ],
            'geometry_version' => 1,
            'geometry_status' => 'schematic',
            'is_default' => false,
        ]);

        foreach ([
            ['SPED', 14.5600, 121.0800],
            ['Caruncho', 14.5605, 121.0805],
            ['Kapasigan 1', 14.5610, 121.0810],
            ['Kapasigan 2', 14.5610, 121.0810],
            ['Rotonda', 14.5630, 121.0830],
            ['Ligaya', 14.5650, 121.0850],
        ] as $index => [$name, $lat, $lng]) {
            RouteVariantStop::create([
                'route_variant_id' => $outbound->id,
                'name' => $name,
                'lat' => $lat,
                'lng' => $lng,
                'radius_meters' => 35,
                'sequence' => $index + 1,
                'stop_type' => $index === 0 ? 'pickup_point' : 'designated_stop',
                'coordinate_status' => 'verified',
            ]);
        }

        foreach ([
            ['Ligaya', 14.5650, 121.0850],
            ['Rotonda IN', 14.5630, 121.0830],
            ['Kapasigan IN', 14.5610, 121.0810],
            ['Caruncho IN', 14.5605, 121.0805],
            ['SPED', 14.5600, 121.0800],
        ] as $index => [$name, $lat, $lng]) {
            RouteVariantStop::create([
                'route_variant_id' => $inbound->id,
                'name' => $name,
                'lat' => $lat,
                'lng' => $lng,
                'radius_meters' => 35,
                'sequence' => $index + 1,
                'stop_type' => $index === 0 ? 'pickup_point' : 'designated_stop',
                'coordinate_status' => 'verified',
            ]);
        }

        return [$route, $outbound->fresh('stops'), $inbound->fresh('stops')];
    }

    private function tripForVariant(Route $route, RouteVariant $variant, array $overrides = []): Trip
    {
        return Trip::factory()->create(array_merge([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
        ], $overrides));
    }
}
