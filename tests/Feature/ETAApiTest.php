<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Trip;
use App\Models\Route;
use App\Models\Stop;
use App\Models\GPSLog;
use App\Jobs\ProcessGPSJob;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ETAApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_gps_validation_and_processing_triggers_listeners_and_updates_progress_etas()
    {
        $route = Route::factory()->create([
            'polyline_coordinates' => [[14.5, 121.0], [14.55, 121.05], [14.6, 121.1]]
        ]);
        $stop1 = Stop::create(['route_id' => $route->id, 'name' => 'A', 'lat' => 14.55, 'lng' => 121.05, 'sequence' => 1]);

        $trip = Trip::factory()->create(['route_id' => $route->id, 'status' => 'ongoing']);

        // Create log record
        $log = GPSLog::create([
            'trip_id' => $trip->id,
            'lat' => 14.525,
            'lng' => 121.025,
            'speed' => 10.0,
            'heading' => 45.0,
            'accuracy' => 5.0,
            'timestamp' => now(),
            'processing_status' => 'pending'
        ]);

        // Run the process job synchronously
        $job = new ProcessGPSJob($log->id);
        app()->call([$job, 'handle']);

        // Verify GPSLog processed
        $log->refresh();
        $this->assertEquals('processed', $log->processing_status);
        $this->assertNotNull($log->filtered_lat);

        // Verify TripProgress generated upcoming_etas json array
        $this->assertDatabaseHas('trip_progresses', [
            'trip_id' => $trip->id
        ]);

        $progress = \App\Models\TripProgress::where('trip_id', $trip->id)->first();
        $this->assertNotEmpty($progress->upcoming_etas);
        $this->assertEquals($stop1->id, $progress->upcoming_etas[0]['stop_id']);
        $this->assertGreaterThan(0.0, $progress->upcoming_etas[0]['distance_remaining_meters']);
    }
}
