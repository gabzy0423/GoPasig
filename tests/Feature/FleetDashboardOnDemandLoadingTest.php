<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\OfficialPasigRouteSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FleetDashboardOnDemandLoadingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fleet_dashboard_initial_render_defers_hidden_modules(): void
    {
        $user = User::factory()->create(['role' => 'fleet_manager']);

        $response = $this->actingAs($user)->get('/fleet/dashboard');

        $response->assertOk();
        $response->assertSee('GoPasigFleetModuleLoaderConfig', false);
        $response->assertSee('data-fleet-module-placeholder="analytics"', false);
        $response->assertDontSee('id="routePassengersChart"', false);
        $response->assertDontSee('id="demand-board-grid"', false);
        $response->assertDontSee('id="log-type-filter"', false);
        $response->assertDontSee('<script src="/js/fleet-dashboard/analytics.js', false);
        $response->assertDontSee('<script src="/js/fleet-dashboard/maintenance-management.js', false);
        $response->assertDontSee('<script src="/js/fleet-dashboard/dispatch-intelligence.js', false);
        $response->assertDontSee('<script src="/js/echarts.min.js', false);
    }

    #[Test]
    public function fleet_dashboard_fragment_renders_requested_module_on_demand(): void
    {
        $user = User::factory()->create(['role' => 'fleet_manager']);

        $response = $this->actingAs($user)->getJson('/fleet/dashboard?tab=analytics&fragment=1');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tab', 'analytics');

        $html = $response->json('html');
        $this->assertStringContainsString('id="screen-analytics"', $html);
        $this->assertStringContainsString('id="routePassengersChart"', $html);
        $this->assertStringContainsString('GoPasigAnalyticsInitialData', $html);
    }

    #[Test]
    public function monitor_fragment_uses_canonical_route_payload_and_safe_gps_initialization_order(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);

        $user = User::factory()->create(['role' => 'fleet_manager']);

        $response = $this->actingAs($user)->getJson('/fleet/dashboard?tab=monitor&fragment=1');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tab', 'monitor');

        $html = $response->json('html');

        $this->assertStringContainsString('id="screen-monitor"', $html);
        $this->assertStringContainsString('Route 2', $html);
        $this->assertStringContainsString('Route 3', $html);
        $this->assertStringContainsString('Route 4', $html);
        $this->assertStringNotContainsString('Route A', $html);
        $this->assertStringNotContainsString('Route B', $html);
        $this->assertStringNotContainsString('Route C', $html);
        $this->assertStringNotContainsString('Route D', $html);
        $this->assertStringContainsString('map_variant_geometries', $html);
        $this->assertStringContainsString('route_variant_id', $html);
        $this->assertStringContainsString('stops', $html);
        $this->assertStringContainsString('routeVariantRouteLookup', $html);
        $this->assertStringContainsString('data.variant_corridors', $html);
        $this->assertStringContainsString('loadSpatialOverlays(data.geofences, data.variant_corridors)', $html);
        $this->assertStringContainsString('function updateCorridorVisibility()', $html);
        $this->assertStringContainsString('updateCorridorVisibility();', $html);
        $this->assertStringNotContainsString('data.corridors', $html);
        $this->assertStringContainsString('function monitorMapHasVisibleSize()', $html);
        $this->assertStringContainsString('mapElement.offsetWidth > 0 && mapElement.offsetHeight > 0', $html);
        $this->assertStringContainsString('document.addEventListener("DOMContentLoaded", startMonitorMapWhenVisible)', $html);
        $this->assertStringNotContainsString('document.addEventListener("DOMContentLoaded", initMonitorMap)', $html);

        $constantsPosition = strpos($html, 'const GPS_POLL_INTERVAL_MS');
        $initPosition = strpos($html, 'initMonitorMap();');

        $this->assertNotFalse($constantsPosition);
        $this->assertNotFalse($initPosition);
        $this->assertLessThan($initPosition, $constantsPosition);
    }

    #[Test]
    public function commuter_trip_fragment_loads_with_official_route_filter_options(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);

        $user = User::factory()->create(['role' => 'fleet_manager']);

        $response = $this->actingAs($user)->getJson('/fleet/dashboard?tab=commuter-trips&fragment=1');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tab', 'commuter-trips');

        $html = $response->json('html');

        $this->assertStringContainsString('id="screen-commuter-trips"', $html);
        $this->assertStringContainsString('Route 2', $html);
        $this->assertStringContainsString('Route 3', $html);
        $this->assertStringContainsString('Route 4', $html);
        $this->assertStringNotContainsString('Route A', $html);
        $this->assertStringNotContainsString('Route B', $html);
        $this->assertStringNotContainsString('Route C', $html);
        $this->assertStringNotContainsString('Route D', $html);
    }

    #[Test]
    public function deep_linked_dashboard_still_returns_shell_with_placeholder_not_full_module(): void
    {
        $user = User::factory()->create(['role' => 'fleet_manager']);

        $response = $this->actingAs($user)->get('/fleet/dashboard?tab=maintenance');

        $response->assertOk();
        $response->assertSee('data-fleet-module-placeholder="maintenance"', false);
        $response->assertDontSee('id="log-type-filter"', false);
        $response->assertDontSee('<script src="/js/fleet-dashboard/maintenance-management.js', false);
    }
}
