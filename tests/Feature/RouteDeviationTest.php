<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Trip;
use App\Models\Route;
use App\Models\GPSLog;
use App\Jobs\ProcessGPSJob;
use App\Events\RouteDeviationDetected;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RouteDeviationTest extends TestCase
{
    use RefreshDatabase;

    public function test_gps_coordinate_deviation_triggers_deviation_logging()
    {
        Event::fake([RouteDeviationDetected::class]);

        config(['fleet.deviation.minor_meters' => 50.0]);
        config(['fleet.deviation.major_meters' => 150.0]);

        $route = Route::factory()->create([
            'polyline_coordinates' => [[14.5, 121.0], [14.6, 121.1]]
        ]);

        $trip = Trip::factory()->create(['route_id' => $route->id, 'status' => 'ongoing']);

        // A very far coordinate (deviation > 300 meters, but within service bounds: 120.95 to 121.20)
        $log = GPSLog::create([
            'trip_id' => $trip->id,
            'lat' => 14.55,
            'lng' => 121.18, // within east bounds limit of 121.20
            'speed' => 12.0,
            'heading' => 45.0,
            'accuracy' => 5.0,
            'timestamp' => now(),
            'processing_status' => 'pending'
        ]);

        $job = new ProcessGPSJob($log->id);
        app()->call([$job, 'handle']);

        // Verify RouteDeviation record created
        $this->assertDatabaseHas('route_deviations', [
            'trip_id' => $trip->id,
            'severity' => 'Critical'
        ]);

        // Verify event dispatched
        Event::assertDispatched(RouteDeviationDetected::class);

        // Verify TripProgress status updated
        $this->assertDatabaseHas('trip_progresses', [
            'trip_id' => $trip->id,
            'route_adherence' => 'Critical Deviation'
        ]);
    }
}
