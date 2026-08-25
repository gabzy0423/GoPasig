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
        $response->assertSee('data-load-state="idle"', false);
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
    public function maintenance_fragment_returns_only_the_dashboard_module_root(): void
    {
        $user = User::factory()->create(['role' => 'fleet_manager']);

        $response = $this->actingAs($user)->getJson('/fleet/dashboard?tab=maintenance&fragment=1');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tab', 'maintenance');

        $html = ltrim($response->json('html'));

        $this->assertStringStartsWith('<section id="screen-maintenance"', $html);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $html);
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringContainsString('Fleet Maintenance Logs', $html);
    }

    #[Test]
    public function dispatch_intelligence_fragment_compiles_and_returns_its_module_root(): void
    {
        $user = User::factory()->create(['role' => 'fleet_manager']);

        $response = $this->actingAs($user)->getJson('/fleet/dashboard?tab=dispatch-intelligence&fragment=1');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tab', 'dispatch-intelligence');

        $html = ltrim($response->json('html'));

        $this->assertStringStartsWith('<section id="screen-dispatch-intelligence"', $html);
        $this->assertStringContainsString('Active Commuter Demand Board', $html);
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
        $this->assertStringContainsString('loadGeofenceOverlays(data.geofences)', $html);
        $this->assertStringNotContainsString('routeVariantRouteLookup', $html);
        $this->assertStringNotContainsString('data.variant_corridors', $html);
        $this->assertStringNotContainsString('loadSpatialOverlays', $html);
        $this->assertStringNotContainsString('updateCorridorVisibility', $html);
        $this->assertStringNotContainsString('routeAdherence', $html);
        $this->assertStringNotContainsString('corridorDistance', $html);
        $this->assertStringNotContainsString('data.corridors', $html);
        $this->assertStringContainsString('id="monitor-direction-outbound"', $html);
        $this->assertStringContainsString('id="monitor-direction-inbound"', $html);
        $this->assertStringContainsString('showControl: false', $html);
        $this->assertStringContainsString('setDirectionVisibility(map, direction, visible)', $html);
        $this->assertStringNotContainsString('alignFleetMonitorOfficialRoutesControl', $html);
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

    #[Test]
    public function fleet_navigation_uses_immediate_latest_click_and_retry_states(): void
    {
        $navigation = file_get_contents(public_path('js/fleet-dashboard/navigation.js'));
        $activationStart = strpos($navigation, 'async function activateFleetModule');
        $switchPosition = strpos($navigation, 'switchScreen(screenName);', $activationStart);
        $fetchPosition = strpos($navigation, 'await fetchModuleFragment(screenName);', $activationStart);

        $this->assertNotFalse($activationStart);
        $this->assertNotFalse($switchPosition);
        $this->assertNotFalse($fetchPosition);
        $this->assertLessThan($fetchPosition, $switchPosition);
        $this->assertStringContainsString('const activationId = ++activationSequence;', $navigation);
        $this->assertStringContainsString('activationId !== activationSequence', $navigation);
        $this->assertStringContainsString('data-fleet-module-retry', $navigation);
        $this->assertStringContainsString('loadedScripts.delete(src);', $navigation);
        $this->assertStringContainsString("incoming.dataset.loaded = 'true';", $navigation);
    }

    #[Test]
    public function recurring_fleet_refreshes_use_the_active_screen_polling_lifecycle(): void
    {
        $navigation = file_get_contents(public_path('js/fleet-dashboard/navigation.js'));

        $this->assertStringContainsString('function registerModulePoller', $navigation);
        $this->assertStringContainsString('function syncModulePollers', $navigation);
        $this->assertStringContainsString("document.addEventListener('visibilitychange'", $navigation);
        $this->assertStringContainsString('activeScreenName !== poller.screenName', $navigation);

        foreach ([
            'overview.js' => "registerPoller('overview'",
            'dispatch-intelligence.js' => "registerPoller('dispatch-intelligence'",
            'incidents.js' => "registerPoller('incidents'",
            'commuter-trips.js' => "registerPoller('commuter-trips'",
            'commuter-sessions.js' => "registerPoller('commuter-sessions'",
        ] as $file => $contract) {
            $source = file_get_contents(public_path("js/fleet-dashboard/{$file}"));
            $this->assertStringContainsString($contract, $source, $file);
        }

        $monitor = file_get_contents(resource_path('views/fleet/monitor/index.blade.php'));
        $this->assertStringContainsString("registerPoller('monitor', 'gps-positions'", $monitor);
        $this->assertStringContainsString("registerPoller('monitor', 'gps-age-labels'", $monitor);
    }
}
