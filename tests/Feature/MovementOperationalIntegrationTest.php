<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\GPSLog;
use App\Models\Trip;
use App\Models\User;
use App\Models\VehiclePosition;
use App\Services\Routing\FleetStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovementOperationalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_moving_state_maps_to_operational_status_moving(): void
    {
        $position = $this->makePosition([
            'movement_state' => 'MOVING',
            'movement_state_updated_at' => now()->subMinutes(2),
            'speed' => 0.0,
        ]);

        $this->assertSame('Moving', app(FleetStatusService::class)->determineStatus($position));
    }

    public function test_stationary_recent_maps_to_stopped(): void
    {
        $position = $this->makePosition([
            'movement_state' => 'STATIONARY',
            'movement_state_updated_at' => now()->subSeconds(60),
            'speed' => 2.0,
        ]);

        $this->assertSame('Stopped', app(FleetStatusService::class)->determineStatus($position));
    }

    public function test_stationary_prolonged_maps_to_idle(): void
    {
        $position = $this->makePosition([
            'movement_state' => 'STATIONARY',
            'movement_state_updated_at' => now()->subSeconds(240),
            'last_updated_at' => now(),
            'speed' => 2.0,
        ]);

        $service = app(FleetStatusService::class);

        $this->assertSame('Idle', $service->determineStatus($position));
        $this->assertGreaterThanOrEqual(240, $service->stationaryDurationSeconds($position));
    }

    public function test_cached_heartbeat_does_not_reset_stationary_duration_or_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-17T06:00:00+00:00'));
        $trip = Trip::factory()->create();

        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T06:00:00+00:00');
        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T06:00:05+00:00');
        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T06:00:10+00:00');

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();
        $stationaryEnteredAt = $position->movement_state_updated_at;
        $this->assertSame('STATIONARY', $position->movement_state);

        Carbon::setTestNow(Carbon::parse('2026-07-17T06:04:00+00:00'));
        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.7, '2026-07-17T06:04:00+00:00', [
            'gps_fix_timestamp' => '2026-07-17T06:00:10+00:00',
            'gps_fix_age_ms' => 230000,
            'is_cached_fix' => true,
            'speed_source' => 'cached',
        ]);

        $position->refresh();
        $service = app(FleetStatusService::class);

        $this->assertSame('STATIONARY', $position->movement_state);
        $this->assertSame('cached_heartbeat_no_new_evidence', $position->movement_reason);
        $this->assertTrue($position->movement_state_updated_at->equalTo($stationaryEnteredAt));
        $this->assertTrue($position->last_updated_at->greaterThan($stationaryEnteredAt));
        $this->assertGreaterThanOrEqual(230, $service->stationaryDurationSeconds($position));
        $this->assertSame('Idle', $service->determineStatus($position));
    }

    public function test_cached_heartbeat_does_not_change_moving_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-17T06:10:00+00:00'));
        $position = $this->makePosition([
            'movement_state' => 'MOVING',
            'movement_reason' => 'speed_and_displacement_confirmed',
            'movement_state_updated_at' => now()->subMinute(),
            'speed' => 0.8,
        ]);
        $trip = $position->trip;

        GPSLog::create([
            'trip_id' => $trip->id,
            'lat' => $position->lat,
            'lng' => $position->lng,
            'speed' => 0.8,
            'heading' => 90.0,
            'accuracy' => 10.0,
            'timestamp' => now(),
            'received_at' => now(),
            'gps_fix_timestamp' => now()->subMinute(),
            'gps_fix_age_ms' => 60000,
            'is_cached_fix' => true,
            'speed_source' => 'cached',
            'processing_status' => 'processed',
        ]);

        $result = app(\App\Services\MovementClassificationService::class)->classify(GPSLog::latest('id')->first(), $position);

        $this->assertSame('MOVING', $result['movement_state']);
        $this->assertSame('cached_heartbeat_no_new_evidence', $result['movement_reason']);
        $this->assertFalse($result['changed']);
    }

    public function test_offline_detection_still_wins(): void
    {
        config(['fleet.gps.offline_timeout_seconds' => 300]);

        $position = $this->makePosition([
            'movement_state' => 'MOVING',
            'last_updated_at' => now()->subSeconds(301),
        ]);

        $this->assertSame('Offline', app(FleetStatusService::class)->determineStatus($position));
    }

    public function test_unknown_state_falls_back_to_legacy_speed_logic(): void
    {
        $movingFallback = $this->makePosition([
            'movement_state' => 'UNKNOWN',
            'speed' => 1.2,
        ]);
        $stoppedFallback = $this->makePosition([
            'movement_state' => 'UNKNOWN',
            'speed' => 0.2,
        ]);

        $service = app(FleetStatusService::class);

        $this->assertSame('Moving', $service->determineStatus($movingFallback));
        $this->assertSame('Stopped', $service->determineStatus($stoppedFallback));
    }

    public function test_fleet_and_admin_api_operational_payload_parity(): void
    {
        $fleetUser = User::factory()->create(['role' => 'fleet_manager']);
        $adminUser = User::factory()->create(['role' => 'admin']);
        $bus = Bus::factory()->create(['status' => 'active', 'speed' => 0.7]);
        $trip = Trip::factory()->create(['bus_id' => $bus->id, 'status' => 'ongoing']);

        VehiclePosition::create([
            'bus_id' => $bus->id,
            'trip_id' => $trip->id,
            'lat' => 14.5969,
            'lng' => 121.0975,
            'speed' => 0.7,
            'heading' => 90.0,
            'status' => 'Stopped',
            'movement_state' => 'STATIONARY',
            'movement_confidence' => 1.0,
            'movement_reason' => 'repeated_low_displacement',
            'movement_state_updated_at' => now()->subSeconds(90),
            'last_updated_at' => now(),
        ]);

        $fleetBus = collect($this->actingAs($fleetUser)->getJson('/fleet/api/bus-gps-positions')->assertOk()->json('buses'))
            ->firstWhere('id', $bus->id);
        $adminBus = collect($this->actingAs($adminUser)->getJson(route('admin.api.fleet-data'))->assertOk()->json('buses'))
            ->firstWhere('id', $bus->id);

        foreach (['movement_state', 'operational_status', 'movement_state_updated_at', 'stationary_duration_seconds'] as $key) {
            $this->assertArrayHasKey($key, $fleetBus);
            $this->assertArrayHasKey($key, $adminBus);
        }

        $this->assertSame('STATIONARY', $fleetBus['movement_state']);
        $this->assertSame($fleetBus['movement_state'], $adminBus['movement_state']);
        $this->assertSame($fleetBus['operational_status'], $adminBus['operational_status']);
        $this->assertEquals($fleetBus['stationary_duration_seconds'], $adminBus['stationary_duration_seconds']);
        $this->assertEquals($fleetBus['speed_kmh'], $adminBus['speed_kmh']);
    }

    public function test_fleet_and_admin_ui_show_zero_for_stationary(): void
    {
        $fleetView = file_get_contents(resource_path('views/fleet/monitor/index.blade.php'));
        $adminJs = file_get_contents(public_path('js/admin-dashboard/dashboard-data.js'));

        $this->assertStringContainsString("toUpperCase() === 'STATIONARY'", $fleetView);
        $this->assertStringContainsString('formatDisplaySpeedKmh(fresh.speed_kmh ?? 0, fresh.movement_state ?? null)', $fleetView);
        $this->assertStringContainsString('statusLabelFromOperationalStatus', $fleetView);
        $this->assertStringContainsString("toUpperCase() === 'STATIONARY'", $adminJs);
        $this->assertStringContainsString('normalizeDisplaySpeedKmh(bus.speed_kmh ?? bus.speed ?? 0, bus.movement_state ?? null)', $adminJs);
        $this->assertStringContainsString('operationalStatus: bus.operational_status ?? bus.status ?? null', $adminJs);
    }

    private function makePosition(array $overrides = []): VehiclePosition
    {
        $bus = Bus::factory()->create(['status' => 'active']);
        $trip = Trip::factory()->create(['bus_id' => $bus->id, 'status' => 'ongoing']);

        return VehiclePosition::create(array_merge([
            'bus_id' => $bus->id,
            'trip_id' => $trip->id,
            'lat' => 14.5969,
            'lng' => 121.0975,
            'speed' => 0.0,
            'heading' => 90.0,
            'status' => 'Unknown',
            'movement_state' => 'UNKNOWN',
            'movement_confidence' => null,
            'movement_reason' => null,
            'movement_state_updated_at' => null,
            'last_updated_at' => now(),
        ], $overrides));
    }

    private function postTelemetry(Trip $trip, float $lat, float $lng, float $speed, string $timestamp, array $overrides = []): void
    {
        $payload = array_merge([
            'lat' => $lat,
            'lng' => $lng,
            'speed' => $speed,
            'heading' => 90.0,
            'accuracy' => 10.0,
            'timestamp' => $timestamp,
            'gps_fix_timestamp' => $timestamp,
            'gps_fix_age_ms' => 0,
            'is_cached_fix' => false,
            'speed_source' => 'native',
        ], $overrides);

        $this->postJson(route('api.driver.location', $trip->id), $payload)
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
