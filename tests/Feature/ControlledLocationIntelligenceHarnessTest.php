<?php

namespace Tests\Feature;

use App\Models\Geofence;
use App\Models\GeofenceTransition;
use App\Models\GPSLog;
use App\Models\Route;
use App\Models\StopArrival;
use App\Models\TripProgress;
use App\Models\VehiclePosition;
use App\Services\Testing\ControlledLocationIntelligenceHarness;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlledLocationIntelligenceHarnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_harness_loads_dense_route_c_sequence_from_database(): void
    {
        $this->seed(RouteSeeder::class);

        $route = Route::with('stops')->findOrFail(3);
        $sequence = app(ControlledLocationIntelligenceHarness::class)->buildRouteCSequence($route);
        $keys = array_column($sequence, 'key');
        $heartbeat = collect($sequence)->firstWhere('key', 'C-heartbeat');
        $last = collect($sequence)->last();

        $this->assertGreaterThan(60, count($sequence));
        $this->assertLessThan(130, count($sequence));
        $this->assertSame('A', $sequence[0]['key']);
        $this->assertNotEmpty(array_filter($keys, fn ($key) => str_starts_with($key, 'B-')));
        $this->assertNotEmpty(array_filter($keys, fn ($key) => str_starts_with($key, 'C-')));
        $this->assertNotEmpty(array_filter($keys, fn ($key) => str_starts_with($key, 'D-depart-')));
        $this->assertNotEmpty(array_filter($keys, fn ($key) => str_starts_with($key, 'D-off-')));
        $this->assertNotEmpty(array_filter($keys, fn ($key) => str_starts_with($key, 'E-return-')));
        $this->assertNotEmpty(array_filter($keys, fn ($key) => str_starts_with($key, 'F-approach-')));
        $this->assertNotEmpty(array_filter($keys, fn ($key) => str_starts_with($key, 'F-')));
        $this->assertSame((float) $route->stops[0]->lat, $sequence[0]['lat']);
        $this->assertTrue($heartbeat['is_cached_fix']);
        $this->assertSame('cached', $heartbeat['speed_source']);
        $this->assertSame((float) $route->stops[1]->lat, $heartbeat['lat']);
        $this->assertSame((float) $route->stops[2]->lng, $last['lng']);
    }

    public function test_harness_posts_all_steps_through_driver_gps_pipeline(): void
    {
        $this->seed(RouteSeeder::class);

        $harness = app(ControlledLocationIntelligenceHarness::class);
        $run = $harness->run();

        $harness->assertRunProcessed($run);
        $this->assertGreaterThan(60, count($run['results']));

        $tripId = $run['trip']['id'];
        $busId = $run['trip']['bus_id'];

        $this->assertSame(count($run['results']), GPSLog::where('trip_id', $tripId)->where('processing_status', 'processed')->count());
        $this->assertNotNull(VehiclePosition::where('bus_id', $busId)->first());
        $this->assertNotNull(TripProgress::where('trip_id', $tripId)->first());
        $this->assertGreaterThanOrEqual(1, StopArrival::where('trip_id', $tripId)->count());
        $this->assertDatabaseMissing('route_deviations', ['trip_id' => $tripId]);
    }

    public function test_cached_heartbeat_inside_stop_does_not_create_duplicate_transition(): void
    {
        $this->seed(RouteSeeder::class);

        $run = app(ControlledLocationIntelligenceHarness::class)->run();
        $tripId = $run['trip']['id'];
        $heartbeat = collect($run['results'])->firstWhere('step', 'C-heartbeat');
        $shawGeofence = Geofence::where('name', 'Shaw Blvd. Crossing')->firstOrFail();

        $this->assertTrue($heartbeat['coordinate_sent']['is_cached_fix']);

        $shawTransitionCount = GeofenceTransition::where('trip_id', $tripId)
            ->where('geofence_id', $shawGeofence->id)
            ->count();

        $this->assertSame(1, $shawTransitionCount);
    }

    public function test_harness_reports_fleet_admin_api_comparison(): void
    {
        $this->seed(RouteSeeder::class);

        $run = app(ControlledLocationIntelligenceHarness::class)->run();
        $final = collect($run['results'])->last();

        $this->assertArrayHasKey('fleet_api', $final);
        $this->assertArrayHasKey('admin_api', $final);
        $this->assertTrue($final['fleet_api']['has_live_telemetry']);
        $this->assertTrue($final['admin_api']['has_live_telemetry']);
        $this->assertNull($final['fleet_api']['next_stop']);
        $this->assertNull($final['admin_api']['next_stop']);
        $this->assertArrayNotHasKey('corridor_distance', $final['fleet_api']);
        $this->assertArrayNotHasKey('route_adherence', $final['fleet_api']);
        $this->assertArrayNotHasKey('corridor_distance', $final['admin_api']);
        $this->assertArrayNotHasKey('route_adherence', $final['admin_api']);
        $this->assertArrayHasKey('fleet_admin_mismatches', $final);
    }
}

