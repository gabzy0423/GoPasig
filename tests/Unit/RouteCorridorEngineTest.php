<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Trip;
use App\Models\RouteCorridor;
use App\Models\VehiclePosition;
use App\Models\RouteDeviation;
use App\Models\TripProgress;
use App\Services\ValueObjects\Coordinate;
use App\Services\GeospatialService;
use App\Services\Spatial\RouteCorridorEngine;
use App\Events\RouteDeviationDetected;
use App\Events\RouteRecovered;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RouteCorridorEngineTest extends TestCase
{
    use RefreshDatabase;

    private RouteCorridorEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new RouteCorridorEngine(new GeospatialService());
    }

    public function test_route_corridor_evaluation_triggers_correct_deviations_and_recovery()
    {
        Event::fake([
            RouteDeviationDetected::class,
            RouteRecovered::class
        ]);

        config(['fleet.spatial.corridor_default' => 20.0]);

        $route = Route::factory()->create([
            'polyline_coordinates' => [
                [14.5593, 121.0805],
                [14.5650, 121.0850]
            ]
        ]);

        $bus = Bus::factory()->create(['status' => 'active']);
        $trip = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'status' => 'ongoing'
        ]);

        $corridor = RouteCorridor::create([
            'route_id' => $route->id,
            'buffer_width' => 20.0,
            'source_type' => 'manual',
            'measurement_method' => 'haversine',
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => [
                    [121.0805, 14.5593],
                    [121.0850, 14.5650]
                ]
            ]
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

        // 1. Position is right on route (distance is ~0)
        $coordOnRoute = new Coordinate(14.5593, 121.0805);
        $this->engine->check($position, $coordOnRoute, $corridor, $trip);

        $progress = TripProgress::where('trip_id', $trip->id)->first();
        $this->assertEquals('On Route', $progress->route_adherence);
        $this->assertDatabaseMissing('route_deviations', ['trip_id' => $trip->id]);

        // 2. Minor deviation: 30 meters away (buffer = 20m, 30m is > 20m and <= 50m)
        // Let's compute a coordinate slightly offset to the east from the route start point.
        // Approx 0.00027 degrees east is ~30 meters.
        $coordMinor = new Coordinate(14.5593, 121.08077);
        $this->engine->check($position, $coordMinor, $corridor, $trip);

        $progress->refresh();
        $this->assertEquals('Minor Deviation', $progress->route_adherence);
        $this->assertDatabaseHas('route_deviations', [
            'trip_id' => $trip->id,
            'severity' => 'Minor',
            'resolved_at' => null
        ]);
        Event::assertDispatched(RouteDeviationDetected::class, function ($event) use ($trip) {
            return $event->tripId === $trip->id && $event->severity === 'Minor';
        });

        // 3. Critical deviation: > 100 meters away
        $coordCritical = new Coordinate(14.5593, 121.0830);
        $this->engine->check($position, $coordCritical, $corridor, $trip);

        $progress->refresh();
        $this->assertEquals('Critical Deviation', $progress->route_adherence);
        $this->assertDatabaseHas('route_deviations', [
            'trip_id' => $trip->id,
            'severity' => 'Critical',
            'resolved_at' => null
        ]);

        // 4. Recover back to route
        $this->engine->check($position, $coordOnRoute, $corridor, $trip);

        $progress->refresh();
        $this->assertEquals('On Route', $progress->route_adherence);
        $this->assertDatabaseMissing('route_deviations', [
            'trip_id' => $trip->id,
            'resolved_at' => null
        ]);
        Event::assertDispatched(RouteRecovered::class);
    }
}
