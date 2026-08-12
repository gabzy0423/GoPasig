<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\User;
use Database\Seeders\OfficialPasigRouteSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRouteEditorConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function seedOfficial(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);
    }

    public function test_official_route_editor_payload_exposes_directional_sequences(): void
    {
        $this->seedOfficial();
        $admin = User::factory()->create(['role' => 'admin']);
        $routes = collect($this->actingAs($admin)->getJson('/admin/api/fleet-data')->assertOk()->json('routes'));
        $route = $routes->firstWhere('name', 'Route 2');
        $variants = collect($route['variants']);

        $this->assertSame(20, count($variants->firstWhere('direction', 'outbound')['stops']));
        $this->assertSame(18, count($variants->firstWhere('direction', 'inbound')['stops']));
        $this->assertNotSame(
            collect($variants->firstWhere('direction', 'outbound')['stops'])->pluck('name')->all(),
            collect($variants->firstWhere('direction', 'inbound')['stops'])->pluck('name')->all()
        );
    }

    public function test_legacy_routes_keep_legacy_stop_records(): void
    {
        $this->seed(RouteSeeder::class);

        $this->assertGreaterThan(0, Route::whereIn('name', ['Route A', 'Route B', 'Route C', 'Route D'])
            ->withCount('stops')->get()->sum('stops_count'));
    }

    public function test_route_editor_uses_canonical_variant_preview_sources(): void
    {
        $routesJs = file_get_contents(base_path('public/js/admin-dashboard/routes.js'));
        $editorJs = file_get_contents(base_path('public/js/admin-dashboard/route-editor.js'));

        $this->assertStringContainsString('canonicalRoutes()', $routesJs);
        $this->assertStringContainsString('ensureSelectedCanonicalRoute()', $routesJs);
        $this->assertStringContainsString('defaultOutboundVariant(route)', $routesJs);
        $this->assertStringContainsString("'schematic'", $routesJs);
        $this->assertStringContainsString('activeVariant.polyline_coordinates', $routesJs);
        $this->assertStringContainsString('activeVariant.stops', $routesJs);
        $this->assertStringContainsString('No RouteVariant available', $editorJs);
        $this->assertStringNotContainsString("selectedRouteId = '1'", $routesJs);
        $this->assertStringNotContainsString('route.polyline_coordinates', $routesJs);
        $this->assertStringNotContainsString('route.stops', $routesJs);
        $this->assertStringNotContainsString('Legacy Route Geometry', $editorJs);
    }

    public function test_route_editor_provides_manual_coordinate_assistance_without_places_api(): void
    {
        $editorJs = file_get_contents(base_path('public/js/admin-dashboard/route-editor.js'));

        $this->assertStringContainsString('Open in Google Maps', $editorJs);
        $this->assertStringContainsString('www.google.com/maps/search/?api=1&query=', $editorJs);
        $this->assertStringContainsString('handleVariantCoordinateInput', $editorJs);
        $this->assertStringContainsString('route-variant-stop-lat', $editorJs);
        $this->assertStringContainsString('route-variant-stop-lng', $editorJs);
        $this->assertStringNotContainsString('libraries=places', $editorJs);
        $this->assertStringNotContainsString('maps.googleapis.com/maps/api/geocode', $editorJs);
    }

    public function test_route_cards_do_not_show_unverified_passenger_or_peak_labels(): void
    {
        $routesJs = file_get_contents(base_path('public/js/admin-dashboard/routes.js'));

        $this->assertStringNotContainsString('Avg ${route.avgPax} pax/trip', $routesJs);
        $this->assertStringNotContainsString('Peak: ${route.peakHours}', $routesJs);
        $this->assertStringContainsString('${route.stopSummary}', $routesJs);
        $this->assertStringContainsString('${route.distance}', $routesJs);
        $this->assertStringContainsString('${route.busesCount} buses', $routesJs);
    }

    public function test_routes_and_stops_view_hides_generation_debug_ui(): void
    {
        $blade = file_get_contents(resource_path('views/admin/schedules/index.blade.php'));
        $editorJs = file_get_contents(base_path('public/js/admin-dashboard/route-editor.js'));

        $this->assertStringNotContainsString('routing-providers-health-container', $blade);
        $this->assertStringNotContainsString('route-provider-select', $blade);
        $this->assertStringNotContainsString('btn-generate-route', $blade);
        $this->assertStringNotContainsString('route-preview-proposal-card', $blade);
        $this->assertStringNotContainsString('btn-run-frechet', $blade);
        $this->assertStringNotContainsString('acceptRouteProposal()', $blade);
        $this->assertStringNotContainsString('rejectRouteProposal()', $blade);
        $this->assertStringNotContainsString('Google Directions</option>', $blade);
        $this->assertStringNotContainsString('OSRM Road Router', $blade);

        $this->assertStringNotContainsString('Provider: Google Directions', $editorJs);
        $this->assertStringNotContainsString('setTimeout(fetchProvidersTelemetry', $editorJs);
        $this->assertStringNotContainsString('setInterval(fetchProvidersTelemetry', $editorJs);
        $this->assertStringContainsString("meta.classList.add('hidden');", $editorJs);
        $this->assertStringContainsString("meta.innerHTML = '';", $editorJs);

        $this->assertStringContainsString('id="route-variant-select"', $blade);
        $this->assertStringContainsString('id="route-variant-geometry-meta"', $blade);
        $this->assertStringContainsString('id="rm-timeline-stops-count"', $blade);
        $this->assertStringContainsString('id="rm-stop-timeline-container"', $blade);
        $this->assertStringContainsString('id="rm-simulated-map-container"', $blade);
        $this->assertStringContainsString('id="rm-detail-route-status"', $blade);
        $this->assertStringContainsString('toggleSuspendRouteDetail()', $blade);
    }

    public function test_manual_coordinate_typing_does_not_repopulate_inputs(): void
    {
        $editorJs = file_get_contents(base_path('public/js/admin-dashboard/route-editor.js'));
        preg_match('/function handleVariantCoordinateInput\(\) \{(.*?)\n\}/s', $editorJs, $match);

        $this->assertNotEmpty($match[1] ?? null);
        $this->assertStringContainsString('renderSelectedVariantStopMap(false)', $match[1]);
        $this->assertStringNotContainsString('syncVariantStopEditorFields()', $match[1]);
        $this->assertStringContainsString('isCompleteVariantCoordinatePair', $editorJs);
        $this->assertStringContainsString('(latitude !== 0 || longitude !== 0)', $editorJs);
    }
    public function test_coordinate_edits_survive_background_refresh_and_are_scoped(): void
    {
        $editorJs = file_get_contents(base_path('public/js/admin-dashboard/route-editor.js'));
        $dashboardJs = file_get_contents(base_path('public/js/admin-dashboard/dashboard-data.js'));

        $this->assertStringContainsString('restoreDirtyVariantStopCoordinateEdit()', $dashboardJs);
        $this->assertGreaterThanOrEqual(3, substr_count($editorJs, 'markDirtyVariantStopCoordinateEdit'));
        $this->assertStringContainsString('renderSelectedVariantStopMap(false)', $editorJs);
        $this->assertGreaterThanOrEqual(2, substr_count($editorJs, 'clearDirtyVariantStopCoordinateEdit();'));
        $this->assertStringContainsString('if (String(selectedVariantStopId) !== String(id)) clearDirtyVariantStopCoordinateEdit();', $editorJs);
        $this->assertStringContainsString('dirtyVariantStopCoordinateEdit', $editorJs);
    }
}
