<?php

namespace Tests\Feature;

use App\Livewire\Commuter\GeofenceDetector;
use App\Models\Bus;
use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\Route;
use App\Models\Stop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class CommuterSmartBoardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_waiting_journey_boards_after_two_consecutive_same_bus_confirmations(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb4-success');
        $this->createWaitingTrip($token, $route, $origin, $destination);
        $bus = $this->createBus($route, 'PAS-CB4-001', $origin->lat, $origin->lng);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSet('activeTrip.status', 'WAITING')
            ->assertSet('pendingBoardingBusId', $bus->id)
            ->assertSet('pendingBoardingConfirmations', 1)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSet('activeTrip.status', 'ON_BUS')
            ->assertSet('activeTrip.bus_id', $bus->id)
            ->assertSet('activeTrip.bus_plate_number', $bus->plate_number)
            ->assertSet('pendingBoardingBusId', null)
            ->assertSet('pendingBoardingConfirmations', 0);

        $this->assertDatabaseHas('commuter_trips', [
            'session_token' => $token,
            'status' => 'ON_BUS',
            'bus_id' => $bus->id,
            'arrived_at' => null,
        ]);
        $this->assertNotNull(CommuterTrip::where('session_token', $token)->first()->boarded_at);
    }

    public function test_wrong_route_bus_never_boards_commuter(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $wrongRoute = Route::create(['name' => 'Route 2', 'description' => 'Other route', 'status' => 'Active', 'color' => '#111111']);
        $token = $this->createCommuterSession('cb4-wrong-route');
        $this->createWaitingTrip($token, $route, $origin, $destination);
        $this->createBus($wrongRoute, 'PAS-WRONG', $origin->lat, $origin->lng);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSet('activeTrip.status', 'WAITING')
            ->assertSet('pendingBoardingBusId', null);

        $this->assertDatabaseHas('commuter_trips', ['session_token' => $token, 'status' => 'WAITING', 'bus_id' => null]);
    }

    public function test_bus_outside_boarding_radius_does_not_board(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb4-radius');
        $this->createWaitingTrip($token, $route, $origin, $destination);
        $this->createBus($route, 'PAS-FAR', 14.5605, 121.0862);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSet('activeTrip.status', 'WAITING')
            ->assertSet('pendingBoardingBusId', null);
    }

    public function test_already_on_bus_journey_cannot_board_again(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb4-duplicate');
        $assignedBus = $this->createBus($route, 'PAS-ASSIGNED', $origin->lat, $origin->lng);
        $otherBus = $this->createBus($route, 'PAS-OTHER', $origin->lat, $origin->lng);

        CommuterTrip::create([
            'session_token' => $token,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'route_id' => $route->id,
            'status' => 'ON_BUS',
            'bus_id' => $assignedBus->id,
            'boarded_at' => now()->subMinute(),
        ]);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSet('activeTrip.status', 'ON_BUS')
            ->assertSet('activeTrip.bus_id', $assignedBus->id)
            ->assertSet('pendingBoardingBusId', null);

        $this->assertDatabaseMissing('commuter_trips', ['session_token' => $token, 'bus_id' => $otherBus->id]);
    }

    public function test_single_gps_sample_does_not_board_and_lost_candidate_resets_pending_boarding(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb4-jitter');
        $this->createWaitingTrip($token, $route, $origin, $destination);
        $bus = $this->createBus($route, 'PAS-JITTER', $origin->lat, $origin->lng);

        $component = Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSet('activeTrip.status', 'WAITING')
            ->assertSet('pendingBoardingBusId', $bus->id)
            ->assertSet('pendingBoardingConfirmations', 1);

        $bus->update(['lat' => 14.5605, 'lng' => 121.0862]);

        $component->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSet('activeTrip.status', 'WAITING')
            ->assertSet('pendingBoardingBusId', null)
            ->assertSet('pendingBoardingConfirmations', 0);
    }

    public function test_multiple_eligible_buses_select_nearest_then_lowest_id_for_equal_distance(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb4-multiple');
        $this->createWaitingTrip($token, $route, $origin, $destination);
        $first = $this->createBus($route, 'PAS-FIRST', $origin->lat, $origin->lng);
        $this->createBus($route, 'PAS-SECOND', $origin->lat, $origin->lng);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSet('pendingBoardingBusId', $first->id)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSet('activeTrip.status', 'ON_BUS')
            ->assertSet('activeTrip.bus_id', $first->id);
    }

    public function test_lost_gps_keeps_waiting_and_clears_pending_boarding(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb4-gps-loss');
        $this->createWaitingTrip($token, $route, $origin, $destination);
        $bus = $this->createBus($route, 'PAS-GPS', $origin->lat, $origin->lng);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSet('pendingBoardingBusId', $bus->id)
            ->call('updateLocation', $origin->lat, $origin->lng, 9999)
            ->assertSet('activeTrip.status', 'WAITING')
            ->assertSet('pendingBoardingBusId', null)
            ->assertSet('pendingBoardingConfirmations', 0);
    }

    public function test_recovered_waiting_journey_can_board_after_resume(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb4-recovery');
        $this->createWaitingTrip($token, $route, $origin, $destination);
        $bus = $this->createBus($route, 'PAS-RECOVER', $origin->lat, $origin->lng);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->assertSet('activeTrip.status', 'WAITING')
            ->call('recoverJourney', true)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSet('pendingBoardingBusId', $bus->id)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSet('activeTrip.status', 'ON_BUS')
            ->assertSet('activeTrip.bus_id', $bus->id);
    }

    private function seedCanonicalRouteWithStops(): array
    {
        $route = Route::create([
            'name' => 'Route 1',
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
            'radius_meters' => 100,
        ]);

        $destination = Stop::create([
            'route_id' => $route->id,
            'name' => 'Destination Terminal',
            'lat' => 14.5685,
            'lng' => 121.0942,
            'sequence' => 2,
            'radius_meters' => 100,
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

    private function createWaitingTrip(string $token, Route $route, Stop $origin, Stop $destination): CommuterTrip
    {
        return CommuterTrip::create([
            'session_token' => $token,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'route_id' => $route->id,
            'status' => 'WAITING',
        ]);
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
