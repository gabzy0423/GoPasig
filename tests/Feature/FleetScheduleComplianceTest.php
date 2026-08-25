<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetScheduleComplianceTest extends TestCase
{
    use RefreshDatabase;

    private Route $route2;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-14 12:00:00', 'Asia/Manila'));
        $this->route2 = Route::factory()->official('Route 2')->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_retired_schedule_url_redirects_to_fleet_overview(): void
    {
        $this->actingAs($this->fleetManager())
            ->get('/fleet/schedule')
            ->assertRedirect('/fleet/dashboard');
    }

    public function test_fleet_dashboard_no_longer_exposes_schedule_compliance_runtime(): void
    {
        $response = $this->actingAs($this->fleetManager())->get('/fleet/dashboard');

        $response->assertOk();
        $response->assertSee('Trip outcomes today');
        $response->assertDontSee('Schedule compliance');
        $response->assertDontSee('data-nav="schedule"', false);
        $response->assertDontSee('schedule-compliance.js', false);
        $response->assertDontSee('id="screen-schedule"', false);
    }

    public function test_retired_schedule_compliance_api_and_export_are_not_available(): void
    {
        $fleetManager = $this->fleetManager();

        $this->actingAs($fleetManager)
            ->get('/fleet/api/schedule-compliance-data')
            ->assertNotFound();

        $this->actingAs($fleetManager)
            ->get('/fleet/api/schedule-compliance-export')
            ->assertNotFound();
    }

    public function test_overview_trip_outcomes_use_actual_official_route_trips_not_schedule_rows(): void
    {
        $bus = Bus::factory()->create();
        $driver = Driver::factory()->create();
        $legacyRoute = Route::factory()->create([
            'name' => 'Route A',
            'status' => 'Active',
        ]);

        $this->trip($bus, $driver, $this->route2, 'completed', [
            'dispatched_at' => $this->manilaUtc('2026-08-14 08:00:00'),
            'started_at' => $this->manilaUtc('2026-08-14 08:05:00'),
            'ended_at' => $this->manilaUtc('2026-08-14 09:00:00'),
        ]);
        $this->trip($bus, $driver, $this->route2, 'ongoing', [
            'dispatched_at' => $this->manilaUtc('2026-08-14 09:50:00'),
            'started_at' => $this->manilaUtc('2026-08-14 10:00:00'),
            'ended_at' => null,
        ]);
        $this->trip($bus, $driver, $this->route2, 'dispatched', [
            'dispatched_at' => $this->manilaUtc('2026-08-14 11:00:00'),
            'started_at' => null,
            'ended_at' => null,
        ]);
        $this->trip($bus, $driver, $this->route2, 'cancelled', [
            'dispatched_at' => $this->manilaUtc('2026-08-14 11:10:00'),
            'started_at' => null,
            'ended_at' => $this->manilaUtc('2026-08-14 11:30:00'),
        ]);
        $this->trip($bus, $driver, $this->route2, 'completed', [
            'dispatched_at' => $this->manilaUtc('2026-08-13 08:00:00'),
            'started_at' => $this->manilaUtc('2026-08-13 08:05:00'),
            'ended_at' => $this->manilaUtc('2026-08-13 09:00:00'),
        ]);
        $this->trip($bus, $driver, $legacyRoute, 'completed', [
            'dispatched_at' => $this->manilaUtc('2026-08-14 07:00:00'),
            'started_at' => $this->manilaUtc('2026-08-14 07:05:00'),
            'ended_at' => $this->manilaUtc('2026-08-14 08:00:00'),
        ]);

        Schedule::factory()->create([
            'route_id' => $this->route2->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => '2026-08-14',
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $response = $this->actingAs($this->fleetManager())
            ->getJson('/fleet/api/overview-data')
            ->assertOk()
            ->assertJsonPath('tripOutcomes.trips_run', 2)
            ->assertJsonPath('tripOutcomes.completed', 1)
            ->assertJsonPath('tripOutcomes.ongoing', 1)
            ->assertJsonPath('tripOutcomes.dispatched', 1)
            ->assertJsonPath('tripOutcomes.cancelled', 1)
            ->assertJsonPath('tripOutcomes.latest_activity', '11:30 AM')
            ->assertJsonPath('tripOutcomes.as_of', '12:00 PM');

        $this->assertArrayNotHasKey('scheduleCompliance', $response->json());
    }

    private function fleetManager(): User
    {
        return User::factory()->create(['role' => 'fleet_manager']);
    }

    private function trip(Bus $bus, Driver $driver, Route $route, string $status, array $timestamps): Trip
    {
        return Trip::factory()->create(array_merge([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => $status,
        ], $timestamps));
    }

    private function manilaUtc(string $timestamp): Carbon
    {
        return Carbon::parse($timestamp, 'Asia/Manila')->utc();
    }
}
