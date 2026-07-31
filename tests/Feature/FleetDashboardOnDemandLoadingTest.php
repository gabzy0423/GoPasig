<?php

namespace Tests\Feature;

use App\Models\User;
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