<?php

namespace Tests\Feature;

use App\Exceptions\RoutingProviderException;
use App\Models\Route;
use App\Models\RouteGenerationSession;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\User;
use App\Services\RouteVariantSelectionService;
use App\Services\Routing\RouteVariantGeometryWorkflow;
use Database\Seeders\OfficialPasigRouteSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteVariantGeometryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_generation_requires_coordinates_and_uses_only_directional_stops(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);
        $variant = Route::where('name', 'Route 1')->firstOrFail()->variants()->where('direction', 'outbound')->firstOrFail();
        $existingGeometry = $variant->polyline_coordinates;

        try {
            app(RouteVariantGeometryWorkflow::class)->generatePreview($variant, 'google');
            $this->fail('Expected missing coordinates to block generation.');
        } catch (RoutingProviderException $e) {
            $this->assertStringContainsString('verified coordinates', $e->getMessage());
        }

        $this->assertSame($existingGeometry, $variant->fresh()->polyline_coordinates);
        $this->assertSame([], RouteGenerationSession::where('route_variant_id', $variant->id)->get()->all());
    }

    public function test_accepting_variant_preview_is_direction_scoped_and_preserves_legacy_route_geometry(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);
        $route = Route::where('name', 'Route 1')->firstOrFail();
        $outbound = $route->variants()->where('direction', 'outbound')->firstOrFail();
        $inbound = $route->variants()->where('direction', 'inbound')->firstOrFail();
        $legacyGeometry = $route->polyline_coordinates;
        $inboundGeometry = [[14.50, 121.01], [14.51, 121.02]];
        $outboundPreview = [[14.52, 121.03], [14.53, 121.04]];
        $inbound->update(['polyline_coordinates' => $inboundGeometry]);
        $admin = User::factory()->create(['role' => 'admin']);

        $session = RouteGenerationSession::create([
            'route_id' => $route->id,
            'route_variant_id' => $outbound->id,
            'provider' => 'google',
            'generated_geometry' => $outboundPreview,
            'comparison_metrics' => ['quality' => ['score' => 100]],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
            'created_by_user_id' => $admin->id,
        ]);

        app(RouteVariantGeometryWorkflow::class)->acceptPreview($session->id, $outbound, $outbound->geometry_version, $admin->id);

        $this->assertSame($outboundPreview, $outbound->fresh()->polyline_coordinates);
        $this->assertSame($inboundGeometry, $inbound->fresh()->polyline_coordinates);
        $this->assertSame($legacyGeometry, $route->fresh()->polyline_coordinates);
        $this->assertSame('authoritative', $outbound->fresh()->geometry_status);
        $this->assertCount(1, $outbound->geometryVersions()->get());
        $this->assertCount(0, $inbound->geometryVersions()->get());
    }

    public function test_approved_variant_is_usable_only_when_directional_stops_are_complete(): void
    {
        $this->seed(RouteSeeder::class);
        $route = Route::findOrFail(2);
        $variant = $route->variants()->firstOrFail();
        $variant->update([
            'polyline_coordinates' => [[14.55, 121.08], [14.56, 121.09]],
            'geometry_status' => 'authoritative',
        ]);
        $variant->stops()->get()->each(function (RouteVariantStop $stop, int $index) {
            $stop->update(['lat' => 14.55 + ($index * 0.001), 'lng' => 121.08 + ($index * 0.001)]);
        });

        $this->assertTrue(app(RouteVariantSelectionService::class)->isUsableForLiveDispatch($variant->fresh()));
    }
}
