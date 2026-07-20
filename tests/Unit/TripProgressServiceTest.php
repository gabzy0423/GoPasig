<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Trip;
use App\Models\Route;
use App\Models\Stop;
use App\Models\StopArrival;
use App\Models\TripProgress;
use App\Services\Routing\TripProgressService;
use App\Services\ValueObjects\Coordinate;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TripProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_progress_hysteresis_stop_arrival_and_departure()
    {
        config(['fleet.stops.entry_radius_meters' => 30.0]);
        config(['fleet.stops.exit_radius_meters' => 45.0]);

        $route = Route::factory()->create();
        $stop1 = Stop::create(['route_id' => $route->id, 'name' => 'Stop 1', 'lat' => 14.5, 'lng' => 121.0, 'sequence' => 1]);
        $stop2 = Stop::create(['route_id' => $route->id, 'name' => 'Stop 2', 'lat' => 14.6, 'lng' => 121.1, 'sequence' => 2]);

        $trip = Trip::factory()->create(['route_id' => $route->id, 'status' => 'ongoing']);

        $service = new TripProgressService();

        // 1. Position is 10 meters away from Stop 1 (within 30m entry radius)
        $posNear = new Coordinate(14.50005, 121.00005);
        $result = $service->updateProgress($trip->id, $posNear);

        $this->assertEquals($stop1->id, $result->currentStopId);
        $this->assertEquals($stop1->id, $result->lastCompletedStopId);
        $this->assertEquals(1, $result->completedStopsCount);
        $this->assertEquals($stop2->id, $result->nextStopId);

        // Assert StopArrival row exists
        $this->assertTrue(StopArrival::where('trip_id', $trip->id)->where('stop_id', $stop1->id)->exists());

        // 2. Position moves 35 meters away (still inside exit radius of 45m hysteresis)
        $posMid = new Coordinate(14.5002, 121.0002);
        $result = $service->updateProgress($trip->id, $posMid);
        $this->assertEquals($stop1->id, $result->currentStopId); // remains At Stop

        // 3. Position moves 50 meters away (exceeds exit radius of 45m hysteresis)
        $posFar = new Coordinate(14.5004, 121.0004);
        $result = $service->updateProgress($trip->id, $posFar);
        $this->assertNull($result->currentStopId); // departed stop!
        $this->assertEquals($stop1->id, $result->lastCompletedStopId);
    }
}
