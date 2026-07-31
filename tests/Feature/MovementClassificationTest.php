<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\GPSLog;
use App\Models\Trip;
use App\Models\User;
use App\Models\VehiclePosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovementClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'fleet.movement.moving_speed_threshold_mps' => 0.5,
            'fleet.movement.sustained_speed_threshold_mps' => 1.0,
            'fleet.movement.speed_evidence_min_displacement_meters' => 2.0,
            'fleet.movement.stationary_speed_threshold_mps' => 0.3,
            'fleet.movement.min_displacement_meters' => 8.0,
            'fleet.movement.accuracy_noise_multiplier' => 0.5,
            'fleet.movement.max_reliable_accuracy_meters' => 50.0,
            'fleet.movement.moving_confirm_samples' => 2,
            'fleet.movement.stationary_confirm_samples' => 3,
        ]);
    }

    public function test_stationary_noisy_speed_spikes_do_not_immediately_mark_moving(): void
    {
        $trip = Trip::factory()->create();

        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T04:00:00+00:00');
        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T04:00:05+00:00');
        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T04:00:10+00:00');

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();
        $this->assertSame('STATIONARY', $position->movement_state);

        $this->postTelemetry($trip, 14.5969000, 121.0975000, 1.2, '2026-07-17T04:00:15+00:00');

        $position->refresh();
        $this->assertSame('STATIONARY', $position->movement_state);
        $this->assertSame('speed_without_meaningful_displacement', $position->movement_reason);
        $this->assertSame(1.2, GPSLog::latest('id')->firstOrFail()->speed);
        $this->assertSame(0.0, $position->speed);
        $this->assertSame(0.0, $trip->bus->fresh()->speed);
    }

    public function test_cached_heartbeat_with_non_zero_speed_does_not_mark_moving(): void
    {
        $trip = Trip::factory()->create();
        $fixTimestamp = '2026-07-17T04:10:00+00:00';

        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T04:10:00+00:00', ['gps_fix_timestamp' => $fixTimestamp]);
        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T04:10:05+00:00', ['gps_fix_timestamp' => '2026-07-17T04:10:05+00:00']);
        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T04:10:10+00:00', ['gps_fix_timestamp' => '2026-07-17T04:10:10+00:00']);

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();
        $this->assertSame('STATIONARY', $position->movement_state);

        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.7, '2026-07-17T04:10:15+00:00', [
            'gps_fix_timestamp' => $fixTimestamp,
            'gps_fix_age_ms' => 15000,
            'is_cached_fix' => true,
            'speed_source' => 'cached',
        ]);

        $position->refresh();
        $this->assertSame('STATIONARY', $position->movement_state);
        $this->assertSame('cached_heartbeat_no_new_evidence', $position->movement_reason);
        $this->assertSame(0, $position->movement_positive_samples);
        $this->assertSame(0.7, GPSLog::latest('id')->firstOrFail()->speed);
        $this->assertSame(0.0, $position->speed);
        $this->assertSame(0.0, $trip->bus->fresh()->speed);
    }

    public function test_repeated_fresh_movement_evidence_transitions_to_moving(): void
    {
        $trip = Trip::factory()->create();

        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T04:20:00+00:00');
        $this->postTelemetry($trip, 14.5970000, 121.0975000, 1.1, '2026-07-17T04:20:10+00:00');

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();
        $this->assertSame('UNKNOWN', $position->movement_state);
        $this->assertSame(1, $position->movement_positive_samples);

        $this->postTelemetry($trip, 14.5971000, 121.0975000, 1.1, '2026-07-17T04:20:20+00:00');

        $position->refresh();
        $this->assertSame('MOVING', $position->movement_state);
        $this->assertSame('speed_and_displacement_confirmed', $position->movement_reason);
        $this->assertSame(1.1, GPSLog::latest('id')->firstOrFail()->speed);
        $this->assertSame(1.1, $position->speed);
        $this->assertSame(1.1, $trip->bus->fresh()->speed);
    }

    public function test_repeated_fresh_low_movement_evidence_transitions_to_stationary(): void
    {
        $trip = Trip::factory()->create();

        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T04:30:00+00:00');
        $this->postTelemetry($trip, 14.5969001, 121.0975000, 0.1, '2026-07-17T04:30:05+00:00');
        $this->postTelemetry($trip, 14.5969002, 121.0975000, 0.1, '2026-07-17T04:30:10+00:00');

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();
        $this->assertSame('STATIONARY', $position->movement_state);
        $this->assertSame('repeated_low_displacement', $position->movement_reason);
    }

    public function test_poor_accuracy_small_displacement_does_not_count_as_reliable_movement(): void
    {
        config(['fleet.gps.max_accuracy_meters' => 120.0]);

        $trip = Trip::factory()->create();

        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T04:40:00+00:00', ['accuracy' => 80.0]);
        $this->postTelemetry($trip, 14.5969500, 121.0975000, 0.8, '2026-07-17T04:40:10+00:00', ['accuracy' => 80.0]);

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();
        $this->assertNotSame('MOVING', $position->movement_state);
        $this->assertSame('poor_accuracy_low_displacement', $position->movement_reason);
    }

    public function test_clear_displacement_and_valid_speed_transitions_to_moving(): void
    {
        $trip = Trip::factory()->create();

        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T04:50:00+00:00');
        $this->postTelemetry($trip, 14.5970200, 121.0975000, 1.4, '2026-07-17T04:50:10+00:00');
        $this->postTelemetry($trip, 14.5971500, 121.0975000, 1.4, '2026-07-17T04:50:20+00:00');

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();
        $this->assertSame('MOVING', $position->movement_state);
    }

    public function test_moving_does_not_flicker_to_stationary_from_one_noisy_sample(): void
    {
        $trip = Trip::factory()->create();

        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T05:00:00+00:00');
        $this->postTelemetry($trip, 14.5970200, 121.0975000, 1.4, '2026-07-17T05:00:10+00:00');
        $this->postTelemetry($trip, 14.5971500, 121.0975000, 1.4, '2026-07-17T05:00:20+00:00');

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();
        $this->assertSame('MOVING', $position->movement_state);

        $this->postTelemetry($trip, 14.5971500, 121.0975000, 0.0, '2026-07-17T05:00:25+00:00');

        $position->refresh();
        $this->assertSame('MOVING', $position->movement_state);
        $this->assertSame(1, $position->movement_negative_samples);
    }

    public function test_stationary_does_not_flicker_to_moving_from_one_gps_spike(): void
    {
        $trip = Trip::factory()->create();

        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T05:10:00+00:00');
        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T05:10:05+00:00');
        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T05:10:10+00:00');

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();
        $this->assertSame('STATIONARY', $position->movement_state);

        $this->postTelemetry($trip, 14.5970200, 121.0975000, 1.4, '2026-07-17T05:10:20+00:00');

        $position->refresh();
        $this->assertSame('STATIONARY', $position->movement_state);
        $this->assertSame(1, $position->movement_positive_samples);
    }

    public function test_trip_49_slow_movement_sequence_transitions_stationary_to_moving(): void
    {
        $trip = Trip::factory()->create();

        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T06:17:00+00:00', ['accuracy' => 25.0]);
        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T06:17:05+00:00', ['accuracy' => 25.0]);
        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T06:17:10+00:00', ['accuracy' => 25.0]);

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();
        $this->assertSame('STATIONARY', $position->movement_state);

        $this->postTelemetry($trip, 14.5969755, 121.0975748, 1.471, '2026-07-17T06:17:30+00:00', ['accuracy' => 27.2]);
        $position->refresh();
        $this->assertSame('STATIONARY', $position->movement_state);
        $this->assertSame(1, $position->movement_positive_samples);

        $this->postTelemetry($trip, 14.5970122, 121.0974989, 1.677, '2026-07-17T06:17:33+00:00', ['accuracy' => 28.4]);

        $position->refresh();
        $this->assertSame('MOVING', $position->movement_state);
        $this->assertSame('speed_and_displacement_confirmed', $position->movement_reason);
        $this->assertSame(2, $position->movement_positive_samples);
    }

    public function test_isolated_high_speed_without_displacement_does_not_trigger_moving(): void
    {
        $trip = Trip::factory()->create();

        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T06:30:00+00:00');
        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T06:30:05+00:00');
        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T06:30:10+00:00');

        $this->postTelemetry($trip, 14.5969000, 121.0975000, 2.0, '2026-07-17T06:30:15+00:00', ['accuracy' => 20.0]);

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();
        $this->assertSame('STATIONARY', $position->movement_state);
        $this->assertSame(0, $position->movement_positive_samples);
        $this->assertSame('speed_without_meaningful_displacement', $position->movement_reason);
    }

    public function test_repeated_stationary_gps_drift_does_not_trigger_moving(): void
    {
        $trip = Trip::factory()->create();

        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T06:40:00+00:00', ['accuracy' => 25.0]);
        $this->postTelemetry($trip, 14.5969050, 121.0975000, 0.2, '2026-07-17T06:40:05+00:00', ['accuracy' => 25.0]);
        $this->postTelemetry($trip, 14.5969100, 121.0975000, 0.2, '2026-07-17T06:40:10+00:00', ['accuracy' => 25.0]);
        $this->postTelemetry($trip, 14.5969150, 121.0975000, 0.2, '2026-07-17T06:40:15+00:00', ['accuracy' => 25.0]);

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();
        $this->assertSame('STATIONARY', $position->movement_state);
        $this->assertSame(0, $position->movement_positive_samples);
    }

    public function test_cached_heartbeat_preserves_movement_counters(): void
    {
        $trip = Trip::factory()->create();

        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T06:50:00+00:00', ['accuracy' => 20.0]);
        $this->postTelemetry($trip, 14.5969500, 121.0975000, 1.2, '2026-07-17T06:50:05+00:00', ['accuracy' => 20.0]);

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();
        $this->assertSame(1, $position->movement_positive_samples);

        $this->postTelemetry($trip, 14.5969500, 121.0975000, 1.2, '2026-07-17T06:50:10+00:00', [
            'accuracy' => 20.0,
            'gps_fix_timestamp' => '2026-07-17T06:50:05+00:00',
            'gps_fix_age_ms' => 5000,
            'is_cached_fix' => true,
            'speed_source' => 'cached',
        ]);

        $position->refresh();
        $this->assertSame(1, $position->movement_positive_samples);
        $this->assertSame(0, $position->movement_negative_samples);
        $this->assertSame('cached_heartbeat_no_new_evidence', $position->movement_reason);
    }

    public function test_fresh_callback_detection_uses_sequence_and_timestamp_not_coordinate_equality(): void
    {
        $driverView = file_get_contents(resource_path('views/driver/trip/index.blade.php'));

        $this->assertStringContainsString('lastSentGpsFixTimestamp', $driverView);
        $this->assertStringContainsString('lastSentGpsFixSequence !== lastGpsFixSequence', $driverView);
        $this->assertStringContainsString('lastSentGpsFixTimestamp !== lastGpsFixTimestamp', $driverView);
        $this->assertStringContainsString('const isCachedFix = !hasUnsentGpsCallback', $driverView);
        $this->assertStringNotContainsString('const isCachedFix = lastDeviceLat', $driverView);
    }

    public function test_moving_can_transition_back_to_stationary_after_repeated_stationary_evidence(): void
    {
        $trip = Trip::factory()->create();

        $this->postTelemetry($trip, 14.5969000, 121.0975000, 0.0, '2026-07-17T07:00:00+00:00');
        $this->postTelemetry($trip, 14.5970200, 121.0975000, 1.4, '2026-07-17T07:00:10+00:00');
        $this->postTelemetry($trip, 14.5971500, 121.0975000, 1.4, '2026-07-17T07:00:20+00:00');

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();
        $this->assertSame('MOVING', $position->movement_state);

        $this->postTelemetry($trip, 14.5971500, 121.0975000, 0.0, '2026-07-17T07:00:25+00:00');
        $this->postTelemetry($trip, 14.5971500, 121.0975000, 0.0, '2026-07-17T07:00:30+00:00');
        $this->postTelemetry($trip, 14.5971500, 121.0975000, 0.0, '2026-07-17T07:00:35+00:00');

        $position->refresh();
        $this->assertSame('STATIONARY', $position->movement_state);
        $this->assertSame('repeated_low_displacement', $position->movement_reason);
        $this->assertSame(0.0, $position->speed);
        $this->assertSame(0.0, $trip->bus->fresh()->speed);
    }
    public function test_fleet_and_admin_api_movement_state_parity(): void
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
            'movement_state_updated_at' => now(),
            'last_updated_at' => now(),
        ]);

        $fleetBus = collect($this->actingAs($fleetUser)->getJson('/fleet/api/bus-gps-positions')->assertOk()->json('buses'))
            ->firstWhere('id', $bus->id);
        $adminBus = collect($this->actingAs($adminUser)->getJson(route('admin.api.fleet-data'))->assertOk()->json('buses'))
            ->firstWhere('id', $bus->id);

        $this->assertSame('STATIONARY', $fleetBus['movement_state']);
        $this->assertSame('STATIONARY', $adminBus['movement_state']);
        $this->assertSame($fleetBus['movement_reason'], $adminBus['movement_reason']);
        $this->assertEquals($fleetBus['speed_kmh'], $adminBus['speed_kmh']);
    }

    public function test_stationary_display_speed_override_is_wired_in_both_map_frontends(): void
    {
        $fleetView = file_get_contents(resource_path('views/fleet/monitor/index.blade.php'));
        $adminJs = file_get_contents(public_path('js/admin-dashboard/dashboard-data.js'));

        $this->assertStringContainsString("toUpperCase() === 'STATIONARY'", $fleetView);
        $this->assertStringContainsString('formatDisplaySpeedKmh(fresh.speed_kmh ?? 0, fresh.movement_state ?? null)', $fleetView);
        $this->assertStringContainsString("toUpperCase() === 'STATIONARY'", $adminJs);
        $this->assertStringContainsString('normalizeDisplaySpeedKmh(bus.speed_kmh ?? bus.speed ?? 0, bus.movement_state ?? null)', $adminJs);
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

        $this->assertSame('processed', GPSLog::latest('id')->firstOrFail()->processing_status);
    }
}





