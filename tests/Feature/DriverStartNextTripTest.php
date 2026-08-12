<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\GPSLog;
use App\Models\Incident;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Schedule;
use App\Models\Stop;
use App\Models\Trip;
use App\Models\TripProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverStartNextTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbound_completed_trip_starts_new_inbound_trip_without_redispatch(): void
    {
        [$user, $bus, $driver, $route, $outbound, $inbound, $previousTrip] = $this->completedAssignment('outbound');

        GPSLog::create([
            'trip_id' => $previousTrip->id,
            'lat' => 14.5000,
            'lng' => 121.0000,
            'speed' => 0,
            'timestamp' => now(),
            'processing_status' => 'processed',
        ]);

        $response = $this->actingAs($user)->postJson('/driver/trip/next');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('route_variant_id', $inbound->id)
            ->assertJsonPath('direction', 'inbound');

        $previousTrip->refresh();
        $nextTrip = Trip::where('id', '!=', $previousTrip->id)->firstOrFail();

        $this->assertSame('completed', $previousTrip->status);
        $this->assertSame('CLOSED', $previousTrip->gps_session);
        $this->assertSame($outbound->id, $previousTrip->route_variant_id);
        $this->assertSame(1, GPSLog::where('trip_id', $previousTrip->id)->count());

        $this->assertSame('ongoing', $nextTrip->status);
        $this->assertSame('ACTIVE', $nextTrip->gps_session);
        $this->assertSame($bus->id, $nextTrip->bus_id);
        $this->assertSame($driver->id, $nextTrip->driver_id);
        $this->assertSame($route->id, $nextTrip->route_id);
        $this->assertSame($inbound->id, $nextTrip->route_variant_id);
        $this->assertNotNull($nextTrip->started_at);
        $this->assertNotNull($nextTrip->gps_session_started_at);
        $this->assertSame(0, GPSLog::where('trip_id', $nextTrip->id)->count());

        $bus->refresh();
        $driver->refresh();
        $this->assertSame('operating', $bus->status);
        $this->assertSame($route->id, $bus->route_id);
        $this->assertSame($driver->name, $bus->driver_name);
        $this->assertSame('driving', $driver->operational_status);
        $this->assertSame($bus->plate_number, $driver->assigned_bus);
        $this->assertSame((string) $route->id, (string) $driver->assigned_route);
    }

    public function test_inbound_completed_trip_starts_new_outbound_trip(): void
    {
        [$user, , , , $outbound, , $previousTrip] = $this->completedAssignment('inbound');

        $response = $this->actingAs($user)->postJson('/driver/trip/next');

        $response->assertOk()
            ->assertJsonPath('route_variant_id', $outbound->id)
            ->assertJsonPath('direction', 'outbound');

        $this->assertSame('completed', $previousTrip->fresh()->status);
        $this->assertSame('CLOSED', $previousTrip->fresh()->gps_session);
        $this->assertSame(2, Trip::count());
    }

    public function test_repeated_start_next_trip_request_does_not_create_duplicate_trip(): void
    {
        [$user, $bus] = $this->completedAssignment('outbound');

        $this->actingAs($user)->postJson('/driver/trip/next')->assertOk();
        $this->actingAs($user)->postJson('/driver/trip/next')->assertStatus(422);

        $this->assertSame(2, Trip::where('bus_id', $bus->id)->count());
        $this->assertSame(1, Trip::where('bus_id', $bus->id)->where('status', 'ongoing')->count());
    }

    public function test_existing_ongoing_trip_blocks_start_next_trip(): void
    {
        [$user, $bus, $driver, $route] = $this->completedAssignment('outbound');
        Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now(),
        ]);

        $this->actingAs($user)->postJson('/driver/trip/next')->assertStatus(422);
        $this->assertSame(2, Trip::count());
    }

    public function test_existing_dispatched_trip_blocks_start_next_trip(): void
    {
        [$user, $bus, $driver, $route, , $inbound] = $this->completedAssignment('outbound');
        Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'route_variant_id' => $inbound->id,
            'status' => 'dispatched',
            'gps_session' => 'OFF',
            'dispatched_at' => now(),
        ]);

        $this->actingAs($user)->postJson('/driver/trip/next')->assertStatus(422);
        $this->assertSame(2, Trip::count());
    }

    public function test_mismatched_previous_route_variant_is_rejected(): void
    {
        [$user, , , , , , $previousTrip] = $this->completedAssignment('outbound');
        $otherRoute = Route::factory()->create();
        $otherVariant = $this->variantFor($otherRoute, 'outbound', 'Other A', 'Other B');
        $previousTrip->update(['route_variant_id' => $otherVariant->id]);

        $this->actingAs($user)->postJson('/driver/trip/next')->assertStatus(422);
        $this->assertSame(1, Trip::count());
    }

    public function test_missing_opposite_direction_is_rejected(): void
    {
        [$user] = $this->completedAssignment('outbound', false);

        $this->actingAs($user)->postJson('/driver/trip/next')->assertStatus(422);
        $this->assertSame(1, Trip::count());
    }

    public function test_ambiguous_opposite_direction_is_rejected(): void
    {
        [$user, , , $route] = $this->completedAssignment('outbound');
        $this->variantFor($route, 'Inbound', 'Ligaya Alt', 'SPED Alt');

        $this->actingAs($user)->postJson('/driver/trip/next')->assertStatus(422);
        $this->assertSame(1, Trip::count());
    }

    public function test_pending_opposite_direction_is_rejected(): void
    {
        [$user] = $this->completedAssignment('outbound', true, 'pending');

        $this->actingAs($user)->postJson('/driver/trip/next')->assertStatus(422);
        $this->assertSame(1, Trip::count());
    }

    public function test_asset_and_assignment_safety_states_block_start_next_trip(): void
    {
        foreach (['breakdown', 'maintenance', 'inactive'] as $status) {
            [$user, $bus] = $this->completedAssignment('outbound');
            $bus->update(['status' => $status]);

            $this->actingAs($user)->postJson('/driver/trip/next')->assertStatus(422);
            $this->assertSame(1, Trip::where('bus_id', $bus->id)->count());
        }
    }

    public function test_released_assignment_blocks_start_next_trip(): void
    {
        [$user, $bus, $driver] = $this->completedAssignment('outbound');
        $driver->update(['assigned_bus' => null]);

        $this->actingAs($user)->postJson('/driver/trip/next')->assertStatus(400);
        $this->assertSame(1, Trip::where('bus_id', $bus->id)->count());
    }

    public function test_active_major_incident_blocks_start_next_trip(): void
    {
        [$user, , $driver, , , , $previousTrip] = $this->completedAssignment('outbound');
        Incident::create([
            'trip_id' => $previousTrip->id,
            'driver_id' => $driver->id,
            'type' => 'Accident',
            'description' => 'Major incident still open.',
            'status' => 'reported',
            'reported_at' => now(),
        ]);

        $this->actingAs($user)->postJson('/driver/trip/next')->assertStatus(422);
        $this->assertSame(1, Trip::count());
    }

    public function test_controlled_phase2_uat_next_endpoint_uses_deterministic_opposite_variant(): void
    {
        $user = User::factory()->create([
            'role' => 'driver',
            'email' => 'phase2.uat.driver@gopasig.test',
        ]);
        $route = Route::factory()->create([
            'name' => 'Route 2',
            'polyline_coordinates' => [[14.5593000, 121.0805000], [14.5603000, 121.0815000]],
        ]);
        $outbound = $this->variantFor($route, 'outbound', 'PHASE2-UAT Point A', 'PHASE2-UAT Point B');
        $inbound = $this->variantFor($route, 'inbound', 'PHASE2-UAT Point B', 'PHASE2-UAT Point A');
        $bus = Bus::factory()->create([
            'plate_number' => 'PHASE2-UAT-BUS',
            'status' => 'inactive',
        ]);
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'first_name' => 'Phase2',
            'last_name' => 'UAT Driver',
            'emp_id' => 'PHASE2-UAT-DRIVER',
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        $firstTrip = \App\Services\SimulationDispatchService::dispatch(
            $bus,
            $driver,
            $route,
            null,
            'Phase 2 controlled UAT outbound dispatch.',
            $outbound
        );

        app(\App\Services\TripLifecycleService::class)->startTrip($firstTrip);
        app(\App\Services\TripLifecycleService::class)->completeTrip($firstTrip);

        $response = $this->actingAs($user)->postJson('/driver/trip/next');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('route_variant_id', $inbound->id)
            ->assertJsonPath('direction', 'inbound')
            ->assertJsonMissing(['message' => 'Select a direction for this route before dispatching.']);

        $firstTrip->refresh();
        $nextTrip = Trip::where('id', '!=', $firstTrip->id)->firstOrFail();

        $this->assertSame('completed', $firstTrip->status);
        $this->assertSame('CLOSED', $firstTrip->gps_session);
        $this->assertSame($outbound->id, $firstTrip->route_variant_id);
        $this->assertSame('ongoing', $nextTrip->status);
        $this->assertSame('ACTIVE', $nextTrip->gps_session);
        $this->assertSame($inbound->id, $nextTrip->route_variant_id);
        $this->assertSame($bus->id, $nextTrip->bus_id);
        $this->assertSame($driver->id, $nextTrip->driver_id);
        $this->assertSame($route->id, $nextTrip->route_id);
        $this->assertSame(2, Trip::where('bus_id', $bus->id)->count());
        $this->assertSame(1, Trip::where('bus_id', $bus->id)->where('status', 'ongoing')->count());

        $bus->refresh();
        $driver->refresh();
        $this->assertSame('operating', $bus->status);
        $this->assertSame($driver->name, $bus->driver_name);
        $this->assertSame($route->id, $bus->route_id);
        $this->assertSame('driving', $driver->operational_status);
        $this->assertSame($bus->plate_number, $driver->assigned_bus);
        $this->assertSame((string) $route->id, (string) $driver->assigned_route);
    }

    public function test_scheduled_completed_outbound_starts_linked_scheduled_inbound_trip(): void
    {
        [$user, $bus, $driver, $route, $outbound, $inbound, $outboundSchedule, $inboundSchedule, $firstTrip] = $this->completedScheduledAssignment();

        $response = $this->actingAs($user)->postJson('/driver/trip/next');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('route_variant_id', $inbound->id)
            ->assertJsonPath('direction', 'inbound');

        $nextTrip = Trip::where('id', '!=', $firstTrip->id)->firstOrFail();

        $this->assertSame($outboundSchedule->id, $firstTrip->fresh()->schedule_id);
        $this->assertSame($inboundSchedule->id, $nextTrip->schedule_id);
        $this->assertSame('completed', $firstTrip->fresh()->status);
        $this->assertSame('CLOSED', $firstTrip->fresh()->gps_session);
        $this->assertSame('ongoing', $nextTrip->status);
        $this->assertSame('ACTIVE', $nextTrip->gps_session);
        $this->assertSame($bus->id, $nextTrip->bus_id);
        $this->assertSame($driver->id, $nextTrip->driver_id);
        $this->assertSame($route->id, $nextTrip->route_id);
        $this->assertSame($inbound->id, $nextTrip->route_variant_id);
        $this->assertDatabaseCount('trips', 2);
        $this->assertNotNull($outboundSchedule->fresh()->actual_arrival_time);
        $this->assertNotNull($inboundSchedule->fresh()->actual_departure_time);
        $this->assertNull($inboundSchedule->fresh()->actual_arrival_time);

        $bus->refresh();
        $driver->refresh();
        $this->assertSame('operating', $bus->status);
        $this->assertSame($route->id, $bus->route_id);
        $this->assertSame($driver->name, $bus->driver_name);
        $this->assertSame('driving', $driver->operational_status);
        $this->assertSame($bus->plate_number, $driver->assigned_bus);
        $this->assertSame((string) $route->id, (string) $driver->assigned_route);

        app(\App\Services\TripLifecycleService::class)->completeTrip($nextTrip->fresh());
        $this->assertNotNull($inboundSchedule->fresh()->actual_arrival_time);
        $this->assertSame('completed', $nextTrip->fresh()->status);
        $this->assertSame('CLOSED', $nextTrip->fresh()->gps_session);
    }

    public function test_scheduled_next_trip_preview_shows_scheduled_return_leg(): void
    {
        [$user, , , , , , , $inboundSchedule] = $this->completedScheduledAssignment();

        $response = $this->actingAs($user)->get('/driver/trip');

        $response->assertOk();
        $response->assertSee('START NEXT TRIP');
        $response->assertSee('Next Trip');
        $response->assertSee(substr((string) $inboundSchedule->departure_time, 0, 5));
    }

    public function test_scheduled_next_trip_preview_remains_available_as_ad_hoc_when_schedule_is_exhausted(): void
    {
        [$user, , , , , $inbound, , $inboundSchedule] = $this->completedScheduledAssignment();
        $inboundSchedule->delete();

        $response = $this->actingAs($user)->get('/driver/trip');

        $response->assertOk();
        $response->assertSee('START NEXT TRIP');
        $response->assertSee('Next Trip');
        $response->assertSee($inbound->origin_name . ' -&gt; ' . $inbound->destination_name, false);
        $response->assertDontSee('no eligible scheduled return leg');
        $response->assertDontSee('Dispatcher review is required');
    }
    public function test_scheduled_previous_trip_without_matching_return_schedule_starts_ad_hoc_next_trip(): void
    {
        [$user, $bus, $driver, $route, , $inbound, , $inboundSchedule, $firstTrip] = $this->completedScheduledAssignment();
        $inboundSchedule->delete();

        $response = $this->actingAs($user)->postJson('/driver/trip/next');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('route_variant_id', $inbound->id)
            ->assertJsonPath('direction', 'inbound');

        $nextTrip = Trip::where('id', '!=', $firstTrip->id)->firstOrFail();
        $this->assertNull($nextTrip->schedule_id);
        $this->assertSame('ongoing', $nextTrip->status);
        $this->assertSame('ACTIVE', $nextTrip->gps_session);
        $this->assertSame($bus->id, $nextTrip->bus_id);
        $this->assertSame($driver->id, $nextTrip->driver_id);
        $this->assertSame($route->id, $nextTrip->route_id);
        $this->assertSame($inbound->id, $nextTrip->route_variant_id);
    }

    public function test_scheduled_previous_trip_with_ambiguous_return_schedules_blocks_next_trip(): void
    {
        [$user, $bus, $driver, $route, , $inbound, , , $firstTrip] = $this->completedScheduledAssignment();
        Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $inbound->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now('Asia/Manila')->toDateString(),
            'departure_time' => now('Asia/Manila')->addHours(5)->format('H:i'),
            'arrival_time' => now('Asia/Manila')->addHours(6)->format('H:i'),
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $response = $this->actingAs($user)->postJson('/driver/trip/next');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Cannot start next trip: multiple matching return Schedules exist. Dispatcher review is required.']);
        $this->assertSame(0, Trip::where('id', '!=', $firstTrip->id)->count());
    }

    public function test_cancelled_scheduled_return_leg_is_ignored_and_starts_ad_hoc_next_trip(): void
    {
        [$user, , , , , $inbound, , $inboundSchedule, $firstTrip] = $this->completedScheduledAssignment([
            'inbound_status' => Schedule::STATUS_CANCELLED,
        ]);

        $response = $this->actingAs($user)->postJson('/driver/trip/next');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('route_variant_id', $inbound->id)
            ->assertJsonPath('direction', 'inbound');

        $nextTrip = Trip::where('id', '!=', $firstTrip->id)->firstOrFail();
        $this->assertSame(Schedule::STATUS_CANCELLED, $inboundSchedule->fresh()->status);
        $this->assertNull($nextTrip->schedule_id);
        $this->assertSame('ongoing', $nextTrip->status);
        $this->assertSame('ACTIVE', $nextTrip->gps_session);
    }

    public function test_already_linked_scheduled_return_leg_is_ignored_and_starts_ad_hoc_next_trip(): void
    {
        [$user, $bus, $driver, $route, , $inbound, , $inboundSchedule, $firstTrip] = $this->completedScheduledAssignment();
        Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'route_variant_id' => $inbound->id,
            'schedule_id' => $inboundSchedule->id,
            'status' => 'completed',
            'gps_session' => 'CLOSED',
            'dispatched_at' => now()->subHours(4),
            'started_at' => now()->subHours(4),
            'ended_at' => now()->subHours(3),
            'gps_session_started_at' => now()->subHours(4),
        ]);

        $response = $this->actingAs($user)->postJson('/driver/trip/next');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('route_variant_id', $inbound->id)
            ->assertJsonPath('direction', 'inbound');

        $nextTrip = Trip::where('id', '!=', $firstTrip->id)
            ->whereNull('schedule_id')
            ->where('status', 'ongoing')
            ->firstOrFail();

        $this->assertSame($inbound->id, $nextTrip->route_variant_id);
        $this->assertSame(1, Trip::where('schedule_id', $inboundSchedule->id)->count());
    }

    public function test_scheduled_next_trip_duplicate_request_does_not_duplicate_linked_schedule_trip(): void
    {
        [$user, , , , , , , $inboundSchedule] = $this->completedScheduledAssignment();

        $this->actingAs($user)->postJson('/driver/trip/next')->assertOk();
        $this->actingAs($user)->postJson('/driver/trip/next')->assertStatus(422);

        $this->assertSame(1, Trip::where('schedule_id', $inboundSchedule->id)->count());
    }

    public function test_continuous_scheduled_then_ad_hoc_next_trip_sequence_after_schedules_are_exhausted(): void
    {
        [$user, , , , $outbound, $inbound, $outboundSchedule, $inboundSchedule, $firstTrip] = $this->completedScheduledAssignment();
        $lifecycle = app(\App\Services\TripLifecycleService::class);

        $this->actingAs($user)->postJson('/driver/trip/next')->assertOk();
        $secondTrip = Trip::where('id', '!=', $firstTrip->id)->where('status', 'ongoing')->firstOrFail();
        $this->assertSame($inboundSchedule->id, $secondTrip->schedule_id);
        $this->assertSame($inbound->id, $secondTrip->route_variant_id);
        $lifecycle->completeTrip($secondTrip->fresh());

        $this->actingAs($user)->postJson('/driver/trip/next')->assertOk();
        $thirdTrip = Trip::whereNull('schedule_id')->where('status', 'ongoing')->firstOrFail();
        $this->assertSame($outbound->id, $thirdTrip->route_variant_id);
        $lifecycle->completeTrip($thirdTrip->fresh());

        $this->actingAs($user)->postJson('/driver/trip/next')->assertOk();
        $fourthTrip = Trip::whereNull('schedule_id')->where('status', 'ongoing')->firstOrFail();
        $this->assertSame($inbound->id, $fourthTrip->route_variant_id);

        $this->assertSame($outboundSchedule->id, $firstTrip->fresh()->schedule_id);
        $this->assertSame($inboundSchedule->id, $secondTrip->fresh()->schedule_id);
        $this->assertSame(1, Trip::where('schedule_id', $outboundSchedule->id)->count());
        $this->assertSame(1, Trip::where('schedule_id', $inboundSchedule->id)->count());
        $this->assertSame(4, Trip::count());
    }
    public function test_completed_controlled_uat_page_renders_one_next_action_and_inactive_stop_state(): void
    {
        [$user] = $this->completedAssignment('outbound');

        $response = $this->actingAs($user)->get('/driver/trip');
        $html = $response->getContent();

        $response->assertOk();
        preg_match_all('/<button[^>]*>\\s*START NEXT TRIP\\s*<\\/button>/s', $html, $matches);
        $this->assertSame(1, count($matches[0]));
        $response->assertSee('id="btn-toggle-tracking"', false);
        $response->assertDontSee('btn-start-next-trip');
        $response->assertSee('Trip completed — ready for next trip', false);
        $response->assertSee('No active next-stop tracking between legs.');
        $response->assertSee('data-inactive-stop-label', false);
        $this->assertDoesNotMatchRegularExpression('/id="active-stop-label"[^>]*>\s*Trip completed — ready for next trip/s', $html);
        $response->assertDontSee('Not Dispatched yet');
        $response->assertDontSee('5 mins');
        $response->assertSee("fetch(\"" . route('driver.trip.next') . "\"", false);
        $this->assertStringContainsString('window.location.reload();', $html);
        $this->assertStringContainsString('stopLabel.closest(\'[data-dispatch-state="active"]\')', $html);

        $nextTripFunctionStart = strpos($html, 'function startNextTrip()');
        $this->assertNotFalse($nextTripFunctionStart);
        $successBlockStart = strpos($html, 'if (data.success)', $nextTripFunctionStart);
        $successBlockEnd = strpos($html, '} else {', $successBlockStart);
        $successBlock = substr($html, $successBlockStart, $successBlockEnd - $successBlockStart);
        $this->assertStringContainsString('window.location.reload();', $successBlock);
        $this->assertStringNotContainsString('startTelemetry();', $successBlock);
    }

    public function test_active_outbound_trip_renders_authoritative_outbound_stop_order(): void
    {
        [$user, , , , $outbound] = $this->dispatchedUatAssignment('outbound');

        $response = $this->actingAs($user)->get('/driver/trip');
        $html = $response->getContent();

        $response->assertOk();
        $response->assertSee('START TRIP');
        $response->assertSee('Awaiting telemetry');
        $response->assertSee('data-dispatch-state="active"', false);
        $response->assertSee('id="active-stop-label"', false);
        $this->assertStringContainsString('PHASE2-UAT Point A', $html);
        $this->assertStringContainsString('PHASE2-UAT Point B', $html);
        $this->assertLessThan(strpos($html, 'PHASE2-UAT Point B'), strpos($html, 'PHASE2-UAT Point A'));
        $this->assertSame('outbound', $outbound->direction);
    }

    public function test_started_inbound_trip_renders_inbound_stop_order_without_completed_progress_leak(): void
    {
        [$user, $bus, , , $outbound, $inbound, $firstTrip] = $this->dispatchedUatAssignment('outbound');
        app(\App\Services\TripLifecycleService::class)->startTrip($firstTrip);

        $outboundStopB = $outbound->stops()->where('name', 'PHASE2-UAT Point B')->firstOrFail();
        TripProgress::create([
            'trip_id' => $firstTrip->id,
            'next_route_variant_stop_id' => $outboundStopB->id,
            'completed_stops_count' => 1,
            'remaining_stops_count' => 1,
            'trip_percentage' => 50,
            'route_adherence' => 'On Route',
            'current_delay_minutes' => 0,
            'upcoming_etas' => [['eta_timestamp' => now()->addMinutes(7)->toIso8601String()]],
        ]);

        app(\App\Services\TripLifecycleService::class)->completeTrip($firstTrip);
        $this->actingAs($user)->postJson('/driver/trip/next')->assertOk();

        $nextTrip = Trip::where('id', '!=', $firstTrip->id)->firstOrFail();
        $response = $this->actingAs($user)->get('/driver/trip');
        $html = $response->getContent();

        $response->assertOk();
        $response->assertSee('END TRIP');
        $response->assertSee('Awaiting telemetry');
        $response->assertSee('Arriving Next Stop');
        $response->assertSee('data-dispatch-state="active"', false);
        $response->assertDontSee('No active next-stop tracking between legs.');
        $this->assertSame($inbound->id, $nextTrip->route_variant_id);
        $this->assertNull(TripProgress::where('trip_id', $nextTrip->id)->first());
        $this->assertLessThan(strpos($html, 'PHASE2-UAT Point A'), strpos($html, 'PHASE2-UAT Point B'));
        $this->assertSame(2, Trip::where('bus_id', $bus->id)->count());
    }

    /**
     * @return array{0: User, 1: Bus, 2: Driver, 3: Route, 4: RouteVariant, 5: ?RouteVariant, 6: Trip}
     */
    /**
     * @return array{0: User, 1: Bus, 2: Driver, 3: Route, 4: RouteVariant, 5: RouteVariant, 6: Trip}
     */
    private function dispatchedUatAssignment(string $direction): array
    {
        $user = User::factory()->create(['role' => 'driver']);
        $route = Route::factory()->create([
            'name' => 'Route 2',
            'polyline_coordinates' => [[14.5593000, 121.0805000], [14.5603000, 121.0815000]],
        ]);
        $outbound = $this->variantFor($route, 'outbound', 'PHASE2-UAT Point A', 'PHASE2-UAT Point B');
        $inbound = $this->variantFor($route, 'inbound', 'PHASE2-UAT Point B', 'PHASE2-UAT Point A');
        $variant = $direction === 'outbound' ? $outbound : $inbound;
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        $trip = \App\Services\SimulationDispatchService::dispatch(
            $bus,
            $driver,
            $route,
            null,
            'Phase 2 controlled UAT dispatch.',
            $variant
        );

        return [$user, $bus->fresh(), $driver->fresh(), $route, $outbound, $inbound, $trip];
    }
    private function completedScheduledAssignment(array $options = []): array
    {
        $user = User::factory()->create(['role' => 'driver']);
        $route = Route::factory()->create(['name' => 'Route 2']);
        $outbound = $this->variantFor($route, 'outbound', 'SPED', 'Ligaya');
        $inbound = $this->variantFor($route, 'inbound', 'Ligaya', 'SPED');
        $bus = Bus::factory()->create(['status' => 'inactive']);
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'operational_status' => 'available',
        ]);
        $serviceDate = $options['service_date'] ?? now('Asia/Manila')->toDateString();

        $outboundSchedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $outbound->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => $serviceDate,
            'departure_time' => now('Asia/Manila')->subHours(2)->format('H:i'),
            'arrival_time' => now('Asia/Manila')->subHour()->format('H:i'),
            'status' => Schedule::STATUS_ON_TIME,
        ]);
        $inboundSchedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $inbound->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => $serviceDate,
            'departure_time' => now('Asia/Manila')->addHours(3)->format('H:i'),
            'arrival_time' => now('Asia/Manila')->addHours(4)->format('H:i'),
            'status' => $options['inbound_status'] ?? Schedule::STATUS_ON_TIME,
        ]);

        $firstTrip = \App\Services\SimulationDispatchService::dispatchFromSchedule($outboundSchedule, null, 'Phase 3C outbound scheduled dispatch.');
        app(\App\Services\TripLifecycleService::class)->startTrip($firstTrip);
        app(\App\Services\TripLifecycleService::class)->completeTrip($firstTrip->fresh());

        return [$user, $bus->fresh(), $driver->fresh(), $route, $outbound, $inbound, $outboundSchedule, $inboundSchedule, $firstTrip->fresh()];
    }
    private function completedAssignment(string $previousDirection, bool $withOpposite = true, string $oppositeStatus = 'valid'): array
    {
        $user = User::factory()->create(['role' => 'driver']);
        $route = Route::factory()->create(['name' => 'Route 2']);
        $outbound = $this->variantFor($route, 'outbound', 'SPED', 'Ligaya');
        $inbound = $withOpposite ? $this->variantFor($route, 'inbound', 'Ligaya', 'SPED', $oppositeStatus) : null;
        $previousVariant = $previousDirection === 'outbound' ? $outbound : $inbound;

        $bus = Bus::factory()->create(['status' => 'ready', 'route_id' => $route->id]);
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'operational_status' => 'assigned',
            'assigned_bus' => $bus->plate_number,
            'assigned_route' => (string) $route->id,
            'trips_today' => 1,
        ]);
        $bus->update(['driver_name' => $driver->name]);

        $previousTrip = Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'route_variant_id' => $previousVariant?->id,
            'status' => 'completed',
            'gps_session' => 'CLOSED',
            'peak_passengers' => 12,
            'dispatched_at' => now()->subHour(),
            'started_at' => now()->subMinutes(55),
            'ended_at' => now()->subMinutes(5),
            'gps_session_started_at' => now()->subMinutes(55),
        ]);

        return [$user, $bus, $driver, $route, $outbound, $inbound, $previousTrip];
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





