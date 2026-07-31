<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use App\Models\Schedule;
use App\Models\Stop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class CommuterScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_commuter_schedule_reads_route_service_schedules_by_direction(): void
    {
        [$route1, $route1Outbound, $route1Inbound] = $this->canonicalRouteWithDirections('Route 1', 'SPED', 'Ligaya');
        [$route2, $route2Outbound, $route2Inbound] = $this->canonicalRouteWithDirections('Route 2', 'San Joaquin', 'Ortigas');
        [$route3] = $this->canonicalRouteWithDirections('Route 3', 'Pasig City Hall', 'Rosario');
        [$legacyRoute, $legacyOutbound] = $this->routeWithDirections('Route A', 'Legacy Origin', 'Legacy Destination');
        [$uatRoute, $uatOutbound] = $this->routeWithDirections('PHASE3C-UAT Point-to-Point A-B', 'UAT A', 'UAT B');

        RouteServiceSchedule::create([
            'route_id' => $route1->id,
            'route_variant_id' => $route1Outbound->id,
            'first_trip_time' => '05:30',
            'last_trip_time' => '17:00',
            'service_configuration' => 'continuous',
            'service_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active' => true,
            'source' => 'beneficiary_official',
        ]);

        RouteServiceSchedule::create([
            'route_id' => $route1->id,
            'route_variant_id' => $route1Inbound->id,
            'first_trip_time' => '06:00',
            'last_trip_time' => '18:00',
            'service_configuration' => 'continuous',
            'service_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active' => true,
            'source' => 'beneficiary_official',
            'effective_from' => '2026-07-01',
            'effective_until' => '2026-12-31',
        ]);

        RouteServiceSchedule::create([
            'route_id' => $route2->id,
            'route_variant_id' => $route2Outbound->id,
            'first_trip_time' => '07:00',
            'last_trip_time' => '16:30',
            'service_configuration' => 'continuous',
            'service_days' => ['sat', 'sun'],
            'is_active' => false,
            'source' => 'beneficiary_official',
        ]);

        RouteServiceSchedule::create([
            'route_id' => $legacyRoute->id,
            'route_variant_id' => $legacyOutbound->id,
            'first_trip_time' => '01:00',
            'last_trip_time' => '02:00',
            'service_configuration' => 'continuous',
            'service_days' => ['mon'],
            'is_active' => true,
            'source' => 'beneficiary_official',
        ]);

        RouteServiceSchedule::create([
            'route_id' => $uatRoute->id,
            'route_variant_id' => $uatOutbound->id,
            'first_trip_time' => '03:00',
            'last_trip_time' => '04:00',
            'service_configuration' => 'continuous',
            'service_days' => ['mon'],
            'is_active' => true,
            'source' => 'beneficiary_official',
        ]);

        $bus = Bus::factory()->create(['plate_number' => 'BUS-LEGACY']);
        $driver = Driver::factory()->create();
        Schedule::create([
            'route_id' => $route1->id,
            'route_variant_id' => $route1Outbound->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'departure_time' => '08:15:00',
            'arrival_time' => '09:00:00',
            'status' => 'Delayed',
            'delay_minutes' => 12,
        ]);

        $component = Livewire::test('commuter.commuter-schedule')
            ->assertSee('Route 1')
            ->assertSee('Route 2')
            ->assertSee('Route 3')
            ->assertSee('Outbound')
            ->assertSee('SPED')
            ->assertSee('Ligaya')
            ->assertSee('5:30 AM')
            ->assertSee('5:00 PM')
            ->assertSee('Inbound')
            ->assertSee('6:00 AM')
            ->assertSee('6:00 PM')
            ->assertSee('Monday - Friday')
            ->assertSee('Continuous')
            ->assertSee('Active')
            ->assertSee('Inactive')
            ->assertSee('2026-07-01 to 2026-12-31')
            ->assertSee('Beneficiary Official')
            ->assertSee('Official operating hours not configured')
            ->assertDontSee('Route A')
            ->assertDontSee('PHASE3C-UAT')
            ->assertDontSee('1:00 AM')
            ->assertDontSee('3:00 AM')
            ->assertDontSee('BUS-LEGACY')
            ->assertDontSee($driver->name)
            ->assertDontSee('8:15 AM')
            ->assertDontSee('Delayed')
            ->assertDontSee('On time')
            ->assertDontSee('Cancelled');

        $serviceRoutes = $component->viewData('serviceRoutes');
        $route1Directions = collect($serviceRoutes)->firstWhere('name', 'Route 1')['directions'];

        $this->assertSame(['outbound', 'inbound'], collect($route1Directions)->pluck('direction')->all());
        $this->assertSame('5:30 AM', $route1Directions[0]['first_trip_time']);
        $this->assertSame('6:00 PM', $route1Directions[1]['last_trip_time']);

        $route2Directions = collect($serviceRoutes)->firstWhere('name', 'Route 2')['directions'];
        $this->assertSame('Missing Configuration', $route2Directions[1]['status_label']);
    }

    public function test_public_commuter_schedule_page_is_guest_accessible_without_trip_slot_fields(): void
    {
        [$route, $outbound] = $this->canonicalRouteWithDirections('Route 1', 'SPED', 'Ligaya');

        RouteServiceSchedule::create([
            'route_id' => $route->id,
            'route_variant_id' => $outbound->id,
            'first_trip_time' => '05:30',
            'last_trip_time' => '17:00',
            'service_configuration' => 'continuous',
            'service_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active' => true,
            'source' => 'beneficiary_official',
        ]);

        $response = $this->get('/commuter/schedule');

        $response->assertOk();
        $response->assertCookie('commuter_session_token');
        $response->assertSee('Official operating windows by route direction');
        $response->assertSee('First Trip');
        $response->assertSee('Last Trip');
        $response->assertDontSee('Bus assigned');
        $response->assertDontSee('Driver assigned');
        $response->assertDontSee('Departure Time');
        $response->assertDontSee('Estimated Arrival Time');
        $response->assertDontSee('Trip details');
        $response->assertDontSee('Set arrival alert');
    }

    public function test_offsets_respect_database_segment_weights(): void
    {
        $route = Route::create([
            'name' => 'Route B',
            'description' => 'Test Route B',
            'polyline_coordinates' => [[14.5, 121.0]],
            'status' => 'Active',
        ]);

        $stop1 = Stop::create([
            'route_id' => $route->id,
            'name' => 'Stop 1',
            'lat' => 14.5,
            'lng' => 121.0,
            'sequence' => 1,
            'segment_weight' => null,
        ]);

        $stop2 = Stop::create([
            'route_id' => $route->id,
            'name' => 'Stop 2',
            'lat' => 14.5,
            'lng' => 121.0,
            'sequence' => 2,
            'segment_weight' => 1.0,
        ]);

        $stop3 = Stop::create([
            'route_id' => $route->id,
            'name' => 'Stop 3',
            'lat' => 14.5,
            'lng' => 121.0,
            'sequence' => 3,
            'segment_weight' => 3.0,
        ]);

        $offsets = Stop::getDistanceWeightedOffsets(collect([$stop1, $stop2, $stop3]), 40.0);

        $this->assertEquals(0.0, $offsets[0]);
        $this->assertEquals(10.0, $offsets[1]);
        $this->assertEquals(40.0, $offsets[2]);
    }

    public function test_offsets_fallback_to_distance_when_segment_weights_missing(): void
    {
        $route = Route::create([
            'name' => 'Route C',
            'description' => 'Test Route C',
            'polyline_coordinates' => [[14.5, 121.0]],
            'status' => 'Active',
        ]);

        $stop1 = Stop::create([
            'route_id' => $route->id,
            'name' => 'Stop 1',
            'lat' => 14.0,
            'lng' => 121.0,
            'sequence' => 1,
            'segment_weight' => null,
        ]);

        $stop2 = Stop::create([
            'route_id' => $route->id,
            'name' => 'Stop 2',
            'lat' => 15.0,
            'lng' => 121.0,
            'sequence' => 2,
            'segment_weight' => null,
        ]);

        $stop3 = Stop::create([
            'route_id' => $route->id,
            'name' => 'Stop 3',
            'lat' => 16.0,
            'lng' => 121.0,
            'sequence' => 3,
            'segment_weight' => null,
        ]);

        $offsets = Stop::getDistanceWeightedOffsets(collect([$stop1, $stop2, $stop3]), 30.0);

        $this->assertEquals(0.0, $offsets[0]);
        $this->assertEquals(15.0, $offsets[1]);
        $this->assertEquals(30.0, $offsets[2]);
    }

    private function canonicalRouteWithDirections(string $name, string $origin, string $destination): array
    {
        return $this->routeWithDirections($name, $origin, $destination);
    }

    private function routeWithDirections(string $name, string $origin, string $destination): array
    {
        Cache::flush();

        $route = Route::create([
            'name' => $name,
            'description' => $origin . ' to ' . $destination,
            'status' => 'Active',
            'color' => '#003F87',
        ]);

        $outbound = $this->variantFor($route, 'outbound', $origin, $destination);
        $inbound = $this->variantFor($route, 'inbound', $destination, $origin);

        return [$route, $outbound, $inbound];
    }

    private function variantFor(Route $route, string $direction, string $origin, string $destination): RouteVariant
    {
        return RouteVariant::create([
            'route_id' => $route->id,
            'direction' => $direction,
            'origin_name' => $origin,
            'destination_name' => $destination,
            'polyline_coordinates' => [[14.5593, 121.0805], [14.5603, 121.0815]],
            'geometry_version' => 1,
            'geometry_status' => 'valid',
            'is_default' => $direction === 'outbound',
        ]);
    }
}

