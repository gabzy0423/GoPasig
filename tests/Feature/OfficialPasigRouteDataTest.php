<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Stop;
use App\Models\Trip;
use App\Models\User;
use App\Models\VehiclePosition;
use App\Services\RouteVariantSelectionService;
use App\Services\Routing\AuthoritativeRouteResolver;
use Database\Seeders\OfficialPasigRouteSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfficialPasigRouteDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_routes_have_outbound_and_inbound_variants(): void
    {
        $this->seedOfficialRoutes();

        foreach (Route::canonicalProductionNames() as $routeName) {
            $route = Route::where('name', $routeName)->firstOrFail();
            $this->assertTrue($route->variants()->where('direction', 'outbound')->exists(), $routeName . ' outbound missing');
            $this->assertTrue($route->variants()->where('direction', 'inbound')->exists(), $routeName . ' inbound missing');
        }
    }

    public function test_official_routes_seed_split_service_schedule_windows_idempotently(): void
    {
        $this->seedOfficialRoutes();
        $this->seed(OfficialPasigRouteSeeder::class);

        $routeIds = Route::whereIn('name', ['Route 2', 'Route 3', 'Route 4'])->pluck('id');
        $this->assertSame(12, RouteServiceSchedule::whereIn('route_id', $routeIds)->count());

        foreach (['Route 2', 'Route 3', 'Route 4'] as $routeName) {
            $route = Route::where('name', $routeName)->firstOrFail();
            $outbound = $route->variants()->where('direction', 'outbound')->firstOrFail();
            $inbound = $route->variants()->where('direction', 'inbound')->firstOrFail();

            $this->assertSame([
                ['05:30:00', '09:00:00'],
                ['15:00:00', '17:00:00'],
            ], $this->serviceWindowsFor($outbound));

            $this->assertSame([
                ['06:00:00', '09:00:00'],
                ['15:00:00', '18:00:00'],
            ], $this->serviceWindowsFor($inbound));

            RouteServiceSchedule::where('route_id', $route->id)->each(function (RouteServiceSchedule $schedule) use ($route) {
                $this->assertSame($route->id, $schedule->routeVariant->route_id);
                $this->assertSame('with_designated_stops', $schedule->service_configuration);
                $this->assertSame(RouteServiceSchedule::SOURCE_BENEFICIARY_OFFICIAL, $schedule->source);
                $this->assertSame(['mon', 'tue', 'wed', 'thu', 'fri'], $schedule->service_days);
                $this->assertTrue($schedule->is_active);
            });
        }
    }

    public function test_official_route_service_schedules_group_for_admin_and_commuter_displays(): void
    {
        $this->seedOfficialRoutes();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/admin/api/route-service-schedules')
            ->assertOk()
            ->assertJsonPath('routes.0.name', 'Route 2')
            ->assertJsonPath('routes.0.variants.0.direction', 'inbound')
            ->assertJsonPath('routes.0.variants.0.serviceSchedule.firstTripTime', '6:00 AM')
            ->assertJsonPath('routes.0.variants.0.serviceSchedule.lastTripTime', '6:00 PM')
            ->assertJsonPath('routes.0.variants.0.serviceSchedule.windowCount', 2)
            ->assertJsonPath('routes.0.variants.0.serviceSchedules.0.firstTripTime', '6:00 AM')
            ->assertJsonPath('routes.0.variants.0.serviceSchedules.0.lastTripTime', '9:00 AM')
            ->assertJsonPath('routes.0.variants.0.serviceSchedules.1.firstTripTime', '3:00 PM')
            ->assertJsonPath('routes.0.variants.0.serviceSchedules.1.lastTripTime', '6:00 PM')
            ->assertJsonPath('routes.0.variants.1.direction', 'outbound')
            ->assertJsonPath('routes.0.variants.1.serviceSchedule.firstTripTime', '5:30 AM')
            ->assertJsonPath('routes.0.variants.1.serviceSchedule.lastTripTime', '5:00 PM')
            ->assertJsonPath('routes.0.variants.1.serviceSchedule.windowCount', 2)
            ->assertJsonPath('routes.0.variants.1.serviceSchedules.0.firstTripTime', '5:30 AM')
            ->assertJsonPath('routes.0.variants.1.serviceSchedules.0.lastTripTime', '9:00 AM')
            ->assertJsonPath('routes.0.variants.1.serviceSchedules.1.firstTripTime', '3:00 PM')
            ->assertJsonPath('routes.0.variants.1.serviceSchedules.1.lastTripTime', '5:00 PM');

        $this->get('/commuter/schedule')
            ->assertOk()
            ->assertSee('Route 2')
            ->assertSee('Operating Windows')
            ->assertSee('5:30 AM - 9:00 AM')
            ->assertSee('3:00 PM - 5:00 PM')
            ->assertSee('6:00 AM - 9:00 AM')
            ->assertSee('3:00 PM - 6:00 PM')
            ->assertSee('With Designated Stops');
    }

    public function test_official_directional_stop_sequences_are_independent(): void
    {
        $this->seedOfficialRoutes();

        $expectations = [
            'Route 2' => ['outbound_first' => 'SPED (Caruncho Ave.)', 'outbound_last' => 'Ligaya (Puregold)', 'inbound_first' => 'Ligaya (Puregold)', 'inbound_last' => 'SPED (Caruncho Ave.)'],
            'Route 3' => ['outbound_first' => 'SPED (Caruncho Ave.)', 'outbound_last' => 'One San Miguel', 'inbound_first' => 'One San Miguel', 'inbound_last' => 'SPED (Caruncho Ave.)'],
            'Route 4' => ['outbound_first' => 'SPED (Caruncho Ave.)', 'outbound_last' => 'Kenneth Road', 'inbound_first' => 'Kenneth Road', 'inbound_last' => 'SPED (Caruncho Ave.)'],
        ];

        foreach ($expectations as $routeName => $expected) {
            $route = Route::where('name', $routeName)->firstOrFail();
            $outbound = $route->variants()->where('direction', 'outbound')->with('stops')->firstOrFail();
            $inbound = $route->variants()->where('direction', 'inbound')->with('stops')->firstOrFail();

            $this->assertSame($expected['outbound_first'], $outbound->stops->first()->name);
            $this->assertSame($expected['outbound_last'], $outbound->stops->last()->name);
            $this->assertSame($expected['inbound_first'], $inbound->stops->first()->name);
            $this->assertSame($expected['inbound_last'], $inbound->stops->last()->name);
            $this->assertNotSame($outbound->stops->pluck('name')->all(), $inbound->stops->pluck('name')->all());
        }
    }

    public function test_official_stop_classifications_are_persisted_and_directional(): void
    {
        $this->seedOfficialRoutes();

        $route2 = Route::where('name', 'Route 2')->firstOrFail();
        $outbound = $route2->variants()->where('direction', 'outbound')->with('stops')->firstOrFail();
        $inbound = $route2->variants()->where('direction', 'inbound')->with('stops')->firstOrFail();

        $this->assertSame('pickup_point', $outbound->stops->firstWhere('name', 'SPED (Caruncho Ave.)')->stop_type);
        $this->assertSame('designated_stop', $outbound->stops->firstWhere('name', 'Ligaya (Puregold)')->stop_type);
        $this->assertSame('pickup_point', $inbound->stops->firstWhere('name', 'Ligaya (Puregold)')->stop_type);
        $this->assertSame('designated_stop', $inbound->stops->firstWhere('name', 'SPED (Caruncho Ave.)')->stop_type);
    }

    public function test_pickup_points_and_designated_stops_remain_ordered_route_variant_stops(): void
    {
        $this->seedOfficialRoutes();

        $variant = Route::where('name', 'Route 3')->firstOrFail()
            ->variants()->where('direction', 'outbound')->with('stops')->firstOrFail();

        $this->assertSame(range(1, $variant->stops->count()), $variant->stops->pluck('sequence')->all());
        $this->assertContains('pickup_point', $variant->stops->pluck('stop_type')->all());
        $this->assertContains('designated_stop', $variant->stops->pluck('stop_type')->all());
    }

    public function test_default_official_variant_projects_coordinates_to_legacy_commuter_stops(): void
    {
        $this->seedOfficialRoutes();

        $route = Route::where('name', 'Route 2')->firstOrFail();
        $variant = $route->variants()->where('is_default', true)->with('stops')->firstOrFail();
        $firstVariantStop = $variant->stops->firstOrFail();
        $firstVariantStop->update([
            'lat' => 14.5602934,
            'lng' => 121.0797616,
        ]);

        $this->seed(OfficialPasigRouteSeeder::class);

        $legacyStop = Stop::where('route_id', $route->id)
            ->where('sequence', $firstVariantStop->sequence)
            ->first();

        $this->assertNotNull($legacyStop);
        $this->assertSame($firstVariantStop->name, $legacyStop->name);
        $this->assertSame($legacyStop->id, $firstVariantStop->fresh()->canonical_stop_id);
    }

    public function test_route_2_inbound_pending_geometry_cannot_dispatch(): void
    {
        $this->seedOfficialRoutes();

        $route = Route::where('name', 'Route 2')->firstOrFail();
        $inbound = $route->variants()->where('direction', 'inbound')->firstOrFail();

        $this->assertSame('pending', $inbound->geometry_status);
        $this->expectException(ValidationException::class);

        app(RouteVariantSelectionService::class)->resolveForDispatch($route, $inbound->id);
    }

    public function test_variant_assigned_trip_uses_route_variant_geometry_and_legacy_trip_falls_back(): void
    {
        $this->seedOfficialRoutes();

        $officialRoute = Route::where('name', 'Route 2')->firstOrFail();
        $variant = $officialRoute->variants()->where('direction', 'outbound')->firstOrFail();
        $variant->update(['polyline_coordinates' => [[14.5, 121.0], [14.51, 121.01]], 'geometry_status' => 'schematic']);
        $variantTrip = Trip::factory()->create([
            'route_id' => $officialRoute->id,
            'route_variant_id' => $variant->id,
        ]);

        $variantPlan = app(AuthoritativeRouteResolver::class)->resolveForTrip($variantTrip);
        $this->assertSame('route_variant', $variantPlan->source);
        $this->assertSame($variant->id, $variantPlan->variant->id);
        $this->assertSame($variant->polyline_coordinates, $variantPlan->polylineCoordinates);
        $this->assertSame('pickup_point', $variantPlan->orderedStops->first()->stop_type);

        $legacyRoute = Route::where('name', 'Route A')->firstOrFail();
        $legacyTrip = Trip::factory()->create([
            'route_id' => $legacyRoute->id,
            'route_variant_id' => null,
        ]);

        $legacyPlan = app(AuthoritativeRouteResolver::class)->resolveForTrip($legacyTrip);
        $this->assertSame('legacy_route', $legacyPlan->source);
        $this->assertSame($legacyRoute->id, $legacyPlan->route->id);
    }

    public function test_admin_fleet_api_exposes_direction_metadata_and_keeps_marker_gps_based(): void
    {
        $this->seedOfficialRoutes();
        $admin = User::factory()->create(['role' => 'admin']);
        $route = Route::where('name', 'Route 3')->firstOrFail();
        $variant = $route->variants()->where('direction', 'outbound')->firstOrFail();
        $bus = Bus::factory()->create(['status' => 'operating', 'lat' => 14.1, 'lng' => 121.1]);
        $driver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'assigned']);
        $trip = Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'status' => 'ongoing',
        ]);

        VehiclePosition::create([
            'bus_id' => $bus->id,
            'trip_id' => $trip->id,
            'lat' => 14.612345,
            'lng' => 121.123456,
            'speed' => 5,
            'status' => 'moving',
            'last_updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/api/fleet-data');

        $response->assertOk();
        $busPayload = collect($response->json('buses'))->firstWhere('id', $bus->id);
        $this->assertSame($variant->id, $busPayload['route_variant_id']);
        $this->assertSame('outbound', $busPayload['direction']);
        $this->assertEquals(14.612345, $busPayload['lat']);
        $this->assertEquals(121.123456, $busPayload['lng']);
    }

    public function test_admin_fleet_api_serializes_canonical_routes_as_json_array(): void
    {
        $this->seedOfficialRoutes();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->getJson('/admin/api/fleet-data');

        $response->assertOk();
        $this->assertTrue(array_is_list($response->json('routes')));
        $this->assertSame([5, 6, 7], collect($response->json('routes'))->pluck('id')->all());
        $this->assertStringContainsString('"routes":[', $response->getContent());
        $this->assertStringNotContainsString('"routes":{"4"', $response->getContent());
    }
    public function test_official_route_identity_migration_preserves_legacy_route_references(): void
    {
        $this->seedOfficialRoutes();

        $legacyRoute = Route::findOrFail(1);
        $trip = Trip::factory()->create(['route_id' => $legacyRoute->id, 'route_variant_id' => null]);

        $this->assertSame('Route A', $legacyRoute->name);
        $this->assertSame($legacyRoute->id, $trip->fresh()->route_id);
        $this->assertNull(Route::where('name', 'Route 1')->first());
        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], Route::getCanonicalProductionCached()->pluck('name')->values()->all());
    }

    private function seedOfficialRoutes(): void
    {
        $this->seed(RouteSeeder::class);
        $this->seed(OfficialPasigRouteSeeder::class);
    }

    private function serviceWindowsFor(RouteVariant $variant): array
    {
        return RouteServiceSchedule::where('route_variant_id', $variant->id)
            ->orderBy('first_trip_time')
            ->get()
            ->map(fn (RouteServiceSchedule $schedule) => [
                substr($schedule->first_trip_time, 0, 8),
                substr($schedule->last_trip_time, 0, 8),
            ])
            ->values()
            ->all();
    }
}
