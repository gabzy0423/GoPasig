<?php

namespace Tests\Feature;

use App\Livewire\Commuter\GeofenceDetector;
use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Stop;
use App\Services\Commuter\CommuterJourneyCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
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
        [$route, $origin, $destination, $variant, $variantOrigin, $variantDestination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb2-selector');

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSet('activeStop.route_variant_stop_id', $variantOrigin->id)
            ->assertViewHas('destinationStops', function ($stops) use ($variantOrigin, $variantDestination) {
                $ids = collect($stops)->pluck('selection_key');

                return $ids->contains($variantOrigin->id.':'.$variantDestination->id);
            })
            ->assertSee('Saan ang iyong destinasyon?')
            ->call('updateLocation', 14.1, 121.5, 5)
            ->assertSet('activeStop', null)
            ->assertViewHas('destinationStops', fn ($stops) => collect($stops)->isEmpty())
            ->assertDontSee('Saan ang iyong destinasyon?');
    }

    public function test_duplicate_destination_names_are_labeled_by_stop_sequence_and_terminal_state(): void
    {
        $route = Route::create([
            'name' => 'Route 2',
            'description' => 'Canonical commuter route',
            'status' => 'Active',
            'color' => '#003F87',
        ]);
        $origin = Stop::create([
            'route_id' => $route->id,
            'name' => 'SPED (Caruncho Ave.)',
            'lat' => 14.5602934,
            'lng' => 121.0797616,
            'sequence' => 1,
            'radius_meters' => 100,
        ]);
        $middle = Stop::create([
            'route_id' => $route->id,
            'name' => 'Ligaya (Puregold)',
            'lat' => 14.6096595,
            'lng' => 121.0919772,
            'sequence' => 20,
            'radius_meters' => 100,
        ]);
        $terminal = Stop::create([
            'route_id' => $route->id,
            'name' => 'Ligaya (Puregold)',
            'lat' => 14.6185612,
            'lng' => 121.0925442,
            'sequence' => 21,
            'radius_meters' => 100,
        ]);
        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'outbound',
            'origin_name' => $origin->name,
            'destination_name' => $terminal->name,
            'geometry_status' => 'valid',
            'is_default' => true,
        ]);
        $this->makeVariantStop($variant, $origin, 1);
        $this->makeVariantStop($variant, $middle, 20);
        $this->makeVariantStop($variant, $terminal, 21);
        $token = $this->createCommuterSession('cb2-duplicate-destination');

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSee('Ligaya (Puregold) - Stop 20')
            ->assertSee('Ligaya (Puregold) - Terminal')
            ->assertViewHas('destinationStops', function ($stops) {
                $destinations = collect($stops);

                return $destinations->where('sequence', 20)->first()->is_terminal === false
                    && $destinations->where('sequence', 21)->first()->is_terminal === true;
            });
    }

    public function test_destination_selection_creates_waiting_journey_through_existing_schema(): void
    {
        [$route, $origin, $destination, $variant, $variantOrigin, $variantDestination] = $this->seedCanonicalRouteWithStops();
        $token = $this->createCommuterSession('cb2-create');

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->set('selectedDestinationId', $variantOrigin->id.':'.$variantDestination->id)
            ->call('startCommuterTrip')
            ->assertSet('activeTrip.status', 'WAITING')
            ->assertSet('activeTrip.origin_stop_name', $origin->name)
            ->assertSet('activeTrip.destination_stop_name', $destination->name);

        $this->assertDatabaseHas('commuter_trips', [
            'session_token' => $token,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'origin_route_variant_stop_id' => $variantOrigin->id,
            'destination_route_variant_stop_id' => $variantDestination->id,
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

    public function test_inbound_journey_uses_variant_stops_without_legacy_stop_references(): void
    {
        $route = Route::create([
            'name' => 'Route 2',
            'description' => 'Canonical commuter route',
            'status' => 'Active',
            'color' => '#003F87',
        ]);
        $inbound = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'inbound',
            'origin_name' => 'Ligaya',
            'destination_name' => 'SPED',
            'geometry_status' => 'valid',
            'is_default' => false,
        ]);
        $origin = RouteVariantStop::create([
            'route_variant_id' => $inbound->id,
            'name' => 'Ligaya',
            'lat' => 14.6182022,
            'lng' => 121.0924001,
            'sequence' => 1,
            'radius_meters' => 100,
        ]);
        $destination = RouteVariantStop::create([
            'route_variant_id' => $inbound->id,
            'name' => 'SPED (Caruncho Ave.)',
            'lat' => 14.5603845,
            'lng' => 121.0798618,
            'sequence' => 2,
            'radius_meters' => 100,
        ]);
        $token = $this->createCommuterSession('cb2-inbound');

        Livewire::withCookie('commuter_session_token', $token)
            ->test(GeofenceDetector::class)
            ->call('updateLocation', $origin->lat, $origin->lng, 5)
            ->assertSet('activeStop.route_variant_stop_id', $origin->id)
            ->assertSee('Route 2 - Inbound: SPED (Caruncho Ave.) - Terminal')
            ->set('selectedDestinationId', $origin->id.':'.$destination->id)
            ->call('startCommuterTrip')
            ->assertSet('activeTrip.status', 'WAITING')
            ->assertSet('activeTrip.direction', 'inbound');

        $this->assertDatabaseHas('commuter_trips', [
            'session_token' => $token,
            'route_id' => $route->id,
            'route_variant_id' => $inbound->id,
            'origin_stop_id' => null,
            'destination_stop_id' => null,
            'origin_route_variant_stop_id' => $origin->id,
            'destination_route_variant_stop_id' => $destination->id,
            'status' => 'WAITING',
        ]);
    }

    public function test_legacy_selection_fails_closed_when_more_than_one_variant_matches(): void
    {
        [$route, $origin, $destination] = $this->seedCanonicalRouteWithStops();
        $secondVariant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'inbound',
            'origin_name' => $origin->name,
            'destination_name' => $destination->name,
            'geometry_status' => 'valid',
            'is_default' => false,
        ]);
        $this->makeVariantStop($secondVariant, $origin, 1);
        $this->makeVariantStop($secondVariant, $destination, 2);
        $token = $this->createCommuterSession('cb2-ambiguous');

        try {
            app(CommuterJourneyCoordinator::class)
                ->initializeWaitingJourney($token, $origin->id, $destination->id);
            $this->fail('Ambiguous direction must not create a commuter journey.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('destination', $exception->errors());
        }

        $this->assertDatabaseMissing('commuter_trips', [
            'session_token' => $token,
            'status' => 'WAITING',
        ]);
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

        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'outbound',
            'origin_name' => $origin->name,
            'destination_name' => $destination->name,
            'geometry_status' => 'valid',
            'is_default' => true,
        ]);
        $variantOrigin = $this->makeVariantStop($variant, $origin, 1);
        $variantDestination = $this->makeVariantStop($variant, $destination, 2);

        return [$route, $origin, $destination, $variant, $variantOrigin, $variantDestination];
    }

    private function makeVariantStop(RouteVariant $variant, Stop $stop, int $sequence): RouteVariantStop
    {
        return RouteVariantStop::create([
            'route_variant_id' => $variant->id,
            'canonical_stop_id' => $stop->id,
            'name' => $stop->name,
            'lat' => $stop->lat,
            'lng' => $stop->lng,
            'sequence' => $sequence,
            'radius_meters' => $stop->radius_meters,
        ]);
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
