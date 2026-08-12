<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\Trip;
use App\Services\RouteVariantSelectionService;
use App\Services\Routing\AuthoritativeRouteResolver;
use Database\Seeders\OfficialPasigRouteSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RD4FOperationalActivationTest extends TestCase
{
    use RefreshDatabase;

    private function seedOperationalRoutes(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);

        Route::whereIn('name', Route::canonicalProductionNames())
            ->get()
            ->each(function (Route $route): void {
                $route->variants()->with('stops')->get()->each(function (RouteVariant $variant): void {
                    $variant->update([
                        'geometry_status' => 'schematic',
                        'polyline_coordinates' => [[14.55, 121.07], [14.56, 121.08]],
                    ]);
                    $variant->stops->each(function ($stop, int $index): void {
                        $stop->update(['lat' => 14.55 + ($index * 0.001), 'lng' => 121.07 + ($index * 0.001)]);
                    });
                });
            });
    }

    public function test_official_variants_are_operationally_eligible(): void
    {
        $this->seedOperationalRoutes();
        $service = app(RouteVariantSelectionService::class);

        foreach (Route::canonicalProductionNames() as $name) {
            $route = Route::where('name', $name)->firstOrFail();
            $variant = $route->variants()->firstOrFail();
            $this->assertTrue($service->isUsableForLiveDispatch($variant));
        }
    }

    public function test_legacy_variants_are_rejected_for_new_dispatch_selection(): void
    {
        $this->seedOperationalRoutes();
        $service = app(RouteVariantSelectionService::class);
        $route = Route::where('name', 'Route A')->firstOrFail();
        $variant = $route->variants()->firstOrFail();

        $this->assertFalse($service->isUsableForLiveDispatch($variant));
        $this->expectException(ValidationException::class);
        $service->resolveForDispatch($route, $variant->id);
    }

    public function test_historical_route_a_trip_still_resolves_through_legacy_geometry(): void
    {
        $this->seedOperationalRoutes();
        $route = Route::where('name', 'Route A')->firstOrFail();
        $trip = Trip::factory()->create(['route_id' => $route->id, 'route_variant_id' => null]);

        $plan = app(AuthoritativeRouteResolver::class)->resolveForTrip($trip);

        $this->assertSame('legacy_route', $plan->source);
        $this->assertSame($route->id, $plan->route->id);
    }
}