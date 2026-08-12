<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\BusStatusAuditLog;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Trip;
use App\Models\User;
use App\Services\CentralDispatchEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverLogoutCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_releases_completed_trip_assignment_into_dispatchable_state(): void
    {
        [$user, $driver, $bus, $route] = $this->retainedAssignment();
        $trip = Trip::factory()->create([
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
            'status' => 'completed',
            'gps_session' => 'CLOSED',
            'started_at' => now()->subHour(),
            'ended_at' => now(),
        ]);
        $schedule = Schedule::factory()->create([
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect('/login');
        $this->assertGuest();

        $bus->refresh();
        $driver->refresh();
        $this->assertSame(Bus::STATUS_INACTIVE, $bus->status);
        $this->assertNull($bus->route_id);
        $this->assertSame(Bus::getDefaultDriverName(), $bus->driver_name);
        $this->assertNull($bus->next_stop);
        $this->assertSame(0, $bus->passengers);
        $this->assertSame(0.0, (float) $bus->speed);
        $this->assertNull($bus->eta);

        $this->assertSame('active', $driver->status);
        $this->assertSame('available', $driver->operational_status);
        $this->assertNull($driver->assigned_bus);
        $this->assertNull($driver->assigned_route);
        $this->assertTrue(CentralDispatchEligibilityService::busIsEligible($bus));
        $this->assertTrue(CentralDispatchEligibilityService::driverIsEligible($driver));

        $this->assertSame('completed', $trip->fresh()->status);
        $this->assertSame(Schedule::STATUS_ON_TIME, $schedule->fresh()->status);
        $this->assertDatabaseHas('bus_status_audit_log', [
            'bus_id' => $bus->id,
            'old_status' => 'ready',
            'new_status' => Bus::STATUS_INACTIVE,
            'changed_by' => $user->id,
            'reason' => 'Driver logout released retained assignment',
        ]);
    }

    public function test_logout_is_blocked_without_mutating_an_ongoing_trip(): void
    {
        [$user, $driver, $bus, $route] = $this->retainedAssignment('operating', 'driving');
        $trip = Trip::factory()->create([
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => now(),
            'ended_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->from(route('driver.trip'))
            ->post(route('logout'));

        $response->assertRedirect(route('driver.trip'));
        $response->assertSessionHasErrors('logout');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('ongoing', $trip->fresh()->status);
        $this->assertSame('operating', $bus->fresh()->status);
        $this->assertSame('driving', $driver->fresh()->operational_status);
        $this->assertSame($bus->plate_number, $driver->assigned_bus);
        $this->assertSame($route->id, $bus->route_id);
    }

    public function test_logout_is_blocked_without_mutating_a_dispatched_trip(): void
    {
        [$user, $driver, $bus, $route] = $this->retainedAssignment();
        $trip = Trip::factory()->create([
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
            'status' => 'dispatched',
            'gps_session' => 'OFF',
            'dispatched_at' => now(),
            'started_at' => null,
            'ended_at' => null,
        ]);

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('driver.trip'));
        $response->assertSessionHasErrors('logout');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('dispatched', $trip->fresh()->status);
        $this->assertSame('ready', $bus->fresh()->status);
        $this->assertSame('assigned', $driver->fresh()->operational_status);
    }

    public function test_logout_preserves_breakdown_incident_ownership(): void
    {
        [$user, $driver, $bus, $route] = $this->retainedAssignment('breakdown', 'unavailable');
        Trip::factory()->create([
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $route->id,
            'status' => 'cancelled',
            'gps_session' => 'CLOSED',
            'ended_at' => now(),
        ]);

        $this->actingAs($user)->post(route('logout'))->assertRedirect('/login');

        $this->assertGuest();
        $this->assertSame('breakdown', $bus->fresh()->status);
        $this->assertSame($route->id, $bus->route_id);
        $this->assertSame($bus->plate_number, $driver->fresh()->assigned_bus);
        $this->assertSame('unavailable', $driver->operational_status);
        $this->assertFalse(CentralDispatchEligibilityService::busIsEligible($bus));
        $this->assertFalse(CentralDispatchEligibilityService::driverIsEligible($driver));
        $this->assertSame(0, BusStatusAuditLog::where('bus_id', $bus->id)->count());
    }

    /**
     * @return array{User, Driver, Bus, Route}
     */
    private function retainedAssignment(string $busStatus = 'ready', string $driverStatus = 'assigned'): array
    {
        $user = User::factory()->create(['role' => 'driver']);
        $route = Route::factory()->create();
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'status' => 'active',
            'operational_status' => $driverStatus,
            'assigned_route' => (string) $route->id,
        ]);
        $bus = Bus::factory()->create([
            'plate_number' => 'PAS-LOGOUT',
            'status' => $busStatus,
            'route_id' => $route->id,
            'driver_name' => $driver->name,
            'next_stop' => 'Next Stop',
            'passengers' => 12,
            'speed' => 18,
            'eta' => 4,
        ]);
        $driver->update(['assigned_bus' => $bus->plate_number]);

        return [$user, $driver->fresh(), $bus, $route];
    }
}
