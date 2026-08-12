<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Trip;
use App\Models\User;
use App\Services\CentralDispatchEligibilityService;
use App\Services\TripService;
use Database\Seeders\OfficialPasigRouteSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RD7LegacyRouteRuntimeRetirementTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoutes(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);
        Cache::forget('routes_all');
    }

    public function test_new_trip_runtime_rejects_legacy_route_but_historical_records_remain_readable(): void
    {
        $this->seedRoutes();

        $legacyRoute = Route::where('name', 'Route A')->firstOrFail();
        $bus = Bus::factory()->create();
        $driver = Driver::factory()->create();

        try {
            TripService::startTrip($bus, $driver, $legacyRoute);
            $this->fail('Legacy Route A should not be accepted for new operational trips.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Only official production routes are available for new operations.',
                $exception->errors()['route_id'][0]
            );
        }

        $this->assertDatabaseMissing('trips', [
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $legacyRoute->id,
        ]);

        $historicalTrip = Trip::factory()->create([
            'route_id' => $legacyRoute->id,
            'route_variant_id' => null,
            'status' => 'completed',
        ]);

        $this->assertSame('Route A', $historicalTrip->fresh()->route->name);
    }

    public function test_central_dispatch_route_eligibility_rejects_legacy_routes(): void
    {
        $this->seedRoutes();

        foreach (['Route A', 'Route B', 'Route C', 'Route D'] as $routeName) {
            $route = Route::where('name', $routeName)->firstOrFail();
            $eligibility = CentralDispatchEligibilityService::route($route);

            $this->assertFalse($eligibility['eligible'], $routeName . ' should be historical-only.');
            $this->assertSame('Only official production routes are available for new operations.', $eligibility['reason']);
        }
    }

    public function test_legacy_route_stop_mutation_api_is_blocked(): void
    {
        $this->seedRoutes();

        $admin = User::factory()->create(['role' => 'admin']);
        $legacyRoute = Route::where('name', 'Route A')->firstOrFail();

        $this->actingAs($admin)
            ->postJson('/admin/api/stops', [
                'route_id' => $legacyRoute->id,
                'name' => 'New Legacy Stop',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Legacy Route A-D stop definitions are historical-only.');

        $this->assertDatabaseMissing('stops', [
            'route_id' => $legacyRoute->id,
            'name' => 'New Legacy Stop',
        ]);
    }

    public function test_dispatch_intelligence_runtime_route_payload_is_canonical_only(): void
    {
        $this->seedRoutes();

        $routes = app(\App\Http\Controllers\Fleet\DispatchIntelligenceController::class)
            ->fetchRoutesData(now()->englishDayOfWeek, '18:00-20:00', 1);

        $names = collect($routes)->pluck('name')->values()->all();

        $this->assertSame(Route::canonicalProductionNames(), $names);
        $this->assertNotContains('Route A', $names);
        $this->assertNotContains('Route B', $names);
        $this->assertNotContains('Route C', $names);
        $this->assertNotContains('Route D', $names);
    }
}
