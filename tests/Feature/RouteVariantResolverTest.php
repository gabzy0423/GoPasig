<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Stop;
use App\Models\Trip;
use App\Services\Routing\AuthoritativeRouteResolver;
use App\Services\Testing\ControlledLocationIntelligenceHarness;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteVariantResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_trip_without_variant_resolves_legacy_route_geometry_and_stops(): void
    {
        $route = Route::factory()->create([
            'polyline_coordinates' => [
                [14.5000, 121.0000],
                [14.5100, 121.0100],
            ],
        ]);

        $stopB = Stop::create(['route_id' => $route->id, 'name' => 'Second', 'lat' => 14.51, 'lng' => 121.01, 'sequence' => 2]);
        $stopA = Stop::create(['route_id' => $route->id, 'name' => 'First', 'lat' => 14.50, 'lng' => 121.00, 'sequence' => 1]);

        $trip = Trip::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => null,
        ]);

        $plan = app(AuthoritativeRouteResolver::class)->resolveForTrip($trip);

        $this->assertSame('legacy_route', $plan->source);
        $this->assertFalse($plan->usesVariant());
        $this->assertSame($route->id, $plan->route->id);
        $this->assertSame($route->polyline_coordinates, $plan->polylineCoordinates);
        $this->assertSame([$stopA->id, $stopB->id], $plan->orderedStops->pluck('id')->all());
    }

    public function test_trip_with_variant_resolves_variant_geometry(): void
    {
        $route = Route::factory()->create([
            'polyline_coordinates' => [
                [14.5000, 121.0000],
                [14.5100, 121.0100],
            ],
        ]);

        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'inbound',
            'origin_name' => 'Inbound Origin',
            'destination_name' => 'Inbound Destination',
            'polyline_coordinates' => [
                [14.7000, 121.2000],
                [14.7100, 121.2100],
                [14.7200, 121.2200],
            ],
            'geometry_version' => 7,
            'geometry_status' => 'valid',
            'is_default' => false,
        ]);

        $trip = Trip::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
        ]);

        $plan = app(AuthoritativeRouteResolver::class)->resolveForTrip($trip);

        $this->assertSame('route_variant', $plan->source);
        $this->assertTrue($plan->usesVariant());
        $this->assertSame($variant->id, $plan->variant->id);
        $this->assertSame($variant->polyline_coordinates, $plan->polylineCoordinates);
    }

    public function test_variant_stops_are_returned_in_variant_specific_sequence(): void
    {
        $route = Route::factory()->create();
        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'inbound',
            'origin_name' => 'Ligaya',
            'destination_name' => 'SPED',
            'polyline_coordinates' => [[14.1, 121.1], [14.2, 121.2]],
            'geometry_status' => 'valid',
            'is_default' => false,
        ]);

        $third = RouteVariantStop::create(['route_variant_id' => $variant->id, 'name' => 'SPED', 'lat' => 14.3, 'lng' => 121.3, 'sequence' => 3]);
        $first = RouteVariantStop::create(['route_variant_id' => $variant->id, 'name' => 'Ligaya', 'lat' => 14.1, 'lng' => 121.1, 'sequence' => 1]);
        $second = RouteVariantStop::create(['route_variant_id' => $variant->id, 'name' => 'Midpoint', 'lat' => 14.2, 'lng' => 121.2, 'sequence' => 2]);

        $trip = Trip::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
        ]);

        $plan = app(AuthoritativeRouteResolver::class)->resolveForTrip($trip);

        $this->assertSame([$first->id, $second->id, $third->id], $plan->orderedStops->pluck('id')->all());
        $this->assertSame(['Ligaya', 'Midpoint', 'SPED'], $plan->orderedStops->pluck('name')->all());
    }

    public function test_route_c_seeded_default_variant_preserves_controlled_location_intelligence_dataset(): void
    {
        $this->seed(RouteSeeder::class);

        $route = Route::with(['stops' => fn ($query) => $query->orderBy('sequence'), 'defaultVariant.stops'])->findOrFail(3);
        $variant = $route->defaultVariant;

        $this->assertNotNull($variant);
        $this->assertSame('outbound', $variant->direction);
        $this->assertSame($route->polyline_coordinates, $variant->polyline_coordinates);
        $this->assertSame($route->stops->pluck('name')->all(), $variant->stops->pluck('name')->all());
        $this->assertSame($route->stops->pluck('sequence')->all(), $variant->stops->pluck('sequence')->all());

        $sequence = app(ControlledLocationIntelligenceHarness::class)->buildRouteCSequence($route);
        $this->assertSame('A', $sequence[0]['key']);
        $this->assertSame((float) $route->stops[0]->lat, $sequence[0]['lat']);
        $this->assertSame((float) $route->stops[2]->lng, collect($sequence)->last()['lng']);
    }
}