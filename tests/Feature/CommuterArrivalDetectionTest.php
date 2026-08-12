<?php

namespace Tests\Feature;

use App\Data\CommuterLocation;
use App\Livewire\Commuter\GeofenceDetector;
use App\Models\Bus;
use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Stop;
use App\Services\Commuter\CommuterJourneyCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class CommuterArrivalDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_on_bus_journey_arrives_after_two_consecutive_destination_confirmations(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb5-success');
        $bus = $this->createBus($route, 'PAS-CB5-001', $origin->lat, $origin->lng);
        $trip = $this->createOnBusTrip($token, $route, $origin, $destination, $bus);
        $boardedAt = $trip->boarded_at;

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->assertSet('activeTrip.status', 'ON_BUS')
            ->assertSet('pendingArrivalJourneyId', $trip->id)
            ->assertSet('pendingArrivalDestinationStopId', $destination->id)
            ->assertSet('pendingArrivalBusId', $bus->id)
            ->assertSet('pendingArrivalConfirmations', 1)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->assertSet('activeTrip.status', 'ARRIVED')
            ->assertSet('activeTrip.bus_id', $bus->id)
            ->assertSet('completedJourneyPendingReset', true)
            ->assertSeeHtml('wire:poll.5s="resetCompletedJourney"')
            ->assertDispatched('commuter-arrived')
            ->assertSet('pendingArrivalJourneyId', null)
            ->assertSet('pendingArrivalConfirmations', 0)
            ->call('resetCompletedJourney')
            ->assertSet('activeTrip', null)
            ->assertSet('completedJourneyPendingReset', false);

        $completed = $trip->fresh();
        $this->assertSame('ARRIVED', $completed->status);
        $this->assertNotNull($completed->arrived_at);
        $this->assertSame($bus->id, $completed->bus_id);
        $this->assertSame($route->id, $completed->route_id);
        $this->assertSame($origin->id, $completed->origin_stop_id);
        $this->assertSame($destination->id, $completed->destination_stop_id);
        $this->assertTrue($boardedAt->equalTo($completed->boarded_at));
    }

    public function test_first_destination_sample_only_keeps_journey_on_bus(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb5-first-only');
        $bus = $this->createBus($route, 'PAS-FIRST', $origin->lat, $origin->lng);
        $this->createOnBusTrip($token, $route, $origin, $destination, $bus);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->assertSet('activeTrip.status', 'ON_BUS')
            ->assertSet('pendingArrivalConfirmations', 1);

        $this->assertDatabaseHas('commuter_trips', ['session_token' => $token, 'status' => 'ON_BUS', 'arrived_at' => null]);
    }

    public function test_outside_destination_radius_clears_pending_arrival(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb5-outside');
        $bus = $this->createBus($route, 'PAS-OUT', $origin->lat, $origin->lng);
        $this->createOnBusTrip($token, $route, $origin, $destination, $bus);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->assertSet('pendingArrivalConfirmations', 1)
            ->call('updateLocation', 14.5585, 121.0842, 5)
            ->assertSet('activeTrip.status', 'ON_BUS')
            ->assertSet('pendingArrivalJourneyId', null)
            ->assertSet('pendingArrivalConfirmations', 0);
    }

    public function test_gps_jitter_requires_two_fresh_consecutive_destination_samples(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb5-jitter');
        $bus = $this->createBus($route, 'PAS-JITTER', $origin->lat, $origin->lng);
        $this->createOnBusTrip($token, $route, $origin, $destination, $bus);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->assertSet('activeTrip.status', 'ON_BUS')
            ->assertSet('pendingArrivalConfirmations', 1);
    }

    public function test_waiting_journey_never_runs_arrival_completion(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb5-waiting');
        CommuterTrip::create([
            'session_token' => $token,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'route_id' => $route->id,
            'status' => 'WAITING',
        ]);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->assertSet('activeTrip.status', 'WAITING')
            ->assertSet('pendingArrivalConfirmations', 0);

        $this->assertDatabaseHas('commuter_trips', ['session_token' => $token, 'status' => 'WAITING', 'arrived_at' => null]);
    }

    public function test_already_arrived_journey_is_idempotent_and_timestamp_is_preserved(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb5-arrived');
        $bus = $this->createBus($route, 'PAS-DONE', $origin->lat, $origin->lng);
        $arrivedAt = now()->subMinutes(5)->startOfSecond();
        $trip = $this->createOnBusTrip($token, $route, $origin, $destination, $bus);
        $trip->update(['status' => 'ARRIVED', 'arrived_at' => $arrivedAt]);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->assertSet('activeTrip', null)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->assertSet('activeTrip', null)
            ->assertSet('pendingArrivalConfirmations', 0);

        $this->assertTrue($arrivedAt->equalTo($trip->fresh()->arrived_at));
    }

    public function test_missing_destination_fails_safely_and_remains_on_bus(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb5-missing-destination');
        $bus = $this->createBus($route, 'PAS-MISS', $origin->lat, $origin->lng);
        $trip = $this->createOnBusTrip($token, $route, $origin, $destination, $bus);
        $trip->setRelation('destinationStop', null);

        $result = app(CommuterJourneyCoordinator::class)->completeOnboardJourney($trip, $destination, $bus->id);

        $this->assertSame('invalid_destination', $result->reason);
        $this->assertSame('ON_BUS', $trip->fresh()->status);
        $this->assertNull($trip->fresh()->arrived_at);
    }

    public function test_destination_from_wrong_route_does_not_complete(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $wrongRoute = Route::create(['name' => 'Route 3', 'description' => 'Other route', 'status' => 'Active', 'color' => '#111111']);
        $wrongDestination = Stop::create([
            'route_id' => $wrongRoute->id,
            'name' => 'Wrong Destination',
            'lat' => $destination->lat,
            'lng' => $destination->lng,
            'sequence' => 2,
            'radius_meters' => 100,
        ]);
        $token = $this->createCommuterSession('cb5-wrong-route');
        $bus = $this->createBus($route, 'PAS-WRONG', $origin->lat, $origin->lng);
        $trip = $this->createOnBusTrip($token, $route, $origin, $destination, $bus);
        $trip->update(['destination_stop_id' => $wrongDestination->id]);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $wrongDestination->lat, $wrongDestination->lng, 5)
            ->call('updateLocation', $wrongDestination->lat, $wrongDestination->lng, 5)
            ->assertSet('activeTrip.status', 'ON_BUS')
            ->assertSet('pendingArrivalConfirmations', 0);
    }

    public function test_missing_bus_assignment_does_not_complete_or_reassign(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb5-missing-bus');
        $bus = $this->createBus($route, 'PAS-BUS', $origin->lat, $origin->lng);
        $trip = $this->createOnBusTrip($token, $route, $origin, $destination, $bus);
        $trip->update(['bus_id' => null]);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->assertSet('activeTrip.status', 'ON_BUS')
            ->assertSet('activeTrip.bus_id', null)
            ->assertSet('pendingArrivalConfirmations', 0);
    }

    public function test_gps_loss_clears_pending_and_requires_two_fresh_samples(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb5-gps-loss');
        $bus = $this->createBus($route, 'PAS-GPS', $origin->lat, $origin->lng);
        $this->createOnBusTrip($token, $route, $origin, $destination, $bus);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->assertSet('pendingArrivalConfirmations', 1)
            ->call('updateLocation', $destination->lat, $destination->lng, 9999)
            ->assertSet('pendingArrivalConfirmations', 0)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->assertSet('activeTrip.status', 'ON_BUS')
            ->assertSet('pendingArrivalConfirmations', 1)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->assertSet('activeTrip.status', 'ARRIVED');
    }

    public function test_refresh_recovery_restores_on_bus_journey_and_arrival_still_completes(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb5-recovery');
        $bus = $this->createBus($route, 'PAS-RECOVER', $origin->lat, $origin->lng);
        $trip = $this->createOnBusTrip($token, $route, $origin, $destination, $bus);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->assertSet('activeTrip.id', $trip->id)
            ->assertSet('activeTrip.status', 'ON_BUS')
            ->assertSet('activeTrip.bus_id', $bus->id)
            ->call('recoverJourney', true)
            ->assertSet('activeTrip.id', $trip->id)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->assertSet('activeTrip.status', 'ARRIVED');

        $this->assertSame(1, CommuterTrip::where('session_token', $token)->count());
    }

    public function test_repeated_completion_attempts_write_timestamp_once(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb5-repeat');
        $bus = $this->createBus($route, 'PAS-REPEAT', $origin->lat, $origin->lng);
        $trip = $this->createOnBusTrip($token, $route, $origin, $destination, $bus);

        $component = Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->assertSet('activeTrip.status', 'ARRIVED');

        $firstArrivedAt = $trip->fresh()->arrived_at;

        $component->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->assertSet('activeTrip.status', 'ARRIVED');

        $this->assertTrue($firstArrivedAt->equalTo($trip->fresh()->arrived_at));
        $this->assertSame(1, CommuterTrip::where('session_token', $token)->count());
    }

    public function test_origin_stop_proximity_does_not_complete_arrival(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb5-origin');
        $bus = $this->createBus($route, 'PAS-ORIGIN', $origin->lat, $origin->lng);
        $this->createOnBusTrip($token, $route, $origin, $destination, $bus);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSet('activeTrip.status', 'ON_BUS')
            ->assertSet('pendingArrivalConfirmations', 0);
    }

    public function test_assigned_bus_is_preserved_and_operational_bus_state_is_unchanged_after_arrival(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb5-bus-preserved');
        $bus = $this->createBus($route, 'PAS-PRESERVE', $origin->lat, $origin->lng);
        $this->createOnBusTrip($token, $route, $origin, $destination, $bus);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->assertSet('activeTrip.status', 'ARRIVED')
            ->assertSet('activeTrip.bus_id', $bus->id);

        $completed = CommuterTrip::where('session_token', $token)->first();
        $this->assertSame($bus->id, $completed->bus_id);
        $this->assertSame(Bus::STATUS_ACTIVE, $bus->fresh()->status);
        $this->assertSame(0, $bus->fresh()->passengers);
    }

    public function test_coordinator_completion_operation_is_idempotent(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb5-coordinator-idempotent');
        $bus = $this->createBus($route, 'PAS-IDEMP', $origin->lat, $origin->lng);
        $trip = $this->createOnBusTrip($token, $route, $origin, $destination, $bus);
        $coordinator = app(CommuterJourneyCoordinator::class);

        $first = $coordinator->detectArrival($token, new CommuterLocation($destination->lat, $destination->lng, 5));
        $this->assertTrue($first->pending);

        $second = $coordinator->detectArrival(
            $token,
            new CommuterLocation($destination->lat, $destination->lng, 5),
            $first->pendingJourneyId,
            $first->pendingDestinationStopId,
            $first->pendingBusId,
            $first->confirmationCount
        );
        $this->assertTrue($second->arrived);
        $arrivedAt = $trip->fresh()->arrived_at;

        $third = $coordinator->completeOnboardJourney($trip->fresh(), $destination, $bus->id);
        $this->assertSame('already_arrived', $third->reason);
        $this->assertTrue($arrivedAt->equalTo($trip->fresh()->arrived_at));
    }

    public function test_variant_only_inbound_destination_can_complete_arrival(): void
    {
        $route = Route::create([
            'name' => 'Route 2',
            'description' => 'Canonical commuter route',
            'status' => 'Active',
            'color' => '#003F87',
        ]);
        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'inbound',
            'origin_name' => 'Ligaya',
            'destination_name' => 'SPED',
            'geometry_status' => 'valid',
            'is_default' => false,
        ]);
        $origin = RouteVariantStop::create([
            'route_variant_id' => $variant->id,
            'name' => 'Ligaya',
            'lat' => 14.6182022,
            'lng' => 121.0924001,
            'sequence' => 1,
            'radius_meters' => 80,
        ]);
        $destination = RouteVariantStop::create([
            'route_variant_id' => $variant->id,
            'name' => 'SPED',
            'lat' => 14.5603845,
            'lng' => 121.0798618,
            'sequence' => 2,
            'radius_meters' => 80,
        ]);
        $token = $this->createCommuterSession('cb5-inbound-variant');
        $bus = $this->createBus($route, 'PAS-INBOUND-ARRIVAL', $destination->lat, $destination->lng);
        $trip = CommuterTrip::create([
            'session_token' => $token,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'origin_stop_id' => null,
            'origin_route_variant_stop_id' => $origin->id,
            'destination_stop_id' => null,
            'destination_route_variant_stop_id' => $destination->id,
            'status' => 'ON_BUS',
            'bus_id' => $bus->id,
            'boarded_at' => now()->subMinutes(3),
        ]);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->assertSet('pendingArrivalDestinationStopId', $destination->id)
            ->call('updateLocation', $destination->lat, $destination->lng, 5)
            ->assertSet('activeTrip.status', 'ARRIVED')
            ->assertSet('activeTrip.direction', 'inbound');

        $this->assertSame('ARRIVED', $trip->fresh()->status);
        $this->assertNotNull($trip->fresh()->arrived_at);
    }

    private function seedCanonicalRouteWithStops(): array
    {
        $route = Route::create([
            'name' => 'Route 2',
            'description' => 'Canonical commuter route',
            'status' => 'Active',
            'color' => '#003F87',
        ]);

        $origin = Stop::create([
            'route_id' => $route->id,
            'name' => 'Origin Terminal',
            'lat' => 14.5585,
            'lng' => 121.0842,
            'sequence' => 1,
            'radius_meters' => 80,
        ]);

        $destination = Stop::create([
            'route_id' => $route->id,
            'name' => 'Destination Terminal',
            'lat' => 14.5685,
            'lng' => 121.0942,
            'sequence' => 2,
            'radius_meters' => 80,
        ]);

        return [$route, $origin, $destination];
    }

    private function createCommuterSession(string $token): string
    {
        CommuterSession::create([
            'session_token' => $token,
            'expires_at' => now()->addHour(),
        ]);

        return $token;
    }

    private function createOnBusTrip(string $token, Route $route, Stop $origin, Stop $destination, Bus $bus): CommuterTrip
    {
        return CommuterTrip::create([
            'session_token' => $token,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'route_id' => $route->id,
            'status' => 'ON_BUS',
            'bus_id' => $bus->id,
            'boarded_at' => now()->subMinutes(3),
            'arrived_at' => null,
        ])->load(['originStop', 'destinationStop', 'route', 'bus']);
    }

    private function createBus(Route $route, string $plateNumber, float $lat, float $lng): Bus
    {
        return Bus::create([
            'plate_number' => $plateNumber,
            'route_id' => $route->id,
            'driver_name' => 'Test Driver',
            'capacity' => 45,
            'speed' => 0,
            'passengers' => 0,
            'next_stop' => 'Origin Terminal',
            'eta' => 1,
            'lat' => $lat,
            'lng' => $lng,
            'status' => Bus::STATUS_ACTIVE,
        ]);
    }
}
