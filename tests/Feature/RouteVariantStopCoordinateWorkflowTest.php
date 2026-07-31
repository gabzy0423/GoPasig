<?php

namespace Tests\Feature;

use App\Exceptions\RoutingProviderException;
use App\Models\Route;
use App\Models\RouteVariantStop;
use App\Models\User;
use App\Services\Routing\RouteVariantGeometryWorkflow;
use Database\Seeders\OfficialPasigRouteSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RouteVariantStopCoordinateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function seedOfficial(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);
    }

    public function test_official_dataset_contains_all_82_directional_stops(): void
    {
        $this->seedOfficial();

        $this->assertSame(82, RouteVariantStop::whereHas('routeVariant.route', fn ($q) => $q->whereIn('name', ['Route 1', 'Route 2', 'Route 3']))->count());
        $this->assertSame([18, 20], Route::where('name', 'Route 1')->firstOrFail()->variants()->orderBy('direction')->withCount('stops')->pluck('stops_count')->sort()->values()->all());
        $this->assertSame([8, 9], Route::where('name', 'Route 2')->firstOrFail()->variants()->withCount('stops')->pluck('stops_count')->sort()->values()->all());
        $this->assertSame([13, 14], Route::where('name', 'Route 3')->firstOrFail()->variants()->withCount('stops')->pluck('stops_count')->sort()->values()->all());
    }

    public function test_coordinate_status_transitions_are_admin_scoped(): void
    {
        $this->seedOfficial();
        $admin = User::factory()->create(['role' => 'admin']);
        $variant = Route::where('name', 'Route 1')->firstOrFail()->variants()->where('direction', 'outbound')->firstOrFail();
        $stop = $variant->stops()->firstOrFail();

        $this->actingAs($admin)->patchJson("/admin/api/route-variants/{$variant->id}/stops/{$stop->id}/coordinates", [
            'lat' => 14.5593, 'lng' => 121.0805, 'coordinate_source' => 'manual map pin', 'coordinate_notes' => 'Candidate review',
        ])->assertOk()->assertJsonPath('stop.coordinate_status', 'candidate');

        $this->actingAs($admin)->postJson("/admin/api/route-variants/{$variant->id}/stops/{$stop->id}/verify", [
            'coordinate_source' => 'official map review',
        ])->assertOk()->assertJsonPath('stop.coordinate_status', 'verified');

        $this->actingAs($admin)->postJson("/admin/api/route-variants/{$variant->id}/stops/{$stop->id}/reject", [
            'coordinate_notes' => 'Pin needs correction',
        ])->assertOk()->assertJsonPath('stop.coordinate_status', 'rejected');
        $this->assertNull($stop->fresh()->lat);
    }

    public function test_candidate_pending_and_rejected_stops_block_generation_with_exact_blockers(): void
    {
        $this->seedOfficial();
        $variant = Route::where('name', 'Route 2')->firstOrFail()->variants()->where('direction', 'outbound')->firstOrFail();
        $stop = $variant->stops()->firstOrFail();

        foreach (['candidate', 'pending', 'rejected'] as $status) {
            $stop->update(['lat' => 14.5593, 'lng' => 121.0805, 'coordinate_status' => $status]);
            try {
                app(RouteVariantGeometryWorkflow::class)->generatePreview($variant->fresh(), 'google');
                $this->fail("Expected {$status} stop to block generation.");
            } catch (RoutingProviderException $e) {
                $this->assertStringContainsString($stop->name, $e->getMessage());
                $this->assertStringContainsString("[{$status}]", $e->getMessage());
            }
        }
    }

    public function test_only_fully_verified_ordered_stops_permit_google_generation(): void
    {
        $this->seedOfficial();
        Http::fake(['maps.googleapis.com/*' => Http::response([
            'status' => 'OK',
            'routes' => [['overview_polyline' => ['points' => '_`owA_yoaV_pR_pR']]],
        ], 200)]);
        $variant = Route::where('name', 'Route 2')->firstOrFail()->variants()->where('direction', 'outbound')->firstOrFail();
        $variant->stops()->get()->each(function (RouteVariantStop $stop, int $index) {
            $stop->update(['lat' => 14.55 + $index * 0.001, 'lng' => 121.08 + $index * 0.001, 'coordinate_status' => 'verified']);
        });

        $preview = app(RouteVariantGeometryWorkflow::class)->generatePreview($variant->fresh(), 'google');

        $this->assertSame($variant->id, $preview->routeVariantId);
    }

    public function test_stop_verification_is_scoped_and_same_names_are_not_synchronized(): void
    {
        $this->seedOfficial();
        $admin = User::factory()->create(['role' => 'admin']);
        $route = Route::where('name', 'Route 2')->firstOrFail();
        $outbound = $route->variants()->where('direction', 'outbound')->firstOrFail();
        $inbound = $route->variants()->where('direction', 'inbound')->firstOrFail();
        $outboundStop = $outbound->stops()->where('name', 'Kenneth Road')->firstOrFail();
        $inboundStop = $inbound->stops()->where('name', 'Kenneth Road')->firstOrFail();

        $this->actingAs($admin)->patchJson("/admin/api/route-variants/{$outbound->id}/stops/{$outboundStop->id}/coordinates", [
            'lat' => 14.56, 'lng' => 121.08,
        ])->assertOk();
        $this->assertNull($inboundStop->fresh()->lat);
        $this->assertSame('pending', $inboundStop->fresh()->coordinate_status);

        $this->actingAs($admin)->patchJson("/admin/api/route-variants/{$outbound->id}/stops/{$inboundStop->id}/coordinates", [
            'lat' => 14.57, 'lng' => 121.09,
        ])->assertNotFound();
    }

    public function test_legacy_stop_coordinates_are_not_copied_to_official_variant_stops(): void
    {
        $this->seedOfficial();
        $variant = Route::where('name', 'Route 1')->firstOrFail()->variants()->where('direction', 'outbound')->firstOrFail();

        $this->assertNull($variant->stops()->firstOrFail()->lat);
        $this->assertNull($variant->stops()->firstOrFail()->canonical_stop_id);
    }

    public function test_rerunning_official_seeder_preserves_existing_coordinate_metadata(): void
    {
        $this->seedOfficial();
        $variant = Route::where('name', 'Route 1')->firstOrFail()->variants()->where('direction', 'outbound')->firstOrFail();
        $stop = $variant->stops()->firstOrFail();
        $stop->update([
            'lat' => 14.5593,
            'lng' => 121.0805,
            'coordinate_status' => 'verified',
            'coordinate_source' => 'manual review',
        ]);

        $this->seed(OfficialPasigRouteSeeder::class);

        $stop = $stop->fresh();
        $this->assertSame('verified', $stop->coordinate_status);
        $this->assertSame(14.5593, $stop->lat);
        $this->assertSame('manual review', $stop->coordinate_source);
    }}
