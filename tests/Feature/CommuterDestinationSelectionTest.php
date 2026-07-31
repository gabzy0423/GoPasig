<?php

namespace Tests\Feature;

use App\Livewire\Commuter\GeofenceDetector;
use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\Route;
use App\Models\Stop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class CommuterDestinationSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_destination_selector_appears_only_inside_valid_stop_geofence(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb2-selector');

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSet('activeStop.id', $origin->id)
            ->assertViewHas('destinationStops', function ($stops) use ($origin, $destination) {
                $ids = collect($stops)->pluck('id');

                return $ids->contains($destination->id) && ! $ids->contains($origin->id);
            })
            ->assertSee('Saan ang iyong destinasyon?')
            ->call('updateLocation', 14.1, 121.5, 5)
            ->assertSet('activeStop', null)
            ->assertViewHas('destinationStops', fn ($stops) => collect($stops)->isEmpty())
            ->assertDontSee('Saan ang iyong destinasyon?');
    }

    public function test_destination_selection_creates_waiting_journey_through_existing_schema(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb2-create');

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->set('selectedDestinationId', $destination->id)
            ->call('startCommuterTrip')
            ->assertSet('activeTrip.status', 'WAITING')
            ->assertSet('activeTrip.origin_stop_name', $origin->name)
            ->assertSet('activeTrip.destination_stop_name', $destination->name);

        $this->assertDatabaseHas('commuter_trips', [
            'session_token' => $token,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'route_id' => $route->id,
            'status' => 'WAITING',
            'bus_id' => null,
            'boarded_at' => null,
            'arrived_at' => null,
        ]);
    }

    public function test_waiting_journey_is_restored_without_creating_duplicate(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb2-restore');

        CommuterTrip::create([
            'session_token' => $token,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'route_id' => $route->id,
            'status' => 'WAITING',
        ]);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('loadActiveTrip')
            ->assertSet('activeTrip.status', 'WAITING')
            ->assertSet('activeTrip.origin_stop_name', $origin->name)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->set('selectedDestinationId', $destination->id)
            ->call('startCommuterTrip')
            ->assertSet('activeTrip.status', 'WAITING');

        $this->assertSame(1, CommuterTrip::where('session_token', $token)->where('status', 'WAITING')->count());
    }

    public function test_origin_destination_and_invalid_stop_validation_are_friendly(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb2-validation');

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->set('selectedDestinationId', $origin->id)
            ->call('startCommuterTrip')
            ->assertHasErrors('destination');

        Livewire::test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->set('selectedDestinationId', 999999)
            ->call('startCommuterTrip')
            ->assertHasErrors('destination');

        $this->assertDatabaseMissing('commuter_trips', [
            'session_token' => $token,
            'status' => 'WAITING',
        ]);
    }

    public function test_no_canonical_commuter_visible_stops_fails_gracefully_without_fake_destinations(): void
    {
        Route::create([
            'name' => 'Route B',
            'description' => 'Legacy route outside canonical public commuter routes',
            'status' => 'Active',
            'color' => '#003F87',
        ]);

        $token = $this->createCommuterSession('cb2-empty-canonical');
        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', 14.55, 121.05, 5)
            ->assertSet('activeStop', null)
            ->assertViewHas('destinationStops', fn ($stops) => collect($stops)->isEmpty())
            ->assertDontSee('Saan ang iyong destinasyon?');

        $this->assertDatabaseMissing('commuter_trips', [
            'session_token' => $token,
            'status' => 'WAITING',
        ]);
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
}



