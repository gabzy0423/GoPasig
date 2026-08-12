<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\RouteVariant;
use Database\Seeders\OfficialPasigRouteSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildStaticRouteGeometryTest extends TestCase
{
    use RefreshDatabase;

    private function prepareOfficialStops(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);

        $offset = 0;
        RouteVariant::query()->whereHas('route', fn ($query) => $query->whereIn('name', Route::canonicalProductionNames()))
            ->with('stops')
            ->get()
            ->each(function (RouteVariant $variant) use (&$offset): void {
                $variant->stops->each(function ($stop, int $index) use (&$offset): void {
                    $stop->update([
                        'lat' => 14.55 + (($offset + $index) * 0.0001),
                        'lng' => 121.07 + (($offset + $index) * 0.0001),
                    ]);
                });
                $offset += $variant->stops->count();
            });
    }

    public function test_dry_run_processes_official_production_variants_without_writes(): void
    {
        $this->prepareOfficialStops();
        $variants = RouteVariant::query()->whereHas('route', fn ($query) => $query->whereIn('name', Route::canonicalProductionNames()))->get();
        $before = $variants->mapWithKeys(fn ($variant) => [$variant->id => [$variant->polyline_coordinates, $variant->geometry_version]])->all();

        $this->artisan('gopasig:build-static-route-geometry --dry-run')
            ->assertExitCode(0)
            ->expectsOutput('Dry run complete. No database writes were made.');

        foreach ($before as $id => [$geometry, $version]) {
            $variant = RouteVariant::findOrFail($id);
            $this->assertSame($geometry, $variant->polyline_coordinates);
            $this->assertSame($version, $variant->geometry_version);
        }
    }

    public function test_apply_preserves_sequence_and_direction_without_fabricated_points(): void
    {
        $this->prepareOfficialStops();
        $this->artisan('gopasig:build-static-route-geometry --apply')->assertExitCode(0);

        $variants = RouteVariant::query()->whereHas('route', fn ($query) => $query->whereIn('name', Route::canonicalProductionNames()))->with('stops')->get();
        $this->assertCount(6, $variants);
        foreach ($variants as $variant) {
            $expected = $variant->stops->sortBy('sequence')->map(fn ($stop) => [round((float) $stop->lat, 7), round((float) $stop->lng, 7)])->values()->all();
            $this->assertSame($expected, $variant->fresh()->polyline_coordinates);
            $this->assertSame('schematic', $variant->fresh()->geometry_status);
        }

        $route2 = Route::where('name', 'Route 2')->firstOrFail();
        $this->assertNotSame(
            $route2->variants()->where('direction', 'outbound')->firstOrFail()->polyline_coordinates,
            $route2->variants()->where('direction', 'inbound')->firstOrFail()->polyline_coordinates
        );
    }

    public function test_duplicate_stop_coordinates_are_preserved_as_vertices(): void
    {
        $this->prepareOfficialStops();
        $variant = Route::where('name', 'Route 2')->firstOrFail()->variants()->where('direction', 'outbound')->firstOrFail();
        $variant->stops()->where('sequence', 2)->update(['lat' => 14.5619548, 'lng' => 121.0767738]);
        $variant->stops()->where('sequence', 3)->update(['lat' => 14.5619548, 'lng' => 121.0767738]);

        $this->artisan('gopasig:build-static-route-geometry --apply')->assertExitCode(0);
        $fresh = $variant->fresh();
        $this->assertSame($fresh->stops()->count(), count($fresh->polyline_coordinates));
        $this->assertSame($fresh->polyline_coordinates[1], $fresh->polyline_coordinates[2]);
    }

    public function test_second_apply_is_idempotent_and_legacy_route_is_untouched(): void
    {
        $this->prepareOfficialStops();
        $legacy = Route::whereNotIn('name', Route::canonicalProductionNames())->firstOrFail();
        $legacyGeometry = [[14.1, 121.1], [14.2, 121.2]];
        $legacy->update(['polyline_coordinates' => $legacyGeometry]);

        $this->artisan('gopasig:build-static-route-geometry --apply')->assertExitCode(0);
        $versions = RouteVariant::query()->whereHas('route', fn ($query) => $query->whereIn('name', Route::canonicalProductionNames()))->pluck('geometry_version', 'id')->all();
        $this->artisan('gopasig:build-static-route-geometry --apply')
            ->assertExitCode(0)
            ->expectsOutputToContain('0 variant(s) updated');

        $this->assertSame($versions, RouteVariant::query()->whereIn('id', array_keys($versions))->pluck('geometry_version', 'id')->all());
        $this->assertSame($legacyGeometry, $legacy->fresh()->polyline_coordinates);
    }

    public function test_active_geometry_requires_force(): void
    {
        $this->prepareOfficialStops();
        $variant = Route::where('name', 'Route 2')->firstOrFail()->variants()->where('direction', 'outbound')->firstOrFail();
        $variant->update(['polyline_coordinates' => [[14.4, 121.0], [14.5, 121.1]], 'geometry_status' => 'authoritative']);

        $this->artisan('gopasig:build-static-route-geometry --apply')
            ->assertExitCode(1)
            ->expectsOutputToContain('Apply blocked');
        $this->assertEquals([[14.4, 121.0], [14.5, 121.1]], $variant->fresh()->polyline_coordinates);
    }

    public function test_missing_coordinate_aborts_without_partial_writes(): void
    {
        $this->prepareOfficialStops();
        $before = RouteVariant::query()->whereHas('route', fn ($query) => $query->whereIn('name', Route::canonicalProductionNames()))
            ->pluck('polyline_coordinates', 'id')
            ->all();

        $variant = Route::where('name', 'Route 2')->firstOrFail()->variants()->where('direction', 'inbound')->firstOrFail();
        $variant->stops()->where('sequence', 1)->update(['lat' => null, 'lng' => null]);
        $this->artisan('gopasig:build-static-route-geometry --apply')->assertExitCode(1);

        $after = RouteVariant::query()->whereIn('id', array_keys($before))->pluck('polyline_coordinates', 'id')->all();
        $this->assertSame($before, $after);
    }
}
