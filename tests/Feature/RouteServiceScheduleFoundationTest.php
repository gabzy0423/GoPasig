<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use App\Models\Schedule;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RouteServiceScheduleFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_service_schedule_belongs_to_route_and_route_variant(): void
    {
        [$route, $outbound] = $this->routeWithVariant('outbound');

        $serviceSchedule = RouteServiceSchedule::create([
            'route_id' => $route->id,
            'route_variant_id' => $outbound->id,
            'first_trip_time' => '05:30',
            'last_trip_time' => '17:00',
            'service_configuration' => RouteServiceSchedule::CONFIG_CONTINUOUS,
            'service_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active' => true,
            'source' => RouteServiceSchedule::SOURCE_BENEFICIARY_OFFICIAL,
        ]);

        $this->assertTrue($serviceSchedule->route->is($route));
        $this->assertTrue($serviceSchedule->routeVariant->is($outbound));
        $this->assertTrue($route->serviceSchedules->first()->is($serviceSchedule));
        $this->assertTrue($outbound->serviceSchedules->first()->is($serviceSchedule));
    }

    public function test_outbound_and_inbound_variants_can_have_different_operating_hours(): void
    {
        $route = Route::factory()->create();
        $outbound = $this->variantFor($route, 'outbound');
        $inbound = $this->variantFor($route, 'inbound');

        $outboundSchedule = RouteServiceSchedule::create([
            'route_id' => $route->id,
            'route_variant_id' => $outbound->id,
            'first_trip_time' => '05:30',
            'last_trip_time' => '17:00',
            'service_configuration' => 'continuous',
            'service_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active' => true,
            'source' => 'beneficiary_official',
        ]);

        $inboundSchedule = RouteServiceSchedule::create([
            'route_id' => $route->id,
            'route_variant_id' => $inbound->id,
            'first_trip_time' => '06:00',
            'last_trip_time' => '18:00',
            'service_configuration' => 'continuous',
            'service_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active' => true,
            'source' => 'beneficiary_official',
        ]);

        $this->assertSame('05:30', substr($outboundSchedule->fresh()->first_trip_time, 0, 5));
        $this->assertSame('17:00', substr($outboundSchedule->fresh()->last_trip_time, 0, 5));
        $this->assertSame('06:00', substr($inboundSchedule->fresh()->first_trip_time, 0, 5));
        $this->assertSame('18:00', substr($inboundSchedule->fresh()->last_trip_time, 0, 5));
    }

    public function test_route_variant_from_another_route_cannot_be_assigned(): void
    {
        $route = Route::factory()->create();
        $otherRoute = Route::factory()->create();
        $otherVariant = $this->variantFor($otherRoute, 'outbound');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route service schedule RouteVariant must belong to the selected Route.');

        RouteServiceSchedule::create([
            'route_id' => $route->id,
            'route_variant_id' => $otherVariant->id,
            'first_trip_time' => '05:30',
            'last_trip_time' => '17:00',
            'service_configuration' => 'continuous',
            'service_days' => ['mon'],
            'is_active' => true,
            'source' => 'beneficiary_official',
        ]);
    }

    public function test_service_days_persist_as_array(): void
    {
        [$route, $variant] = $this->routeWithVariant('outbound');

        $serviceSchedule = RouteServiceSchedule::create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'first_trip_time' => '05:30',
            'last_trip_time' => '17:00',
            'service_configuration' => 'continuous',
            'service_days' => ['mon', 'wed', 'fri', 'sun'],
            'is_active' => true,
            'source' => 'beneficiary_official',
        ])->fresh();

        $this->assertSame(['mon', 'wed', 'fri', 'sun'], $serviceSchedule->service_days);
    }

    public function test_inactive_service_schedule_can_be_represented(): void
    {
        [$route, $variant] = $this->routeWithVariant('outbound');

        $serviceSchedule = RouteServiceSchedule::create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'first_trip_time' => '05:30',
            'last_trip_time' => '17:00',
            'service_configuration' => 'continuous',
            'service_days' => ['mon'],
            'is_active' => false,
            'source' => 'beneficiary_official',
        ])->fresh();

        $this->assertFalse($serviceSchedule->is_active);
        $this->assertSame(0, $route->activeServiceSchedules()->count());
    }

    public function test_effective_date_ranges_can_be_represented(): void
    {
        [$route, $variant] = $this->routeWithVariant('outbound');

        $serviceSchedule = RouteServiceSchedule::create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'first_trip_time' => '05:30',
            'last_trip_time' => '17:00',
            'service_configuration' => 'continuous',
            'service_days' => ['mon'],
            'is_active' => true,
            'source' => 'beneficiary_official',
            'effective_from' => '2026-07-01',
            'effective_until' => '2026-12-31',
        ])->fresh();

        $this->assertSame('2026-07-01', $serviceSchedule->effective_from->toDateString());
        $this->assertSame('2026-12-31', $serviceSchedule->effective_until->toDateString());
    }

    public function test_invalid_effective_date_range_is_rejected(): void
    {
        [$route, $variant] = $this->routeWithVariant('outbound');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route service schedule effective_until must be on or after effective_from.');

        RouteServiceSchedule::create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'first_trip_time' => '05:30',
            'last_trip_time' => '17:00',
            'service_configuration' => 'continuous',
            'service_days' => ['mon'],
            'is_active' => true,
            'source' => 'beneficiary_official',
            'effective_from' => '2026-12-31',
            'effective_until' => '2026-07-01',
        ]);
    }

    public function test_existing_legacy_schedule_and_trip_schedule_id_behavior_remains_unchanged(): void
    {
        $route = Route::factory()->create();
        $variant = $this->variantFor($route, 'outbound');
        $bus = Bus::factory()->create();
        $driver = Driver::factory()->create();

        $schedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => '2026-07-23',
        ]);

        $trip = Trip::factory()->create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'schedule_id' => $schedule->id,
            'status' => 'dispatched',
            'gps_session' => 'OFF',
            'started_at' => null,
        ]);

        $this->assertTrue($schedule->trip->is($trip));
        $this->assertTrue($trip->schedule->is($schedule));
        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'schedule_id' => $schedule->id,
        ]);
    }

    private function routeWithVariant(string $direction): array
    {
        $route = Route::factory()->create();

        return [$route, $this->variantFor($route, $direction)];
    }

    private function variantFor(Route $route, string $direction): RouteVariant
    {
        return RouteVariant::create([
            'route_id' => $route->id,
            'direction' => $direction,
            'origin_name' => ucfirst($direction) . ' Origin',
            'destination_name' => ucfirst($direction) . ' Destination',
            'polyline_coordinates' => [[14.5593, 121.0805], [14.5603, 121.0815]],
            'geometry_version' => 1,
            'geometry_status' => 'valid',
            'is_default' => $direction === 'outbound',
        ]);
    }
}



