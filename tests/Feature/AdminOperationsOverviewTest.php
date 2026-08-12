<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\Route;
use App\Models\ServiceAlert;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\OfficialPasigRouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminOperationsOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function overview_uses_actual_operations_and_compact_official_schedules_without_waiting_demand(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-12 16:00:00', 'Asia/Manila'));
        $this->seed(OfficialPasigRouteSeeder::class);

        $admin = User::factory()->create(['role' => 'admin']);
        $driver = Driver::factory()->create();
        $route2 = Route::where('name', 'Route 2')->firstOrFail();
        $outbound = $route2->variants()->where('direction', 'outbound')->firstOrFail();
        $outbound->update([
            'geometry_status' => 'schematic',
            'polyline_coordinates' => [
                [14.5602934, 121.0797616],
                [14.6185612, 121.0925442],
            ],
        ]);

        $operatingBus = Bus::factory()->create([
            'route_id' => $route2->id,
            'status' => 'operating',
        ]);
        $standbyBus = Bus::factory()->create([
            'route_id' => null,
            'driver_name' => null,
            'status' => Bus::STATUS_INACTIVE,
        ]);
        $readyBus = Bus::factory()->create([
            'route_id' => $route2->id,
            'status' => 'ready',
        ]);
        Bus::factory()->create(['status' => Bus::STATUS_MAINTENANCE]);
        Bus::factory()->create(['status' => Bus::STATUS_BREAKDOWN]);

        Trip::factory()->create([
            'bus_id' => $operatingBus->id,
            'driver_id' => $driver->id,
            'route_id' => $route2->id,
            'route_variant_id' => $outbound->id,
            'status' => 'ongoing',
            'started_at' => Carbon::now('Asia/Manila')->subHour()->utc(),
            'ended_at' => null,
        ]);
        $completedTrip = Trip::factory()->create([
            'bus_id' => $readyBus->id,
            'driver_id' => $driver->id,
            'route_id' => $route2->id,
            'route_variant_id' => $outbound->id,
            'status' => 'completed',
            'started_at' => Carbon::now('Asia/Manila')->subHours(2)->utc(),
            'ended_at' => Carbon::now('Asia/Manila')->subHour()->utc(),
        ]);
        Trip::factory()->create([
            'bus_id' => $standbyBus->id,
            'driver_id' => $driver->id,
            'route_id' => $route2->id,
            'route_variant_id' => $outbound->id,
            'status' => 'completed',
            'started_at' => Carbon::now('Asia/Manila')->subDay()->subHours(2)->utc(),
            'ended_at' => Carbon::now('Asia/Manila')->subDay()->subHour()->utc(),
        ]);

        Incident::factory()->create([
            'trip_id' => $completedTrip->id,
            'driver_id' => $driver->id,
            'status' => 'reported',
            'reported_at' => Carbon::now('Asia/Manila')->utc(),
        ]);
        ServiceAlert::factory()->active()->create([
            'route_id' => $route2->id,
            'affected_routes' => $route2->name,
            'created_at' => Carbon::now('Asia/Manila')->utc(),
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.api.fleet-data'));

        $response->assertOk()
            ->assertJsonPath('overview.metrics.buses_in_service', 1)
            ->assertJsonPath('overview.metrics.completed_today', 1)
            ->assertJsonPath('overview.metrics.under_maintenance', 1)
            ->assertJsonPath('overview.metrics.open_disruptions', 3)
            ->assertJsonPath('overview.fleet_status.total', 5)
            ->assertJsonPath('overview.fleet_status.in_service', 1)
            ->assertJsonPath('overview.fleet_status.standby', 1)
            ->assertJsonPath('overview.fleet_status.unavailable', 3)
            ->assertJsonPath('overview.system_health.state', 'critical')
            ->assertJsonCount(3, 'overview.official_schedules');

        $overview = $response->json('overview');
        $routeNames = collect($overview['official_schedules'])->pluck('route_name')->all();
        $route2Schedule = collect($overview['official_schedules'])->firstWhere('route_name', 'Route 2');
        $route2Outbound = collect($route2Schedule['directions'])->firstWhere('direction', 'outbound');
        $route2MapData = collect($response->json('routes'))->firstWhere('name', 'Route 2');
        $outboundGeometry = collect($route2MapData['map_variant_geometries'])
            ->firstWhere('route_variant_id', $outbound->id);

        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], $routeNames);
        $this->assertSame(
            ['5:30 AM - 9:00 AM', '3:00 PM - 5:00 PM'],
            $route2Outbound['windows']
        );
        $this->assertSame('In service', $route2Outbound['status']);
        $this->assertSame('route_variant', $route2MapData['map_geometry_source']);
        $this->assertSame(
            [[14.5602934, 121.0797616], [14.6185612, 121.0925442]],
            $outboundGeometry['polyline_coordinates']
        );
        $this->assertStringNotContainsString('waiting', strtolower(json_encode($overview)));
    }

    #[Test]
    public function empty_maintenance_api_returns_a_valid_paginated_empty_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson(route('admin.api.maintenance.index'))
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    #[Test]
    public function overview_keeps_the_original_layout_and_only_replaces_existing_panel_content(): void
    {
        $view = file_get_contents(resource_path('views/admin/overview.blade.php'));
        $renderer = file_get_contents(public_path('js/admin-dashboard/overview-map-simulation.js'));

        $this->assertStringContainsString('grid grid-cols-2 gap-4 sm:grid-cols-4', $view);
        $this->assertStringContainsString('grid grid-cols-1 gap-6 lg:grid-cols-3', $view);
        $this->assertStringContainsString('grid grid-cols-1 gap-6 md:grid-cols-3', $view);
        $this->assertSame(2, substr_count($view, 'h-[420px]'));
        $this->assertSame(3, substr_count($view, 'h-[320px]'));
        $this->assertStringContainsString('Official Operating Schedule', $view);
        $this->assertStringContainsString('id="official-schedule-list"', $view);
        $this->assertStringContainsString('Recent Trip Activity', $view);
        $this->assertStringNotContainsString('dispatch-queue-list', $view);
        $this->assertSame(0, preg_match('/\bWAITING\b/i', $view));
        $this->assertStringNotContainsString('dispatchQueueData', $renderer);
        $this->assertSame(0, preg_match('/\bWAITING\b/i', $renderer));
        $this->assertStringNotContainsString('waiting_commuters', $renderer);
        $this->assertStringContainsString('route.map_variant_geometries', $renderer);
        $this->assertStringContainsString('Array.isArray(payload.data)', $renderer);
        $this->assertStringContainsString('No active maintenance schedules pending.', $renderer);
    }
}
