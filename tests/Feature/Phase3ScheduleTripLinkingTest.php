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
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase3ScheduleTripLinkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_trips_schedule_id_exists_and_is_nullable_for_legacy_or_ad_hoc_trips(): void
    {
        $this->assertTrue(Schema::hasColumn('trips', 'schedule_id'));

        $trip = Trip::factory()->create(['schedule_id' => null]);

        $this->assertNull($trip->schedule_id);
    }

    public function test_manual_dispatch_creates_unscheduled_trip(): void
    {
        $route = Route::factory()->create();
        $variant = $this->variantFor($route, 'outbound', 'SPED', 'Ligaya');
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);

        $trip = SimulationDispatchService::dispatch($bus, $driver, $route, null, 'Manual dispatch.', $variant);

        $this->assertNull($trip->schedule_id);
    }

    public function test_dispatch_from_schedule_creates_one_linked_trip_preserving_assignment_and_direction(): void
    {
        [$schedule, $bus, $driver, $route, $variant] = $this->scheduledDirectionalLeg();

        $trip = SimulationDispatchService::dispatchFromSchedule($schedule, null, 'Scheduled dispatch.');

        $this->assertSame($schedule->id, $trip->schedule_id);
        $this->assertSame($bus->id, $trip->bus_id);
        $this->assertSame($driver->id, $trip->driver_id);
        $this->assertSame($route->id, $trip->route_id);
        $this->assertSame($variant->id, $trip->route_variant_id);
        $this->assertSame($trip->id, $schedule->fresh()->trip->id);
        $this->assertDatabaseCount('trips', 1);
    }

    public function test_same_schedule_cannot_create_second_trip(): void
    {
        [$schedule] = $this->scheduledDirectionalLeg();

        SimulationDispatchService::dispatchFromSchedule($schedule);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already been dispatched');

        SimulationDispatchService::dispatchFromSchedule($schedule->fresh());
    }

    public function test_schedule_variant_from_another_route_is_rejected_for_scheduled_dispatch(): void
    {
        $route = Route::factory()->create();
        $otherRoute = Route::factory()->create();
        $otherVariant = $this->variantFor($otherRoute, 'outbound', 'Other A', 'Other B');
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);
        $schedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $otherVariant->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
        ]);

        $this->expectException(ValidationException::class);

        SimulationDispatchService::dispatchFromSchedule($schedule);
    }

    public function test_pending_unusable_schedule_variant_is_rejected_for_scheduled_dispatch(): void
    {
        $route = Route::factory()->create();
        $variant = $this->variantFor($route, 'outbound', 'SPED', 'Ligaya', 'pending');
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);
        $schedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
        ]);

        $this->expectException(ValidationException::class);

        SimulationDispatchService::dispatchFromSchedule($schedule);
    }

    public function test_legacy_schedule_with_null_variant_can_still_create_linked_legacy_trip(): void
    {
        $route = Route::factory()->create();
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);
        $schedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => null,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
        ]);

        $trip = SimulationDispatchService::dispatchFromSchedule($schedule);

        $this->assertSame($schedule->id, $trip->schedule_id);
        $this->assertNull($trip->route_variant_id);
    }

    public function test_admin_can_dispatch_eligible_schedule_through_runtime_endpoint(): void
    {
        $admin = $this->actingAsAdmin();
        [$schedule, $bus, $driver, $route, $variant] = $this->scheduledDirectionalLeg([
            'departure_time' => now('Asia/Manila')->format('H:i'),
        ]);

        $response = $this->actingAs($admin)->postJson("/admin/api/schedules/{$schedule->id}/dispatch");

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('trip.scheduleId', $schedule->id)
            ->assertJsonPath('trip.busId', $bus->id)
            ->assertJsonPath('trip.driverId', $driver->id)
            ->assertJsonPath('trip.routeId', $route->id)
            ->assertJsonPath('trip.routeVariantId', $variant->id);

        $trip = Trip::where('schedule_id', $schedule->id)->sole();
        $this->assertSame($schedule->id, $trip->schedule_id);
        $this->assertSame($bus->id, $trip->bus_id);
        $this->assertSame($driver->id, $trip->driver_id);
        $this->assertSame($route->id, $trip->route_id);
        $this->assertSame($variant->id, $trip->route_variant_id);
    }

    public function test_dispatch_queue_exposes_linked_trip_state_from_schedule_relationship(): void
    {
        $admin = $this->actingAsAdmin();
        [$schedule] = $this->scheduledDirectionalLeg();

        $initial = $this->actingAs($admin)->getJson('/admin/api/schedules/dispatch-queue/today');
        $initial->assertOk()
            ->assertJsonPath('dispatches.0.id', $schedule->id)
            ->assertJsonPath('dispatches.0.isDispatched', false)
            ->assertJsonPath('dispatches.0.canDispatch', true)
            ->assertJsonPath('dispatches.0.dispatchState', 'eligible');

        $trip = SimulationDispatchService::dispatchFromSchedule($schedule);

        $linked = $this->actingAs($admin)->getJson('/admin/api/schedules/dispatch-queue/today');
        $linked->assertOk()
            ->assertJsonPath('dispatches.0.id', $schedule->id)
            ->assertJsonPath('dispatches.0.tripId', $trip->id)
            ->assertJsonPath('dispatches.0.isDispatched', true)
            ->assertJsonPath('dispatches.0.canDispatch', false)
            ->assertJsonPath('dispatches.0.dispatchState', 'dispatched');
    }

    public function test_second_runtime_schedule_dispatch_attempt_cannot_create_another_trip(): void
    {
        $admin = $this->actingAsAdmin();
        [$schedule] = $this->scheduledDirectionalLeg();

        $this->actingAs($admin)->postJson("/admin/api/schedules/{$schedule->id}/dispatch")
            ->assertCreated();

        $this->actingAs($admin)->postJson("/admin/api/schedules/{$schedule->id}/dispatch")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(1, Trip::where('schedule_id', $schedule->id)->count());
    }

    public function test_cancelled_and_invalid_schedules_cannot_dispatch_through_runtime_endpoint(): void
    {
        $admin = $this->actingAsAdmin();
        [$cancelledSchedule] = $this->scheduledDirectionalLeg(['status' => Schedule::STATUS_CANCELLED]);

        $this->actingAs($admin)->postJson("/admin/api/schedules/{$cancelledSchedule->id}/dispatch")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $route = Route::factory()->create();
        $variant = $this->variantFor($route, 'outbound', 'SPED', 'Ligaya', 'pending');
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);
        $invalidSchedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now('Asia/Manila')->toDateString(),
        ]);

        $this->actingAs($admin)->postJson("/admin/api/schedules/{$invalidSchedule->id}/dispatch")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(0, Trip::whereIn('schedule_id', [$cancelledSchedule->id, $invalidSchedule->id])->count());
    }

    public function test_manual_dispatch_remains_independent_after_scheduled_dispatch_endpoint(): void
    {
        $route = Route::factory()->create();
        $variant = $this->variantFor($route, 'outbound', 'SPED', 'Ligaya');
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);

        $trip = SimulationDispatchService::dispatch($bus, $driver, $route, null, 'Manual dispatch.', $variant);

        $this->assertNull($trip->schedule_id);
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        return $user;
    }
    /**
     * @return array{0: Schedule, 1: Bus, 2: Driver, 3: Route, 4: RouteVariant}
     */
    private function scheduledDirectionalLeg(array $overrides = []): array
    {
        $route = Route::factory()->create();
        $variant = $this->variantFor($route, 'outbound', 'SPED', 'Ligaya');
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);
        $schedule = Schedule::factory()->create(array_merge([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now('Asia/Manila')->toDateString(),
            'departure_time' => now('Asia/Manila')->addHours(3)->format('H:i'),
        ], $overrides));

        return [$schedule, $bus, $driver, $route, $variant];
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
