<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\RouteGenerationSession;
use App\Models\RouteVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteGenerationMaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_disabled_flag_blocks_route_generation_provider_endpoints(): void
    {
        config(['routing.route_generation_maintenance_enabled' => false]);

        $admin = User::factory()->create(['role' => 'admin']);
        $route = Route::factory()->create([
            'polyline_coordinates' => [[14.5, 121.0], [14.6, 121.1]],
            'geometry_version' => 1,
        ]);
        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'outbound',
            'origin_name' => 'Origin',
            'destination_name' => 'Destination',
            'polyline_coordinates' => [[14.5, 121.0], [14.6, 121.1]],
            'geometry_version' => 1,
            'geometry_status' => 'valid',
            'is_default' => true,
        ]);
        $routeSession = RouteGenerationSession::create([
            'route_id' => $route->id,
            'provider' => 'osrm',
            'generated_geometry' => [[14.55, 121.05], [14.65, 121.15]],
            'comparison_metrics' => [],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);
        $variantSession = RouteGenerationSession::create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'provider' => 'google',
            'generated_geometry' => [[14.55, 121.05], [14.65, 121.15]],
            'comparison_metrics' => [],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.api.routes.telemetry'))
            ->assertNotFound();

        $this->actingAs($admin)
            ->postJson(route('admin.api.routes.geometry.generate_preview', $route), ['provider' => 'osrm'])
            ->assertNotFound();

        $this->actingAs($admin)
            ->postJson(route('admin.api.routes.geometry.accept_preview', $route), [
                'session_id' => $routeSession->id,
                'last_geometry_version' => 1,
            ])
            ->assertNotFound();

        $this->actingAs($admin)
            ->postJson(route('admin.api.routes.geometry.reject_preview', $route), ['session_id' => $routeSession->id])
            ->assertNotFound();

        $this->actingAs($admin)
            ->postJson(route('admin.api.routes.geometry.advanced_analysis', $route), ['session_id' => $routeSession->id])
            ->assertNotFound();

        $this->actingAs($admin)
            ->postJson(route('admin.api.route-variants.geometry.generate_preview', $variant), ['provider' => 'google'])
            ->assertNotFound();

        $this->actingAs($admin)
            ->postJson(route('admin.api.route-variants.geometry.accept_preview', $variant), [
                'session_id' => $variantSession->id,
                'last_geometry_version' => 1,
            ])
            ->assertNotFound();

        $this->actingAs($admin)
            ->postJson(route('admin.api.route-variants.geometry.reject_preview', $variant), ['session_id' => $variantSession->id])
            ->assertNotFound();
    }

    public function test_disabled_generation_mode_does_not_block_normal_route_display_endpoints(): void
    {
        config(['routing.route_generation_maintenance_enabled' => false]);

        $admin = User::factory()->create(['role' => 'admin']);
        $route = Route::factory()->create();
        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'outbound',
            'origin_name' => 'Origin',
            'destination_name' => 'Destination',
            'polyline_coordinates' => [[14.5, 121.0], [14.6, 121.1]],
            'geometry_version' => 1,
            'geometry_status' => 'valid',
            'is_default' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.api.fleet-data'))
            ->assertOk();

        $this->actingAs($admin)
            ->getJson(route('admin.api.route-variants.geometry.history', $variant))
            ->assertOk()
            ->assertJson(['success' => true]);
    }
}
