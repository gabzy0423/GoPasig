<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteServiceScheduleManagementUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_service_schedule_api_reads_from_route_service_schedules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$route, $outbound, $inbound] = $this->routeWithDirections();

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
        RouteServiceSchedule::create([
            'route_id' => $route->id,
            'route_variant_id' => $inbound->id,
            'first_trip_time' => '06:00',
            'last_trip_time' => '18:00',
            'service_configuration' => 'continuous',
            'service_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active' => true,
            'source' => 'beneficiary_official',
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/api/route-service-schedules');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('routes.0.name', 'Route 1 - SPED Ligaya')
            ->assertJsonPath('routes.0.variants.0.serviceSchedule.firstTripTime', '6:00 AM')
            ->assertJsonPath('routes.0.variants.0.serviceSchedule.lastTripTime', '6:00 PM')
            ->assertJsonPath('routes.0.variants.1.serviceSchedule.firstTripTime', '5:30 AM')
            ->assertJsonPath('routes.0.variants.1.serviceSchedule.lastTripTime', '5:00 PM')
            ->assertJsonPath('routes.0.variants.1.serviceSchedule.serviceDaysLabel', 'Monday - Friday');
    }

    public function test_outbound_inbound_missing_and_inactive_states_are_exposed_separately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$route, $outbound, $inbound] = $this->routeWithDirections();

        RouteServiceSchedule::create([
            'route_id' => $route->id,
            'route_variant_id' => $outbound->id,
            'first_trip_time' => '05:30',
            'last_trip_time' => '17:00',
            'service_configuration' => 'continuous',
            'service_days' => ['sat', 'sun'],
            'is_active' => false,
            'source' => 'beneficiary_official',
            'effective_from' => '2026-07-01',
            'effective_until' => '2026-12-31',
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/api/route-service-schedules');

        $response->assertOk()
            ->assertJsonPath('routes.0.variants.0.direction', 'inbound')
            ->assertJsonPath('routes.0.variants.0.serviceSchedule', null)
            ->assertJsonPath('routes.0.variants.1.direction', 'outbound')
            ->assertJsonPath('routes.0.variants.1.serviceSchedule.statusLabel', 'Inactive')
            ->assertJsonPath('routes.0.variants.1.serviceSchedule.serviceDaysLabel', 'Saturday, Sunday')
            ->assertJsonPath('routes.0.variants.1.serviceSchedule.effectiveRangeLabel', '2026-07-01 to 2026-12-31');
    }

    public function test_dashboard_primary_route_service_schedule_ui_is_read_only_and_omits_trip_slot_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get('/admin/dashboard');
        $section = $this->routeServiceScheduleSection($response->getContent());

        $response->assertOk();
        $this->assertStringContainsString('Route Service Schedule Management', $section);
        $this->assertStringContainsString('Route Service Schedules', $section);
        $this->assertStringContainsString('Official operating windows per route direction', $section);
        $this->assertStringContainsString('route_service_schedules', $section);
        $this->assertStringContainsString('First Trip', $section);
        $this->assertStringContainsString('Last Trip', $section);
        $this->assertStringContainsString('Official operating hours not configured', $section);
        $this->assertStringContainsString('Inactive', $section);
        $this->assertStringNotContainsString('Create schedule', $section);
        $this->assertStringNotContainsString('Choose a bus', $section);
        $this->assertStringNotContainsString('Choose a driver', $section);
        $this->assertStringNotContainsString('Departure Time', $section);
        $this->assertStringNotContainsString('Estimated Arrival Time', $section);
    }

    public function test_route_service_schedule_show_endpoint_returns_one_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$route, $outbound] = $this->routeWithSingleDirection();
        $serviceSchedule = RouteServiceSchedule::create([
            'route_id' => $route->id,
            'route_variant_id' => $outbound->id,
            'first_trip_time' => '05:30',
            'last_trip_time' => '17:00',
            'service_configuration' => 'continuous',
            'service_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active' => true,
            'source' => 'beneficiary_official',
        ]);

        $this->actingAs($admin)
            ->getJson("/admin/api/route-service-schedules/{$serviceSchedule->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('serviceSchedule.id', $serviceSchedule->id)
            ->assertJsonPath('serviceSchedule.firstTripTime', '5:30 AM')
            ->assertJsonPath('serviceSchedule.lastTripTime', '5:00 PM');
    }

    private function routeServiceScheduleSection(string $html): string
    {
        $start = strpos($html, 'id="screen-routes"');
        $end = strpos($html, 'id="rm-panel-stops"');

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }

    private function routeWithDirections(): array
    {
        $route = Route::factory()->create(['name' => 'Route 1 - SPED Ligaya']);
        $outbound = $this->variantFor($route, 'outbound', 'SPED', 'Ligaya');
        $inbound = $this->variantFor($route, 'inbound', 'Ligaya', 'SPED');

        return [$route, $outbound, $inbound];
    }

    private function routeWithSingleDirection(): array
    {
        $route = Route::factory()->create(['name' => 'Route 1 - SPED Ligaya']);
        $outbound = $this->variantFor($route, 'outbound', 'SPED', 'Ligaya');

        return [$route, $outbound];
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

