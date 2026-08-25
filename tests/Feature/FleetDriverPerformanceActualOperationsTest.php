<?php

namespace Tests\Feature;

use App\Http\Controllers\Fleet\DriverPerformanceController;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Trip;
use App\Models\TripLog;
use App\Models\TripPassengerEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetDriverPerformanceActualOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-14 12:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_driver_rows_use_actual_trips_logs_events_and_qualifying_incidents(): void
    {
        $officialRoute = Route::factory()->official('Route 2')->create();
        $legacyRoute = Route::factory()->create(['name' => 'Route A', 'status' => 'Active']);
        $driver = Driver::factory()->create([
            'trips_today' => 99,
            'pax_today' => 999,
            'performance_score' => 84,
        ]);
        Schedule::factory()->create([
            'driver_id' => $driver->id,
            'route_id' => $legacyRoute->id,
            'passengers' => 300,
            'service_date' => '2026-08-14',
        ]);

        $completedTrip = $this->trip($driver, $officialRoute, 'completed', [
            'started_at' => $this->at('08:00'),
            'ended_at' => $this->at('08:14'),
            'peak_passengers' => 12,
        ]);
        TripLog::create([
            'driver_id' => $driver->id,
            'trip_id' => $completedTrip->id,
            'bus_id' => $completedTrip->bus_id,
            'route_id' => $officialRoute->id,
            'started_at' => $completedTrip->started_at,
            'completed_at' => $completedTrip->ended_at,
            'passengers' => 4,
            'alighted_passengers' => 2,
            'peak_passengers' => 12,
            'status' => 'completed',
        ]);

        $ongoingTrip = $this->trip($driver, $officialRoute, 'ongoing', [
            'started_at' => $this->at('09:00'),
            'peak_passengers' => 8,
        ]);
        $this->event($ongoingTrip, TripPassengerEvent::TYPE_BOARDED, 3, 3, '09:05');
        $this->event($ongoingTrip, TripPassengerEvent::TYPE_ALIGHTED, 1, 2, '09:10');

        $cancelledTrip = $this->trip($driver, $officialRoute, 'cancelled', [
            'ended_at' => $this->at('10:00'),
            'peak_passengers' => 45,
        ]);
        TripLog::create([
            'driver_id' => $driver->id,
            'trip_id' => $cancelledTrip->id,
            'bus_id' => $cancelledTrip->bus_id,
            'route_id' => $officialRoute->id,
            'completed_at' => $cancelledTrip->ended_at,
            'passengers' => 1,
            'alighted_passengers' => 1,
            'peak_passengers' => 45,
            'status' => 'cancelled',
        ]);

        $dispatchedTrip = $this->trip($driver, $officialRoute, 'dispatched', [
            'dispatched_at' => $this->at('11:00'),
            'peak_passengers' => 40,
        ]);

        $this->trip($driver, $legacyRoute, 'completed', [
            'started_at' => $this->at('08:30'),
            'ended_at' => $this->at('08:45'),
            'peak_passengers' => 50,
        ]);

        Incident::factory()->create([
            'driver_id' => $driver->id,
            'trip_id' => $completedTrip->id,
            'type' => 'Breakdown',
            'reported_at' => $this->at('08:12'),
        ]);
        Incident::factory()->create([
            'driver_id' => $driver->id,
            'trip_id' => $ongoingTrip->id,
            'type' => 'Passenger Concern',
            'reported_at' => $this->at('09:12'),
        ]);

        $rows = app(DriverPerformanceController::class)
            ->buildDriverData('2026-08-14', '2026-08-14');
        $row = collect($rows)->firstWhere('db_id', $driver->id);

        $this->assertNotNull($row);
        $this->assertSame(2, $row['trips_run']);
        $this->assertSame(1, $row['completed_trips']);
        $this->assertSame(1, $row['ongoing_trips']);
        $this->assertSame(1, $row['dispatched_trips']);
        $this->assertSame(1, $row['cancelled_trips']);
        $this->assertSame('Route 2', $row['assigned_route']);
        $this->assertSame(8, $row['recorded_boarded']);
        $this->assertSame(4, $row['recorded_alighted']);
        $this->assertSame(12, $row['peak_load']);
        $this->assertSame(2, $row['incidents']);
        $this->assertSame(1, $row['qualifying_incidents']);
        $this->assertSame(90, $row['performance_score']);
        $this->assertSame('actual_operations', $row['performance_score_basis']);
        $this->assertNotSame('Route A', $row['assigned_route']);
    }

    public function test_driver_without_actual_operations_does_not_use_counters_or_legacy_assignment(): void
    {
        $legacyRoute = Route::factory()->create(['name' => 'Route A', 'status' => 'Active']);
        $driver = Driver::factory()->create([
            'assigned_route' => (string) $legacyRoute->id,
            'trips_today' => 20,
            'pax_today' => 300,
            'performance_score' => 84,
        ]);

        $row = collect(app(DriverPerformanceController::class)
            ->buildDriverData('2026-08-14', '2026-08-14'))
            ->firstWhere('db_id', $driver->id);

        $this->assertNotNull($row);
        $this->assertSame(0, $row['trips_run']);
        $this->assertSame(0, $row['recorded_boarded']);
        $this->assertSame(0, $row['recorded_alighted']);
        $this->assertSame(0, $row['peak_load']);
        $this->assertNull($row['performance_score']);
        $this->assertSame('Unassigned', $row['assigned_route']);
    }

    public function test_driver_details_drawer_uses_actual_trip_records(): void
    {
        $dispatcher = User::factory()->create(['role' => 'fleet_manager']);
        $route = Route::factory()->official('Route 3')->create();
        $driver = Driver::factory()->create(['first_name' => 'Actual', 'last_name' => 'Driver']);
        $trip = $this->trip($driver, $route, 'completed', [
            'started_at' => $this->at('07:00'),
            'ended_at' => $this->at('07:20'),
            'peak_passengers' => 10,
        ]);
        TripLog::create([
            'driver_id' => $driver->id,
            'trip_id' => $trip->id,
            'bus_id' => $trip->bus_id,
            'route_id' => $route->id,
            'started_at' => $trip->started_at,
            'completed_at' => $trip->ended_at,
            'passengers' => 6,
            'alighted_passengers' => 4,
            'peak_passengers' => 10,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($dispatcher)
            ->getJson('/fleet/api/drivers-details/DRV-' . str_pad((string) $driver->id, 4, '0', STR_PAD_LEFT));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('selectedDriver.trips_run', 1)
            ->assertJsonPath('selectedDriver.recorded_boarded', 6)
            ->assertJsonPath('selectedDriver.recorded_alighted', 4)
            ->assertJsonPath('selectedDriverTrips.0.route', 'Route 3')
            ->assertJsonPath('selectedDriverTrips.0.recorded_boarded', 6)
            ->assertJsonPath('selectedDriverTrips.0.recorded_alighted', 4)
            ->assertJsonPath('selectedDriverTrips.0.peak_load', 10);
    }

    private function trip(Driver $driver, Route $route, string $status, array $attributes = []): Trip
    {
        return Trip::factory()->create(array_merge([
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => $status,
            'started_at' => null,
            'ended_at' => null,
            'dispatched_at' => null,
            'peak_passengers' => 0,
        ], $attributes));
    }

    private function event(Trip $trip, string $type, int $delta, int $onboardAfter, string $time): void
    {
        TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'bus_id' => $trip->bus_id,
            'route_id' => $trip->route_id,
            'event_type' => $type,
            'passenger_delta' => $delta,
            'onboard_after' => $onboardAfter,
            'recorded_at' => $this->at($time),
        ]);
    }

    private function at(string $time): Carbon
    {
        return Carbon::parse('2026-08-14 ' . $time, 'Asia/Manila');
    }
}
