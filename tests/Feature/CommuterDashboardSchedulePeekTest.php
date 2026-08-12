<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use App\Models\Schedule;
use App\Models\Trip;
use App\Services\CommuterDashboardCacheService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CommuterDashboardSchedulePeekTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_schedule_peek_uses_official_split_windows_for_midday_gap(): void
    {
        Cache::flush();
        [$route, $outbound, $inbound] = $this->routeWithDirections();
        $this->seedOfficialWindows($route, $outbound, $inbound);
        Carbon::setTestNow(Carbon::parse('2026-08-03 09:01:00', 'Asia/Manila'));

        $peek = collect(app(CommuterDashboardCacheService::class)->dashboardData()['schedulePeek'])
            ->firstWhere('route_name', 'Route 2');

        $this->assertSame('5:30 AM', $peek->first_trip);
        $this->assertSame('6:00 PM', $peek->last_trip);
        $this->assertSame('Starts in 359 min', $peek->service_status);
        $this->assertSame(359, $peek->mins_until_start);
    }

    public function test_dashboard_schedule_peek_marks_service_ended_after_last_official_window(): void
    {
        Cache::flush();
        [$route, $outbound, $inbound] = $this->routeWithDirections();
        $this->seedOfficialWindows($route, $outbound, $inbound);
        Carbon::setTestNow(Carbon::parse('2026-08-03 18:01:00', 'Asia/Manila'));

        $peek = collect(app(CommuterDashboardCacheService::class)->dashboardData()['schedulePeek'])
            ->firstWhere('route_name', 'Route 2');

        $this->assertSame('Service ended', $peek->service_status);
        $this->assertSame(0, $peek->mins_until_start);
    }

    public function test_dashboard_schedule_peek_does_not_use_legacy_schedules_as_official_windows(): void
    {
        Cache::flush();
        [$route] = $this->routeWithDirections();
        Schedule::factory()->create([
            'route_id' => $route->id,
            'service_date' => Carbon::parse('2026-08-03', 'Asia/Manila')->toDateString(),
            'departure_time' => '05:00:00',
            'arrival_time' => '21:00:00',
            'status' => Schedule::STATUS_ON_TIME,
        ]);
        Carbon::setTestNow(Carbon::parse('2026-08-03 08:00:00', 'Asia/Manila'));

        $peek = collect(app(CommuterDashboardCacheService::class)->dashboardData()['schedulePeek'])
            ->firstWhere('route_name', 'Route 2');

        $this->assertSame('No schedules', $peek->first_trip);
        $this->assertSame('No schedules', $peek->last_trip);
        $this->assertSame('Missing configuration', $peek->service_status);
        $this->assertSame(0, $peek->mins_until_start);
    }

    public function test_dashboard_next_eta_stays_tba_when_only_future_legacy_schedule_exists(): void
    {
        Cache::flush();
        [$route, $outbound, $inbound] = $this->routeWithDirections();
        $this->seedOfficialWindows($route, $outbound, $inbound);
        Schedule::factory()->create([
            'route_id' => $route->id,
            'service_date' => Carbon::parse('2026-08-03', 'Asia/Manila')->toDateString(),
            'departure_time' => '08:30:00',
            'arrival_time' => '09:00:00',
            'status' => Schedule::STATUS_ON_TIME,
        ]);
        Carbon::setTestNow(Carbon::parse('2026-08-03 08:00:00', 'Asia/Manila'));

        $data = app(CommuterDashboardCacheService::class)->dashboardData();
        $activeRoute = $data['activeRoutes']->firstWhere('route_name', 'Route 2');
        $peek = $data['schedulePeek']->firstWhere('route_name', 'Route 2');

        $this->assertSame(0, $activeRoute->buses_on_route);
        $this->assertNull($activeRoute->next_eta_minutes);
        $this->assertNull($activeRoute->next_eta_provenance_state);
        $this->assertSame('TBA', $activeRoute->next_eta_label);
        $this->assertSame(0, $activeRoute->completed_trips);
        $this->assertSame(1, $activeRoute->scheduled_trips);
        $this->assertSame('In service', $peek->service_status);
        $this->assertSame('5:30 AM', $peek->first_trip);
    }

    public function test_dashboard_route_progress_uses_completed_trips_today_over_todays_dispatch_schedules(): void
    {
        Cache::flush();
        [$route, $outbound, $inbound] = $this->routeWithDirections();
        $this->seedOfficialWindows($route, $outbound, $inbound);
        Carbon::setTestNow(Carbon::parse('2026-08-03 12:00:00', 'Asia/Manila'));

        Schedule::factory()->create([
            'route_id' => $route->id,
            'service_date' => '2026-08-02',
            'departure_time' => '07:00:00',
            'arrival_time' => '07:30:00',
        ]);
        Schedule::factory()->create([
            'route_id' => $route->id,
            'service_date' => '2026-08-03',
            'departure_time' => '07:00:00',
            'arrival_time' => '07:30:00',
        ]);
        Schedule::factory()->create([
            'route_id' => $route->id,
            'service_date' => '2026-08-03',
            'departure_time' => '15:00:00',
            'arrival_time' => '15:30:00',
        ]);
        Schedule::factory()->create([
            'route_id' => $route->id,
            'service_date' => '2026-08-04',
            'departure_time' => '07:00:00',
            'arrival_time' => '07:30:00',
        ]);
        Trip::factory()->create([
            'route_id' => $route->id,
            'status' => 'completed',
            'ended_at' => Carbon::parse('2026-08-02 09:00:00', 'Asia/Manila'),
        ]);
        Trip::factory()->create([
            'route_id' => $route->id,
            'status' => 'completed',
            'ended_at' => Carbon::parse('2026-08-03 09:00:00', 'Asia/Manila'),
        ]);

        $data = app(CommuterDashboardCacheService::class)->dashboardData();
        $activeRoute = $data['activeRoutes']->firstWhere('route_name', 'Route 2');
        $peek = $data['schedulePeek']->firstWhere('route_name', 'Route 2');

        $this->assertSame(1, $activeRoute->completed_trips);
        $this->assertSame(2, $activeRoute->scheduled_trips);
        $this->assertSame('Starts in 180 min', $peek->service_status);
    }

    private function seedOfficialWindows(Route $route, RouteVariant $outbound, RouteVariant $inbound): void
    {
        $this->schedule($route, $outbound, '05:30:00', '09:00:00');
        $this->schedule($route, $outbound, '15:00:00', '17:00:00');
        $this->schedule($route, $inbound, '06:00:00', '09:00:00');
        $this->schedule($route, $inbound, '15:00:00', '18:00:00');
    }

    private function schedule(Route $route, RouteVariant $variant, string $firstTrip, string $lastTrip): void
    {
        RouteServiceSchedule::create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'first_trip_time' => $firstTrip,
            'last_trip_time' => $lastTrip,
            'service_configuration' => 'with_designated_stops',
            'service_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active' => true,
            'source' => RouteServiceSchedule::SOURCE_BENEFICIARY_OFFICIAL,
        ]);
    }

    private function routeWithDirections(): array
    {
        $route = Route::factory()->create(['name' => 'Route 2', 'status' => 'Active']);
        $outbound = $this->variantFor($route, 'outbound', 'SPED', 'Ligaya');
        $inbound = $this->variantFor($route, 'inbound', 'Ligaya', 'SPED');

        return [$route, $outbound, $inbound];
    }

    private function variantFor(Route $route, string $direction, string $origin, string $destination): RouteVariant
    {
        return RouteVariant::create([
            'route_id' => $route->id,
            'direction' => $direction,
            'origin_name' => $origin,
            'destination_name' => $destination,
            'polyline_coordinates' => [[14.5593, 121.0805], [14.5603, 121.0815]],
            'geometry_version' => 1,
            'geometry_status' => 'valid',
            'is_default' => $direction === 'outbound',
        ]);
    }
}
