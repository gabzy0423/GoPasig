<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Stop;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverNextLegFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_route_two_leg_starts_the_opposite_leg(): void
    {
        [$user, $bus, $driver, $route, $outbound, $inbound, $previousTrip] = $this->completedRouteTwoLeg();

        $response = $this->actingAs($user)->postJson('/driver/trip/next');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('route_variant_id', $inbound->id)
            ->assertJsonPath('direction', 'inbound')
            ->assertJsonPath('status', 'ongoing');

        $nextTrip = Trip::query()
            ->where('id', '!=', $previousTrip->id)
            ->where('bus_id', $bus->id)
            ->firstOrFail();

        $this->assertSame('completed', $previousTrip->fresh()->status);
        $this->assertSame('CLOSED', $previousTrip->fresh()->gps_session);
        $this->assertSame($outbound->id, $previousTrip->route_variant_id);
        $this->assertSame('ongoing', $nextTrip->status);
        $this->assertSame('ACTIVE', $nextTrip->gps_session);
        $this->assertSame($inbound->id, $nextTrip->route_variant_id);
        $this->assertSame('operating', $bus->fresh()->status);
        $this->assertSame('driving', $driver->fresh()->operational_status);
    }

    public function test_driver_page_exposes_next_leg_after_completed_route_two_trip(): void
    {
        [$user] = $this->completedRouteTwoLeg();

        $this->actingAs($user)
            ->get('/driver/trip')
            ->assertOk()
            ->assertSee('START NEXT TRIP')
            ->assertSee('Next Trip')
            ->assertSee('Trip completed');
    }

    /**
     * @return array{0: User, 1: Bus, 2: Driver, 3: Route, 4: RouteVariant, 5: RouteVariant, 6: Trip}
     */
    private function completedRouteTwoLeg(): array
    {
        $user = User::factory()->create(['role' => 'driver']);
        $route = Route::factory()->create(['name' => 'Route 2']);
        $outbound = $this->variantFor($route, 'outbound', 'SPED', 'Ligaya');
        $inbound = $this->variantFor($route, 'inbound', 'Ligaya', 'SPED');
        $bus = Bus::factory()->create([
            'status' => 'ready',
            'route_id' => $route->id,
        ]);
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'operational_status' => 'assigned',
            'assigned_bus' => $bus->plate_number,
            'assigned_route' => (string) $route->id,
        ]);
        $bus->update(['driver_name' => $driver->name]);

        $previousTrip = Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'route_variant_id' => $outbound->id,
            'status' => 'completed',
            'gps_session' => 'CLOSED',
            'dispatched_at' => now()->subHour(),
            'started_at' => now()->subMinutes(55),
            'ended_at' => now()->subMinutes(5),
            'gps_session_started_at' => now()->subMinutes(55),
        ]);

        return [$user, $bus, $driver, $route, $outbound, $inbound, $previousTrip];
    }

    private function variantFor(Route $route, string $direction, string $origin, string $destination): RouteVariant
    {
        $originStop = Stop::create([
            'route_id' => $route->id,
            'name' => $origin,
            'lat' => 14.5000,
            'lng' => 121.0000,
            'sequence' => 1,
        ]);
        $destinationStop = Stop::create([
            'route_id' => $route->id,
            'name' => $destination,
            'lat' => 14.5100,
            'lng' => 121.0100,
            'sequence' => 2,
        ]);

        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => $direction,
            'origin_name' => $origin,
            'destination_name' => $destination,
            'polyline_coordinates' => [[14.5000, 121.0000], [14.5100, 121.0100]],
            'geometry_version' => 1,
            'geometry_status' => 'valid',
            'is_default' => $direction === 'outbound',
        ]);

        RouteVariantStop::create([
            'route_variant_id' => $variant->id,
            'name' => $origin,
            'lat' => 14.5000,
            'lng' => 121.0000,
            'radius_meters' => 50,
            'sequence' => 1,
        ]);
        RouteVariantStop::create([
            'route_variant_id' => $variant->id,
            'name' => $destination,
            'lat' => 14.5100,
            'lng' => 121.0100,
            'radius_meters' => 50,
            'sequence' => 2,
        ]);

        return $variant;
    }
}
