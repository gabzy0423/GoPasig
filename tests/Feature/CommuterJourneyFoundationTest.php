<?php

namespace Tests\Feature;

use App\Data\CommuterLocation;
use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\Route;
use App\Models\Stop;
use App\Services\Commuter\CommuterJourneyCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CommuterJourneyFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_returns_runtime_context_without_mutating_trip_lifecycle(): void
    {
        Cache::flush();

        $route = Route::create([
            'name' => 'Route 1',
            'description' => 'Canonical route',
            'status' => 'Active',
            'color' => '#003F87',
        ]);

        $origin = Stop::create([
            'route_id' => $route->id,
            'name' => 'Origin Stop',
            'lat' => 14.5,
            'lng' => 121.0,
            'sequence' => 1,
            'radius_meters' => 100,
        ]);

        $destination = Stop::create([
            'route_id' => $route->id,
            'name' => 'Destination Stop',
            'lat' => 14.501,
            'lng' => 121.001,
            'sequence' => 2,
            'radius_meters' => 100,
        ]);

        $token = 'cb1-runtime-context';
        CommuterSession::create([
            'session_token' => $token,
            'expires_at' => now()->addHour(),
        ]);

        CommuterTrip::create([
            'session_token' => $token,
            'route_id' => $route->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'status' => 'WAITING',
        ]);

        $context = app(CommuterJourneyCoordinator::class)
            ->context($token, new CommuterLocation((float) $origin->lat, (float) $origin->lng, 5));

        $this->assertSame($token, $context->session?->session_token);
        $this->assertSame('WAITING', $context->activeTrip?->status);
        $this->assertSame($origin->id, $context->originStop()?->id);
        $this->assertSame($destination->id, $context->destinationStop()?->id);
        $this->assertSame($route->id, $context->route()?->id);
        $this->assertSame($origin->id, $context->nearestStop()?->id);
        $this->assertSame($origin->id, $context->activeStop()?->id);
        $this->assertTrue($context->stopGeofence->isInsideStop());

        $this->assertDatabaseHas('commuter_trips', [
            'session_token' => $token,
            'status' => 'WAITING',
            'bus_id' => null,
            'boarded_at' => null,
            'arrived_at' => null,
        ]);
    }
}