<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Route;
use App\Models\Stop;
use App\Models\StopArrival;
use App\Models\TripProgress;
use App\Models\TripLog;
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

        $service = app(TripProgressService::class);

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

    public function test_last_stop_gps_completion_creates_one_trip_log_summary()
    {
        $route = Route::factory()->create();
        $stop1 = Stop::create(['route_id' => $route->id, 'name' => 'Stop 1', 'lat' => 14.5, 'lng' => 121.0, 'sequence' => 1]);
        $stop2 = Stop::create(['route_id' => $route->id, 'name' => 'Stop 2', 'lat' => 14.6, 'lng' => 121.1, 'sequence' => 2]);
        $bus = Bus::factory()->create([
            'status' => 'operating',
            'next_stop' => 'Stop 2',
            'passengers' => 8,
            'speed' => 25,
            'eta' => 4,
        ]);
        $driver = Driver::factory()->create(['operational_status' => 'driving']);

        $trip = Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now()->subMinutes(10),
        ]);

        $service = app(TripProgressService::class);
        $service->updateProgress($trip->id, new Coordinate(14.50005, 121.00005));
        $service->updateProgress($trip->id, new Coordinate(14.60005, 121.10005));

        $trip->refresh();
        $bus->refresh();
        $driver->refresh();
        $tripLog = TripLog::where('trip_id', $trip->id)->first();

        $this->assertSame('completed', $trip->status);
        $this->assertSame('CLOSED', $trip->gps_session);
        $this->assertNotNull($trip->ended_at);
        $this->assertSame('ready', $bus->status);
        $this->assertNull($bus->next_stop);
        $this->assertSame(0, $bus->passengers);
        $this->assertEquals(0, $bus->speed);
        $this->assertNull($bus->eta);
        $this->assertSame('assigned', $driver->operational_status);
        $this->assertNotNull($tripLog);
        $this->assertSame(1, TripLog::where('trip_id', $trip->id)->count());
        $this->assertSame('completed', $tripLog->status);
        $this->assertEquals($trip->ended_at, $tripLog->completed_at);
    }
}

