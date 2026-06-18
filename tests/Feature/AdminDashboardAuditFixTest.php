<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Trip;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminDashboardAuditFixTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fleet_overview_keeps_zero_completed_trips_for_a_real_zero_trip_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-18 09:00:00', 'Asia/Manila'));

        $route = Route::factory()->create();
        $bus = Bus::factory()->create(['status' => 'active', 'passengers' => 10, 'capacity' => 40]);
        $driver = Driver::factory()->create();

        Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'completed',
            'started_at' => Carbon::yesterday('Asia/Manila')->setTime(8, 0),
            'ended_at' => Carbon::yesterday('Asia/Manila')->setTime(9, 0),
        ]);

        $stats = app(DashboardService::class)->getFleetOverviewKpi();

        $this->assertSame(0, $stats['trips_completed']);
        $this->assertSame('-1 vs yesterday', $stats['deltas']->trips_completed_yesterday);

        Carbon::setTestNow();
    }

    #[Test]
    public function active_bus_delta_uses_same_trip_activity_rule_for_today_and_yesterday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-18 09:00:00', 'Asia/Manila'));

        $route = Route::factory()->create();
        $driver = Driver::factory()->create();
        $todayBus = Bus::factory()->create(['status' => 'active']);
        $overnightBus = Bus::factory()->create(['status' => 'active']);
        $yesterdayBus = Bus::factory()->create(['status' => 'active']);

        Trip::factory()->create([
            'bus_id' => $todayBus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'started_at' => Carbon::today('Asia/Manila')->setTime(6, 0),
            'ended_at' => null,
        ]);

        Trip::factory()->create([
            'bus_id' => $overnightBus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'completed',
            'started_at' => Carbon::yesterday('Asia/Manila')->setTime(23, 30),
            'ended_at' => Carbon::today('Asia/Manila')->setTime(0, 30),
        ]);

        Trip::factory()->create([
            'bus_id' => $yesterdayBus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'completed',
            'started_at' => Carbon::yesterday('Asia/Manila')->setTime(8, 0),
            'ended_at' => Carbon::yesterday('Asia/Manila')->setTime(9, 0),
        ]);

        $stats = app(DashboardService::class)->getFleetOverviewKpi();

        $this->assertSame(2, $stats['active_buses']);
        $this->assertSame('+0 vs yesterday', $stats['deltas']->active_buses_yesterday);

        Carbon::setTestNow();
    }
}
