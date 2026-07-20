<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Stop;
use App\Models\Trip;
use App\Models\Geofence;
use App\Models\VehiclePosition;
use App\Enums\GeofenceType;
use App\Enums\SpatialPresenceState;
use App\Services\ValueObjects\Coordinate;
use App\Services\Spatial\GeofenceEngine;
use App\Services\Spatial\Handlers\StopGeofenceHandler;
use App\Events\BusEnteredStop;
use App\Events\BusExitedStop;
use App\Events\StopReached;
use App\Events\StopDeparted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HysteresisJitterTest extends TestCase
{
    use RefreshDatabase;

    public function test_gps_jitter_hysteresis_prevents_duplicate_events()
    {
        Event::fake([
            BusEnteredStop::class,
            BusExitedStop::class,
            StopReached::class,
            StopDeparted::class
        ]);

        config(['fleet.spatial.hysteresis_time_threshold_seconds' => 15]);

        // 1. Setup entities
        $route = Route::factory()->create();
        $bus = Bus::factory()->create(['status' => 'active']);
        $trip = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'status' => 'ongoing'
        ]);

        $stop = Stop::create([
            'route_id' => $route->id,
            'name' => 'SPED Test Stop',
            'lat' => 14.5593,
            'lng' => 121.0805,
            'sequence' => 1
        ]);
        
        $geofence = Geofence::create([
            'name' => 'SPED Test Stop',
            'type' => GeofenceType::STOP,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [14.5593, 121.0805]
            ],
            'radius' => 30.0,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'priority' => 100,
            'status' => 'active'
        ]);

        $position = VehiclePosition::create([
            'bus_id' => $bus->id,
            'trip_id' => $trip->id,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'heading' => 0,
            'speed' => 0,
            'status' => 'Moving',
            'last_updated_at' => now()
        ]);

        $geofenceEngine = app(GeofenceEngine::class);
        $stopHandler = app(StopGeofenceHandler::class);

        $insideCoord = new Coordinate(14.55931, 121.08051); // Inside
        $outsideCoord = new Coordinate(14.5650, 121.0850);  // Outside

        // --- PING 1: Entering Stop ---
        $res1 = $geofenceEngine->check($insideCoord, $geofence, $bus->id);
        $this->assertEquals(SpatialPresenceState::ENTERING, $res1->state);
        
        $stopHandler->handle($position, $geofence, $res1, $trip);
        
        // Assert entered event is fired
        Event::assertDispatched(BusEnteredStop::class, 1);
        Event::assertNotDispatched(BusExitedStop::class);

        // --- PING 2: Jitter Outside (2s elapsed) ---
        $res2 = $geofenceEngine->check($outsideCoord, $geofence, $bus->id);
        $this->assertEquals(SpatialPresenceState::EXIT_PENDING, $res2->state);
        
        $stopHandler->handle($position, $geofence, $res2, $trip);
        
        // Assert NO exit event is fired (retained under temporal grace period)
        Event::assertNotDispatched(BusExitedStop::class);

        // --- PING 3: Jitter Inside again (4s elapsed) ---
        $res3 = $geofenceEngine->check($insideCoord, $geofence, $bus->id);
        $this->assertEquals(SpatialPresenceState::INSIDE, $res3->state);
        
        $stopHandler->handle($position, $geofence, $res3, $trip);

        // Assert NO duplicate entered event
        Event::assertDispatched(BusEnteredStop::class, 1);
        Event::assertNotDispatched(BusExitedStop::class);

        // --- PING 4: Leaves Stop for real (20s elapsed - grace period expired) ---
        // Simulate exit pending state first
        $res4 = $geofenceEngine->check($outsideCoord, $geofence, $bus->id);
        $this->assertEquals(SpatialPresenceState::EXIT_PENDING, $res4->state);
        
        // Manually back-date the exit pending cache to simulate 20 seconds passing
        Cache::put("bus:{$bus->id}:geofence:{$geofence->id}:exit_pending_at", now()->subSeconds(20)->timestamp, 86400);

        // Re-evaluate
        $res5 = $geofenceEngine->check($outsideCoord, $geofence, $bus->id);
        $this->assertEquals(SpatialPresenceState::OUTSIDE, $res5->state);

        $stopHandler->handle($position, $geofence, $res5, $trip);

        // Assert exit event is finally fired
        Event::assertDispatched(BusExitedStop::class, 1);
    }
}
