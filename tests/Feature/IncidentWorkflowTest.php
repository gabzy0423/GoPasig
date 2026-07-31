<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\Route;
use App\Models\SystemSetting;
use App\Models\Trip;
use App\Models\User;
use App\Services\DriverPerformanceService;
use App\Services\RouteStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private $driverUser;
    private $driver;
    private $bus;
    private $route;
    private $trip;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'driver_default_on_time_score' => 100,
            'driver_default_passenger_score' => 100,
            'driver_score_incident_penalty' => 10,
            'driver_performance_rolling_days' => 30,
        ] as $key => $value) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value, 'description' => 'Incident workflow test fixture']
            );
        }

        // 1. Create a route
        $this->route = Route::create([
            'name' => 'Route A',
            'description' => 'Pasig - Ortigas',
            'polyline_coordinates' => [[14.5690, 121.0680]],
            'status' => 'Active',
            'min_buses_required' => 1,
        ]);

        // 2. Create a user with driver role
        $this->driverUser = User::factory()->create(['role' => 'driver']);

        // 3. Create a driver linked to that user
        $this->driver = Driver::create([
            'user_id' => $this->driverUser->id,
            'emp_id' => 'EMP-777',
            'first_name' => 'Mario',
            'last_name' => 'Andretti',
            'license_number' => 'N01-99-888888',
            'license_expiry' => '2028-12-12',
            'status' => 'active',
            'assigned_bus' => 'PAS-777',
            'assigned_route' => $this->route->id,
            'performance_score' => 100,
            'incidents_30' => 0,
        ]);

        // 4. Create an active bus
        $this->bus = Bus::create([
            'plate_number' => 'PAS-777',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5690,
            'lng' => 121.0680,
            'speed' => 0,
            'passengers' => 10,
            'driver_name' => 'Mario Andretti',
            'route_id' => $this->route->id,
        ]);

        // 5. Create an ongoing trip
        $this->trip = Trip::create([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->id,
            'route_id' => $this->route->id,
            'status' => 'ongoing',
            'started_at' => now(),
        ]);
    }

    /** @test */
    public function test_accident_report_causes_breakdown_state_machine_transition_and_cancels_trip()
    {
        $this->actingAs($this->driverUser);

        // Submit accident quick report
        $response = $this->postJson(route('driver.trip.incident'), [
            'type' => Incident::getAccidentType(),
            'description' => 'Minor collision near junction',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Assert Bus Status transitions to breakdown
        $this->bus->refresh();
        $this->assertEquals('breakdown', $this->bus->status);

        // Assert Trip is cancelled
        $this->trip->refresh();
        $this->assertEquals('cancelled', $this->trip->status);

        // Assert Driver assignment and route assignment remain preserved on bus
        $this->assertEquals('Mario Andretti', $this->bus->driver_name);
        $this->assertEquals($this->route->id, $this->bus->route_id);

        // Assert Driver is deactivated but assignments are preserved on the driver record
        $this->driver->refresh();
        $this->assertEquals('active', $this->driver->status);
        $this->assertEquals('unavailable', $this->driver->operational_status);
        $this->assertEquals('PAS-777', $this->driver->assigned_bus);
        $this->assertEquals($this->route->id, $this->driver->assigned_route);

        // Assert incident record is in db
        $this->assertDatabaseHas('incidents', [
            'trip_id' => $this->trip->id,
            'driver_id' => $this->driver->id,
            'type' => Incident::getAccidentType(),
            'status' => 'reported',
        ]);
    }

    /** @test */
    public function test_driver_performance_penalty_is_applied_to_accidents_and_breakdowns_only()
    {
        // Recalculate initially -> score 100
        DriverPerformanceService::recalculate($this->driver->id);
        $this->driver->refresh();
        $this->assertEquals(100, $this->driver->performance_score);

        // 1. Log a Traffic Delay
        Incident::create([
            'trip_id' => $this->trip->id,
            'driver_id' => $this->driver->id,
            'type' => Incident::getTrafficDelayType(),
            'description' => 'Stuck in Ortigas rush hour',
            'status' => 'reported',
            'reported_at' => now(),
        ]);

        DriverPerformanceService::recalculate($this->driver->id);
        $this->driver->refresh();
        // Traffic Delay should NOT penalize the driver
        $this->assertEquals(100, $this->driver->performance_score);
        $this->assertEquals(0, $this->driver->incidents_30);

        // 2. Log a Passenger Concern
        Incident::create([
            'trip_id' => $this->trip->id,
            'driver_id' => $this->driver->id,
            'type' => Incident::getPassengerConcernType(),
            'description' => 'Passenger lost an item',
            'status' => 'reported',
            'reported_at' => now(),
        ]);

        DriverPerformanceService::recalculate($this->driver->id);
        $this->driver->refresh();
        // Passenger Concern should NOT penalize the driver
        $this->assertEquals(100, $this->driver->performance_score);
        $this->assertEquals(0, $this->driver->incidents_30);

        // 3. Log an Accident
        Incident::create([
            'trip_id' => $this->trip->id,
            'driver_id' => $this->driver->id,
            'type' => Incident::getAccidentType(),
            'description' => 'Fender bender',
            'status' => 'reported',
            'reported_at' => now(),
        ]);

        DriverPerformanceService::recalculate($this->driver->id);
        $this->driver->refresh();
        // Accident SHOULD penalize the weighted recalculated score.
        $this->assertEquals(97, $this->driver->performance_score);
        $this->assertEquals(1, $this->driver->incidents_30);

        // 4. Register via registerIncident logic directly
        DriverPerformanceService::registerIncident($this->driver->id, Incident::getTrafficDelayType());
        $this->driver->refresh();
        $this->assertEquals(97, $this->driver->performance_score); // No extra penalty

        DriverPerformanceService::registerIncident($this->driver->id, Incident::getBreakdownType());
        $this->driver->refresh();
        $this->assertEquals(87, $this->driver->performance_score); // Breakdown register path penalizes (-10 points)
    }

    /** @test */
    public function test_fleet_route_health_differentiates_incidents()
    {
        $service = new RouteStatusService();

        // Initially with 1 active bus on route -> On Track
        $this->assertEquals('On Track', $service->getFleetRouteHealth($this->route, 1));

        // 1. Create a Passenger Concern -> Route Health stays On Track
        $concern = Incident::create([
            'trip_id' => $this->trip->id,
            'driver_id' => $this->driver->id,
            'type' => Incident::getPassengerConcernType(),
            'description' => 'Lost key',
            'status' => 'reported',
            'reported_at' => now(),
        ]);

        $this->assertEquals('On Track', $service->getFleetRouteHealth($this->route, 1));

        // 2. Add a Traffic Delay -> Route Health becomes Minor Delay
        $delay = Incident::create([
            'trip_id' => $this->trip->id,
            'driver_id' => $this->driver->id,
            'type' => Incident::getTrafficDelayType(),
            'description' => 'Heavy gridlock',
            'status' => 'reported',
            'reported_at' => now(),
        ]);

        $this->assertEquals('Minor Delay', $service->getFleetRouteHealth($this->route, 1));

        // 3. Add an Accident -> Route Health becomes Disrupted (takes precedence over Minor Delay)
        $accident = Incident::create([
            'trip_id' => $this->trip->id,
            'driver_id' => $this->driver->id,
            'type' => Incident::getAccidentType(),
            'description' => 'Bus hit post',
            'status' => 'reported',
            'reported_at' => now(),
        ]);

        $this->assertEquals('Disrupted', $service->getFleetRouteHealth($this->route, 1));
    }
}
