<?php

namespace Tests\Feature;

use App\Data\CommuterLocation;
use App\Livewire\Commuter\GeofenceDetector;
use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\Route;
use App\Models\Stop;
use App\Services\Commuter\CommuterJourneyCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class CommuterJourneyRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_waiting_journey_recovers_on_dashboard_mount_without_duplicate_trip(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb3-refresh');
        $trip = $this->createWaitingTrip($token, $route, $origin, $destination);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->assertSet('journeyRecovered', true)
            ->assertSet('activeTrip.id', $trip->id)
            ->assertSet('activeTrip.status', 'WAITING')
            ->assertSet('activeTrip.destination_stop_id', $destination->id)
            ->assertSet('activeTrip.route_id', $route->id)
            ->assertSet('waitingDurationSeconds', fn ($seconds) => is_int($seconds) && $seconds >= 0);

        $this->assertSame(1, CommuterTrip::where('session_token', $token)->where('status', 'WAITING')->count());
    }

    public function test_repeated_refresh_and_recovery_reuses_existing_waiting_journey(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb3-repeat');
        $this->createWaitingTrip($token, $route, $origin, $destination);

        for ($i = 0; $i < 3; $i++) {
            Livewire::withCookie('commuter_session_token', $token)
                ->test(GeofenceDetector::class)
                ->call('recoverJourney', true)
                ->assertSet('activeTrip.status', 'WAITING');
        }

        $this->assertSame(1, CommuterTrip::where('session_token', $token)->where('status', 'WAITING')->count());
        $this->assertSame(1, CommuterSession::where('session_token', $token)->count());
    }

    public function test_temporary_gps_loss_does_not_cancel_or_transition_waiting_journey(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb3-gps-loss');
        $this->createWaitingTrip($token, $route, $origin, $destination);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 9999)
            ->assertSet('activeTrip.status', 'WAITING')
            ->call('recoverJourney', true)
            ->assertSet('activeTrip.status', 'WAITING')
            ->assertSet('activeTrip.destination_stop_name', $destination->name);

        $this->assertDatabaseHas('commuter_trips', [
            'session_token' => $token,
            'status' => 'WAITING',
            'bus_id' => null,
            'boarded_at' => null,
            'arrived_at' => null,
        ]);
    }

    public function test_gps_resume_updates_runtime_context_without_duplicate_initialization(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb3-resume');
        $this->createWaitingTrip($token, $route, $origin, $destination);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSet('activeTrip.status', 'WAITING')
            ->assertSet('nearestStop.id', $origin->id)
            ->assertSet('activeStop.id', $origin->id)
            ->call('recoverJourney', true)
            ->assertSet('activeTrip.status', 'WAITING');

        $this->assertSame(1, CommuterTrip::where('session_token', $token)->whereIn('status', ['WAITING', 'ON_BUS'])->count());
    }

    public function test_coordinator_runtime_context_exposes_recovery_fields_without_persistence(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb3-context');
        $trip = $this->createWaitingTrip($token, $route, $origin, $destination);

        $runtime = app(CommuterJourneyCoordinator::class)->recoverWaitingRuntime(
            $token,
            new CommuterLocation((float) $origin->lat, (float) $origin->lng, 5)
        );

        $this->assertSame($trip->id, $runtime->journey?->id);
        $this->assertSame($token, $runtime->session?->session_token);
        $this->assertSame($origin->id, $runtime->originStop?->id);
        $this->assertSame($destination->id, $runtime->destinationStop?->id);
        $this->assertSame($route->id, $runtime->route?->id);
        $this->assertSame($origin->id, $runtime->nearestStop?->id);
        $this->assertIsInt($runtime->waitingDurationSeconds);
        $this->assertSame((float) $origin->lat, $runtime->latestCommuterGps?->lat);
        $this->assertDatabaseCount('commuter_trips', 1);
        $this->assertDatabaseCount('commuter_sessions', 1);
    }

    public function test_on_bus_journey_is_recovered_without_new_statuses_or_duplicate_session(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb3-on-bus');

        CommuterTrip::create([
            'session_token' => $token,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'route_id' => $route->id,
            'status' => 'ON_BUS',
        ]);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->assertSet('activeTrip.status', 'ON_BUS')
            ->call('recoverJourney', true)
            ->assertSet('activeTrip.status', 'ON_BUS');

        $this->assertSame(1, CommuterSession::where('session_token', $token)->count());
        $this->assertDatabaseMissing('commuter_trips', ['status' => 'RECOVERING']);
        $this->assertDatabaseMissing('commuter_trips', ['status' => 'PAUSED']);
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
}
