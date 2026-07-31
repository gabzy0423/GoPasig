<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\GPSLog;
use App\Models\Trip;
use App\Models\User;
use App\Models\VehiclePosition;
use App\Services\GpsQualityService;
use App\Services\MovementClassificationService;
use App\Services\Routing\FleetStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GpsQualityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'fleet.gps.offline_timeout_seconds' => 300,
            'fleet.gps_quality.good_accuracy_meters' => 20.0,
            'fleet.gps_quality.degraded_accuracy_meters' => 50.0,
            'fleet.gps_quality.degraded_fix_age_seconds' => 30,
            'fleet.gps_quality.stale_fix_age_seconds' => 300,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_fresh_accurate_gps_fix_is_good(): void
    {
        $trip = Trip::factory()->create();

        $this->postTelemetry($trip, '2026-07-20T01:00:00+00:00', [
            'accuracy' => 10.0,
            'gps_fix_age_ms' => 0,
        ]);

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();

        $this->assertSame('GOOD', $position->gps_quality_state);
        $this->assertSame('gps_fix_recent_and_accurate', $position->gps_quality_reason);
        $this->assertSame(0, $position->gps_fix_age_seconds);
        $this->assertNotNull($position->last_gps_fix_at);
    }

    public function test_fresh_poor_accuracy_gps_fix_is_degraded(): void
    {
        $trip = Trip::factory()->create();

        $this->postTelemetry($trip, '2026-07-20T01:05:00+00:00', [
            'accuracy' => 35.0,
            'gps_fix_age_ms' => 0,
        ]);

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();

        $this->assertSame('DEGRADED', $position->gps_quality_state);
        $this->assertSame('gps_accuracy_degraded', $position->gps_quality_reason);
    }

    public function test_cached_heartbeat_with_aging_physical_fix_becomes_degraded(): void
    {
        $trip = Trip::factory()->create();
        $fixTimestamp = '2026-07-20T01:10:00+00:00';

        $this->postTelemetry($trip, '2026-07-20T01:10:00+00:00', [
            'gps_fix_timestamp' => $fixTimestamp,
            'accuracy' => 10.0,
            'gps_fix_age_ms' => 0,
        ]);

        $this->postTelemetry($trip, '2026-07-20T01:10:45+00:00', [
            'gps_fix_timestamp' => $fixTimestamp,
            'gps_fix_age_ms' => 45000,
            'is_cached_fix' => true,
            'speed_source' => 'cached',
            'accuracy' => 10.0,
        ]);

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();

        $this->assertSame('DEGRADED', $position->gps_quality_state);
        $this->assertSame('gps_fix_age_degraded', $position->gps_quality_reason);
        $this->assertSame(45, $position->gps_fix_age_seconds);
        $this->assertEquals($fixTimestamp, $position->last_gps_fix_at->toIso8601String());
    }

    public function test_extended_physical_fix_age_is_stale_but_not_offline_when_telemetry_is_live(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20T01:21:00+00:00'));
        $trip = Trip::factory()->create();
        $fixTimestamp = '2026-07-20T01:15:00+00:00';

        $this->postTelemetry($trip, '2026-07-20T01:21:00+00:00', [
            'gps_fix_timestamp' => $fixTimestamp,
            'gps_fix_age_ms' => 360000,
            'is_cached_fix' => true,
            'speed_source' => 'cached',
            'accuracy' => 10.0,
        ]);

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();

        $this->assertSame('STALE', $position->gps_quality_state);
        $this->assertSame('gps_fix_age_stale', $position->gps_quality_reason);
        $this->assertSame(360, $position->gps_fix_age_seconds);
        $this->assertNotSame('Offline', app(FleetStatusService::class)->determineStatus($position));
    }

    public function test_cached_heartbeat_does_not_reset_physical_gps_fix_age(): void
    {
        $trip = Trip::factory()->create();
        $fixTimestamp = '2026-07-20T01:25:00+00:00';

        $this->postTelemetry($trip, '2026-07-20T01:25:00+00:00', [
            'gps_fix_timestamp' => $fixTimestamp,
            'gps_fix_age_ms' => 0,
        ]);

        $this->postTelemetry($trip, '2026-07-20T01:27:00+00:00', [
            'gps_fix_timestamp' => $fixTimestamp,
            'gps_fix_age_ms' => 120000,
            'is_cached_fix' => true,
            'speed_source' => 'cached',
        ]);

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();

        $this->assertSame(120, $position->gps_fix_age_seconds);
        $this->assertEquals($fixTimestamp, $position->last_gps_fix_at->toIso8601String());
    }

    public function test_gps_quality_degraded_does_not_force_offline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20T01:30:00+00:00'));
        $trip = Trip::factory()->create();

        $this->postTelemetry($trip, '2026-07-20T01:30:00+00:00', [
            'accuracy' => 40.0,
            'gps_fix_age_ms' => 0,
        ]);

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();

        $this->assertSame('DEGRADED', $position->gps_quality_state);
        $this->assertNotSame('Offline', app(FleetStatusService::class)->determineStatus($position));
    }

    public function test_blocked_state_is_preserved_when_no_new_fix_exists(): void
    {
        $bus = Bus::factory()->create(['status' => 'active']);
        $trip = Trip::factory()->create(['bus_id' => $bus->id, 'status' => 'ongoing']);
        $position = VehiclePosition::create([
            'bus_id' => $bus->id,
            'trip_id' => $trip->id,
            'lat' => 14.5969,
            'lng' => 121.0975,
            'speed' => 0.0,
            'heading' => null,
            'status' => 'Stopped',
            'last_updated_at' => now(),
            'gps_quality_state' => 'BLOCKED',
            'gps_quality_reason' => 'gps_permission_blocked',
        ]);
        $log = GPSLog::create([
            'trip_id' => $position->trip_id,
            'lat' => $position->lat,
            'lng' => $position->lng,
            'speed' => 0.0,
            'heading' => null,
            'accuracy' => null,
            'timestamp' => now(),
            'received_at' => now(),
            'gps_fix_timestamp' => null,
            'gps_fix_age_ms' => null,
            'is_cached_fix' => true,
            'speed_source' => 'cached',
            'processing_status' => 'processed',
        ]);

        $result = app(GpsQualityService::class)->classify($log, $position);

        $this->assertSame('BLOCKED', $result['gps_quality_state']);
        $this->assertSame('gps_permission_blocked', $result['gps_quality_reason']);
    }
    public function test_stale_gps_quality_does_not_create_new_movement_evidence(): void
    {
        $bus = Bus::factory()->create(['status' => 'active']);
        $trip = Trip::factory()->create(['bus_id' => $bus->id, 'status' => 'ongoing']);
        $position = VehiclePosition::create([
            'bus_id' => $bus->id,
            'trip_id' => $trip->id,
            'lat' => 14.5969,
            'lng' => 121.0975,
            'speed' => 1.5,
            'heading' => null,
            'status' => 'Moving',
            'movement_state' => 'MOVING',
            'movement_confidence' => 1.0,
            'movement_reason' => 'speed_and_displacement_confirmed',
            'movement_state_updated_at' => now()->subMinute(),
            'movement_positive_samples' => 2,
            'movement_negative_samples' => 0,
            'gps_quality_state' => 'STALE',
            'gps_quality_reason' => 'gps_fix_age_stale',
            'last_updated_at' => now(),
        ]);
        $log = GPSLog::create([
            'trip_id' => $trip->id,
            'lat' => 14.5975,
            'lng' => 121.0985,
            'speed' => 1.5,
            'heading' => null,
            'accuracy' => 10.0,
            'timestamp' => now(),
            'received_at' => now(),
            'gps_fix_timestamp' => now()->subMinutes(10),
            'gps_fix_age_ms' => 600000,
            'is_cached_fix' => false,
            'speed_source' => 'native',
            'processing_status' => 'pending',
        ]);

        $result = app(MovementClassificationService::class)->classify($log, $position);

        $this->assertSame('MOVING', $result['movement_state']);
        $this->assertSame('stale_gps_quality_no_new_movement_evidence', $result['movement_reason']);
        $this->assertSame(2, $result['movement_positive_samples']);
        $this->assertSame(0, $result['movement_negative_samples']);
        $this->assertFalse($result['changed']);
    }

    public function test_degraded_gps_quality_caps_movement_confidence_without_blocking_classification(): void
    {
        $bus = Bus::factory()->create(['status' => 'active']);
        $trip = Trip::factory()->create(['bus_id' => $bus->id, 'status' => 'ongoing']);
        $position = VehiclePosition::create([
            'bus_id' => $bus->id,
            'trip_id' => $trip->id,
            'lat' => 14.5969,
            'lng' => 121.0975,
            'speed' => 1.5,
            'heading' => null,
            'status' => 'Stopped',
            'movement_state' => 'STATIONARY',
            'movement_confidence' => 1.0,
            'movement_state_updated_at' => now()->subMinute(),
            'movement_positive_samples' => 1,
            'movement_negative_samples' => 0,
            'gps_quality_state' => 'DEGRADED',
            'gps_quality_reason' => 'gps_accuracy_degraded',
            'last_updated_at' => now(),
        ]);
        GPSLog::create([
            'trip_id' => $trip->id,
            'lat' => 14.5969,
            'lng' => 121.0975,
            'speed' => 1.4,
            'heading' => null,
            'accuracy' => 28.0,
            'timestamp' => now()->subSeconds(10),
            'received_at' => now()->subSeconds(10),
            'gps_fix_timestamp' => now()->subSeconds(10),
            'gps_fix_age_ms' => 0,
            'is_cached_fix' => false,
            'speed_source' => 'native',
            'processing_status' => 'processed',
        ]);
        $log = GPSLog::create([
            'trip_id' => $trip->id,
            'lat' => 14.5971,
            'lng' => 121.0978,
            'speed' => 1.5,
            'heading' => null,
            'accuracy' => 28.0,
            'timestamp' => now(),
            'received_at' => now(),
            'gps_fix_timestamp' => now(),
            'gps_fix_age_ms' => 0,
            'is_cached_fix' => false,
            'speed_source' => 'native',
            'processing_status' => 'pending',
        ]);

        $result = app(MovementClassificationService::class)->classify($log, $position);

        $this->assertSame('MOVING', $result['movement_state']);
        $this->assertSame(0.6, $result['movement_confidence']);
        $this->assertSame('DEGRADED', $result['evidence']['gps_quality_state']);
    }


    public function test_fleet_and_admin_api_gps_quality_payload_parity(): void
    {
        $fleetUser = User::factory()->create(['role' => 'fleet_manager']);
        $adminUser = User::factory()->create(['role' => 'admin']);
        $bus = Bus::factory()->create(['status' => 'active']);
        $trip = Trip::factory()->create(['bus_id' => $bus->id, 'status' => 'ongoing']);

        VehiclePosition::create([
            'bus_id' => $bus->id,
            'trip_id' => $trip->id,
            'lat' => 14.5969,
            'lng' => 121.0975,
            'speed' => 0.0,
            'heading' => null,
            'status' => 'Stopped',
            'movement_state' => 'STATIONARY',
            'movement_state_updated_at' => now(),
            'gps_quality_state' => 'DEGRADED',
            'gps_quality_reason' => 'gps_fix_age_degraded',
            'gps_quality_updated_at' => now(),
            'gps_fix_age_seconds' => 45,
            'last_gps_fix_at' => now()->subSeconds(45),
            'last_updated_at' => now(),
        ]);

        $fleetBus = collect($this->actingAs($fleetUser)->getJson('/fleet/api/bus-gps-positions')->assertOk()->json('buses'))
            ->firstWhere('id', $bus->id);
        $adminBus = collect($this->actingAs($adminUser)->getJson(route('admin.api.fleet-data'))->assertOk()->json('buses'))
            ->firstWhere('id', $bus->id);

        foreach (['gps_quality_state', 'gps_quality_reason', 'gps_fix_age_seconds', 'last_gps_fix_at'] as $key) {
            $this->assertArrayHasKey($key, $fleetBus);
            $this->assertArrayHasKey($key, $adminBus);
            $this->assertEquals($fleetBus[$key], $adminBus[$key]);
        }

        $this->assertSame('DEGRADED', $fleetBus['gps_quality_state']);
    }

    private function postTelemetry(Trip $trip, string $timestamp, array $overrides = []): void
    {
        $payload = array_merge([
            'lat' => 14.5969,
            'lng' => 121.0975,
            'speed' => 0.0,
            'heading' => null,
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



