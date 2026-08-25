<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\StopArrival;
use App\Models\Trip;
use App\Models\TripProgress;
use App\Models\VehiclePosition;
use App\Services\Routing\CurrentTripStopResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentTripStopResolverTest extends TestCase
{
    use RefreshDatabase;

    private Trip $trip;
    private RouteVariant $variant;
    private RouteVariantStop $stop;
    private VehiclePosition $position;

    protected function setUp(): void
    {
        parent::setUp();

        $route = Route::create([
            'name' => 'Route 2',
            'status' => 'Active',
        ]);
        $this->variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'outbound',
            'origin_name' => 'SPED',
            'destination_name' => 'Ligaya',
            'is_default' => true,
        ]);
        $this->stop = RouteVariantStop::create([
            'route_variant_id' => $this->variant->id,
            'name' => 'SPED',
            'lat' => 14.5602934,
            'lng' => 121.0797616,
            'sequence' => 1,
        ]);

        $bus = Bus::factory()->create([
            'status' => 'operating',
            'route_id' => $route->id,
        ]);
        $driver = Driver::factory()->create([
            'status' => 'active',
            'operational_status' => 'driving',
        ]);
        $this->trip = Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'route_variant_id' => $this->variant->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now()->subMinute(),
            'dispatched_at' => now()->subMinutes(2),
        ]);
        TripProgress::create([
            'trip_id' => $this->trip->id,
            'current_route_variant_stop_id' => $this->stop->id,
            'last_completed_route_variant_stop_id' => $this->stop->id,
            'completed_stops_count' => 1,
        ]);
        StopArrival::create([
            'trip_id' => $this->trip->id,
            'route_variant_stop_id' => $this->stop->id,
            'arrival_time' => now(),
            'arrival_source' => 'GPS',
        ]);
        $this->position = VehiclePosition::create([
            'bus_id' => $bus->id,
            'trip_id' => $this->trip->id,
            'lat' => $this->stop->lat,
            'lng' => $this->stop->lng,
            'speed' => 0,
            'gps_quality_state' => 'GOOD',
            'last_gps_fix_at' => now(),
            'last_updated_at' => now(),
        ]);
    }

    public function test_it_resolves_a_fresh_confirmed_stop_for_the_same_trip_and_variant(): void
    {
        $resolved = app(CurrentTripStopResolver::class)->resolve($this->trip);

        $this->assertNotNull($resolved);
        $this->assertSame($this->stop->id, $resolved->id);
    }

    public function test_it_fails_closed_when_the_vehicle_position_is_stale(): void
    {
        $this->position->update([
            'last_gps_fix_at' => now()->subMinutes(6),
            'last_updated_at' => now()->subMinutes(6),
        ]);

        $this->assertNull(app(CurrentTripStopResolver::class)->resolve($this->trip));
    }

    public function test_it_fails_closed_when_the_stop_does_not_belong_to_the_trip_variant(): void
    {
        $otherVariant = RouteVariant::create([
            'route_id' => $this->trip->route_id,
            'direction' => 'inbound',
            'origin_name' => 'Ligaya',
            'destination_name' => 'SPED',
        ]);
        $otherStop = RouteVariantStop::create([
            'route_variant_id' => $otherVariant->id,
            'name' => 'Ligaya',
            'lat' => $this->stop->lat,
            'lng' => $this->stop->lng,
            'sequence' => 1,
        ]);
        TripProgress::where('trip_id', $this->trip->id)->update([
            'current_route_variant_stop_id' => $otherStop->id,
        ]);
        StopArrival::create([
            'trip_id' => $this->trip->id,
            'route_variant_stop_id' => $otherStop->id,
            'arrival_time' => now(),
            'arrival_source' => 'GPS',
        ]);

        $this->assertNull(app(CurrentTripStopResolver::class)->resolve($this->trip));
    }

    public function test_it_fails_closed_when_position_and_persisted_stop_disagree(): void
    {
        $this->position->update([
            'lat' => 14.5702934,
            'lng' => 121.0897616,
        ]);

        $this->assertNull(app(CurrentTripStopResolver::class)->resolve($this->trip));
    }

    public function test_it_fails_closed_without_an_open_stop_arrival(): void
    {
        StopArrival::where('trip_id', $this->trip->id)->update([
            'departure_time' => now(),
        ]);

        $this->assertNull(app(CurrentTripStopResolver::class)->resolve($this->trip));
    }
}
