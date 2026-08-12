<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Schedule;
use App\Models\Stop;
use App\Models\Trip;
use App\Models\User;
use App\Services\SimulationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperationalDirectionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_can_persist_a_valid_route_variant(): void
    {
        $admin = $this->actingAsAdmin();
        $route = Route::factory()->official()->create();
        $variant = $this->variantFor($route, 'outbound', 'SPED', 'Ligaya');
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);

        $response = $this->actingAs($admin)->postJson('/admin/api/schedules', [
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'bus_plate' => $bus->plate_number,
            'driver_id' => $driver->id,
            'service_date' => now('Asia/Manila')->addDay()->toDateString(),
            'departure_time' => '08:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('schedule.routeVariantId', $variant->id)
            ->assertJsonPath('schedule.direction', 'outbound');

        $this->assertDatabaseHas('schedules', [
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
        ]);
    }

    public function test_schedule_rejects_route_variant_from_another_route(): void
    {
        $admin = $this->actingAsAdmin();
        $route = Route::factory()->official()->create();
        $otherRoute = Route::factory()->official('Route 3')->create();
        $otherVariant = $this->variantFor($otherRoute, 'inbound', 'Ligaya', 'SPED');
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);

        $response = $this->actingAs($admin)->postJson('/admin/api/schedules', [
            'route_id' => $route->id,
            'route_variant_id' => $otherVariant->id,
            'bus_plate' => $bus->plate_number,
            'driver_id' => $driver->id,
            'service_date' => now('Asia/Manila')->addDay()->toDateString(),
            'departure_time' => '09:00',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('schedules', [
            'route_id' => $route->id,
            'route_variant_id' => $otherVariant->id,
        ]);
    }

    public function test_scheduled_dispatch_propagates_route_variant_to_trip(): void
    {
        $route = Route::factory()->official()->create();
        $variant = $this->variantFor($route, 'outbound', 'SPED', 'Ligaya');
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);

        $schedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now('Asia/Manila')->toDateString(),
            'departure_time' => now('Asia/Manila')->addHours(3)->format('H:i'),
        ]);

        $trip = SimulationDispatchService::dispatchFromSchedule($schedule, null, 'Scheduled test dispatch.');

        $this->assertSame($route->id, $trip->route_id);
        $this->assertSame($variant->id, $trip->route_variant_id);
    }

    public function test_manual_outbound_and_inbound_dispatch_create_directional_trips(): void
    {
        $route = Route::factory()->official()->create();
        $outbound = $this->variantFor($route, 'outbound', 'SPED', 'Ligaya');
        $inbound = $this->variantFor($route, 'inbound', 'Ligaya', 'SPED');

        $outboundTrip = SimulationDispatchService::dispatch(
            Bus::factory()->create(['status' => 'inactive']),
            Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']),
            $route,
            null,
            'Outbound test dispatch.',
            $outbound
        );

        $inboundTrip = SimulationDispatchService::dispatch(
            Bus::factory()->create(['status' => 'inactive']),
            Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']),
            $route,
            null,
            'Inbound test dispatch.',
            $inbound
        );

        $this->assertSame($outbound->id, $outboundTrip->route_variant_id);
        $this->assertSame($inbound->id, $inboundTrip->route_variant_id);
    }

    public function test_variant_and_route_mismatch_is_rejected_for_dispatch(): void
    {
        $route = Route::factory()->official()->create();
        $otherRoute = Route::factory()->official('Route 3')->create();
        $otherVariant = $this->variantFor($otherRoute, 'inbound', 'Ligaya', 'SPED');

        $this->expectException(ValidationException::class);

        SimulationDispatchService::dispatch(
            Bus::factory()->create(['status' => 'inactive']),
            Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']),
            $route,
            null,
            'Mismatch test dispatch.',
            $otherVariant
        );
    }

    public function test_pending_geometry_cannot_dispatch_by_falling_back_to_another_direction(): void
    {
        $route = Route::factory()->official()->create();
        $this->variantFor($route, 'outbound', 'SPED', 'Ligaya');
        $pendingInbound = $this->variantFor($route, 'inbound', 'Ligaya', 'SPED', 'pending');

        try {
            SimulationDispatchService::dispatch(
                $bus = Bus::factory()->create(['status' => 'inactive']),
                Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']),
                $route,
                null,
                'Pending inbound test dispatch.',
                $pendingInbound
            );

            $this->fail('Pending geometry should not dispatch.');
        } catch (ValidationException $exception) {
            $this->assertDatabaseMissing('trips', [
                'bus_id' => $bus->id,
                'route_variant_id' => $pendingInbound->id,
            ]);
        }
    }

    public function test_legacy_schedule_and_trip_with_null_variant_still_work(): void
    {
        $route = Route::factory()->official()->create();
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);

        $schedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => null,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
        ]);
        $trip = Trip::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => null,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
        ]);

        $this->assertNull($schedule->route_variant_id);
        $this->assertNull($trip->route_variant_id);
        $this->assertSame($route->id, $trip->route_id);
    }

    public function test_active_trip_exposes_direction_metadata_without_bus_owning_direction(): void
    {
        $admin = $this->actingAsAdmin();
        $route = Route::factory()->official()->create();
        $variant = $this->variantFor($route, 'inbound', 'Ligaya', 'SPED');
        $bus = Bus::factory()->create(['status' => 'operating']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'assigned']);

        Trip::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'ongoing',
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/api/fleet-data');

        $response->assertOk();
        $busPayload = collect($response->json('buses'))->firstWhere('id', $bus->id);
        $this->assertSame($variant->id, $busPayload['route_variant_id']);
        $this->assertSame('inbound', $busPayload['direction']);
        $this->assertArrayNotHasKey('route_variant_id', $bus->getAttributes());
    }


    public function test_driver_fallback_start_reuses_existing_dispatched_directional_trip(): void
    {
        $user = User::factory()->create(['role' => 'driver']);
        $route = Route::factory()->official()->create();
        $variant = $this->variantFor($route, 'outbound', 'SPED', 'Ligaya');
        $bus = Bus::factory()->create(['status' => 'ready', 'route_id' => $route->id]);
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'operational_status' => 'assigned',
            'assigned_bus' => $bus->plate_number,
            'assigned_route' => (string) $route->id,
        ]);
        $bus->update(['driver_name' => $driver->first_name . ' ' . $driver->last_name]);

        $trip = Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'status' => 'dispatched',
            'gps_session' => 'OFF',
            'dispatched_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/driver/trip/toggle', ['status' => 'active']);

        $response->assertOk();
        $trip->refresh();
        $this->assertSame('ongoing', $trip->status);
        $this->assertSame('ACTIVE', $trip->gps_session);
        $this->assertSame($variant->id, $trip->route_variant_id);
        $this->assertSame(1, Trip::where('bus_id', $bus->id)->count());
    }

    public function test_driver_fallback_start_rejects_ambiguous_direction_without_creating_trip(): void
    {
        $user = User::factory()->create(['role' => 'driver']);
        $route = Route::factory()->official()->create();
        $this->variantFor($route, 'outbound', 'SPED', 'Ligaya');
        $this->variantFor($route, 'inbound', 'Ligaya', 'SPED');
        $bus = Bus::factory()->create(['status' => 'ready', 'route_id' => $route->id]);
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'operational_status' => 'assigned',
            'assigned_bus' => $bus->plate_number,
            'assigned_route' => (string) $route->id,
        ]);
        $bus->update(['driver_name' => $driver->first_name . ' ' . $driver->last_name]);

        $response = $this->actingAs($user)->postJson('/driver/trip/toggle', ['status' => 'active']);

        $response->assertStatus(422);
        $this->assertSame(0, Trip::where('bus_id', $bus->id)->count());
    }

    public function test_directional_dispatch_rejects_route_with_variants_but_no_usable_direction(): void
    {
        $route = Route::factory()->official()->create();
        $this->variantFor($route, 'outbound', 'SPED', 'Ligaya', 'pending');
        $bus = Bus::factory()->create(['status' => 'inactive']);

        $this->expectException(ValidationException::class);

        try {
            SimulationDispatchService::dispatch(
                $bus,
                Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']),
                $route,
                null,
                'No usable direction dispatch.'
            );
        } finally {
            $this->assertSame(0, Trip::where('bus_id', $bus->id)->count());
        }
    }
    private function actingAsAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function variantFor(Route $route, string $direction, string $origin, string $destination, string $status = 'valid'): RouteVariant
    {
        Stop::create(['route_id' => $route->id, 'name' => $origin, 'lat' => 14.5000, 'lng' => 121.0000, 'sequence' => 1]);
        Stop::create(['route_id' => $route->id, 'name' => $destination, 'lat' => 14.5100, 'lng' => 121.0100, 'sequence' => 2]);

        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => $direction,
            'origin_name' => $origin,
            'destination_name' => $destination,
            'polyline_coordinates' => [[14.5000, 121.0000], [14.5100, 121.0100]],
            'geometry_version' => 1,
            'geometry_status' => $status,
            'is_default' => $direction === 'outbound',
        ]);

        RouteVariantStop::create(['route_variant_id' => $variant->id, 'name' => $origin, 'lat' => 14.5000, 'lng' => 121.0000, 'radius_meters' => 50, 'sequence' => 1]);
        RouteVariantStop::create(['route_variant_id' => $variant->id, 'name' => $destination, 'lat' => 14.5100, 'lng' => 121.0100, 'radius_meters' => 50, 'sequence' => 2]);

        return $variant;
    }
}

