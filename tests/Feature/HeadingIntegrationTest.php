<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\GPSLog;
use App\Models\Trip;
use App\Models\User;
use App\Models\VehiclePosition;
use App\Services\HeadingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeadingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'fleet.gps_quality.good_accuracy_meters' => 20.0,
            'fleet.gps_quality.degraded_accuracy_meters' => 50.0,
            'fleet.gps_quality.degraded_fix_age_seconds' => 30,
            'fleet.gps_quality.stale_fix_age_seconds' => 300,
            'fleet.heading.derive_min_displacement_meters' => 5.0,
            'fleet.heading.derive_accuracy_noise_multiplier' => 0.35,
            'fleet.heading.max_reliable_accuracy_meters' => 50.0,
            'fleet.movement.moving_confirm_samples' => 2,
        ]);
    }

    public function test_heading_zero_remains_valid_true_north(): void
    {
        [$trip, $position] = $this->movingPosition(['display_heading' => 270.0]);
        $log = $this->log($trip, ['heading' => 0.0]);

        $result = app(HeadingService::class)->resolve($log, $position);

        $this->assertSame(0.0, $result['display_heading']);
        $this->assertSame('native', $result['heading_source']);
    }

    public function test_heading_null_remains_raw_null_in_live_telemetry(): void
    {
        $trip = Trip::factory()->create();

        $this->postJson(route('api.driver.location', $trip->id), [
            'lat' => 14.5969,
            'lng' => 121.0975,
            'speed' => 0.0,
            'heading' => null,
            'accuracy' => 10.0,
            'timestamp' => '2026-07-20T01:00:00+00:00',
            'gps_fix_timestamp' => '2026-07-20T01:00:00+00:00',
            'gps_fix_age_ms' => 0,
            'is_cached_fix' => false,
            'speed_source' => 'native',
        ])->assertOk()->assertJsonPath('success', true);

        $log = GPSLog::firstOrFail();
        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();

        $this->assertNull($log->heading);
        $this->assertNull($position->heading);
        $this->assertNull($position->display_heading);
        $this->assertSame('unavailable', $position->heading_source);
    }

    public function test_fresh_moving_packet_with_native_heading_updates_display_heading(): void
    {
        $trip = Trip::factory()->create();
        $samples = [
            ['lat' => 14.596900, 'lng' => 121.097500, 'timestamp' => '2026-07-20T01:00:00+00:00'],
            ['lat' => 14.596900, 'lng' => 121.097610, 'timestamp' => '2026-07-20T01:00:05+00:00'],
            ['lat' => 14.596900, 'lng' => 121.097730, 'timestamp' => '2026-07-20T01:00:10+00:00'],
        ];

        foreach ($samples as $sample) {
            $this->postJson(route('api.driver.location', $trip->id), array_merge($sample, [
                'speed' => 2.0,
                'heading' => 90.0,
                'accuracy' => 10.0,
                'gps_fix_timestamp' => $sample['timestamp'],
                'gps_fix_age_ms' => 0,
                'is_cached_fix' => false,
                'speed_source' => 'native',
            ]))->assertOk();
        }

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();

        $this->assertSame('MOVING', $position->movement_state);
        $this->assertEquals(90.0, $position->display_heading);
        $this->assertSame('native', $position->heading_source);
        $this->assertEquals(90.0, $position->heading);
    }

    public function test_cached_heartbeat_does_not_update_heading_evidence(): void
    {
        [$trip, $position] = $this->movingPosition(['display_heading' => 90.0, 'heading_source' => 'native']);
        $log = $this->log($trip, ['heading' => 180.0, 'is_cached_fix' => true, 'speed_source' => 'cached']);

        $result = app(HeadingService::class)->resolve($log, $position);

        $this->assertSame(90.0, $result['display_heading']);
        $this->assertSame('preserved', $result['heading_source']);
    }

    public function test_stationary_packet_does_not_overwrite_reliable_heading(): void
    {
        [$trip, $position] = $this->movingPosition([
            'movement_state' => 'STATIONARY',
            'display_heading' => 90.0,
            'heading_source' => 'native',
        ]);
        $log = $this->log($trip, ['heading' => 180.0]);

        $result = app(HeadingService::class)->resolve($log, $position);

        $this->assertSame(90.0, $result['display_heading']);
        $this->assertSame('preserved', $result['heading_source']);
    }

    public function test_moving_with_null_native_heading_derives_bearing_from_fresh_coordinates(): void
    {
        [$trip, $position] = $this->movingPosition(['display_heading' => null, 'heading_source' => 'unavailable']);
        GPSLog::create([
            'trip_id' => $trip->id,
            'lat' => 14.596900,
            'lng' => 121.097500,
            'speed' => 2.0,
            'heading' => null,
            'accuracy' => 10.0,
            'timestamp' => now()->subSeconds(5),
            'received_at' => now()->subSeconds(5),
            'gps_fix_timestamp' => now()->subSeconds(5),
            'gps_fix_age_ms' => 0,
            'is_cached_fix' => false,
            'speed_source' => 'native',
            'processing_status' => 'processed',
            'filtered_lat' => 14.596900,
            'filtered_lng' => 121.097500,
        ]);
        $log = $this->log($trip, [
            'lat' => 14.596900,
            'lng' => 121.097620,
            'heading' => null,
            'filtered_lat' => 14.596900,
            'filtered_lng' => 121.097620,
        ]);

        $result = app(HeadingService::class)->resolve($log, $position);

        $this->assertEqualsWithDelta(90.0, $result['display_heading'], 3.0);
        $this->assertSame('derived', $result['heading_source']);
    }

    public function test_no_derived_heading_from_insignificant_gps_jitter(): void
    {
        [$trip, $position] = $this->movingPosition(['display_heading' => null, 'heading_source' => 'unavailable']);
        GPSLog::create([
            'trip_id' => $trip->id,
            'lat' => 14.596900,
            'lng' => 121.097500,
            'speed' => 2.0,
            'heading' => null,
            'accuracy' => 20.0,
            'timestamp' => now()->subSeconds(5),
            'received_at' => now()->subSeconds(5),
            'gps_fix_timestamp' => now()->subSeconds(5),
            'gps_fix_age_ms' => 0,
            'is_cached_fix' => false,
            'speed_source' => 'native',
            'processing_status' => 'processed',
            'filtered_lat' => 14.596900,
            'filtered_lng' => 121.097500,
        ]);
        $log = $this->log($trip, [
            'lat' => 14.596901,
            'lng' => 121.097501,
            'heading' => null,
            'accuracy' => 20.0,
            'filtered_lat' => 14.596901,
            'filtered_lng' => 121.097501,
        ]);

        $result = app(HeadingService::class)->resolve($log, $position);

        $this->assertNull($result['display_heading']);
        $this->assertSame('unavailable', $result['heading_source']);
    }

    public function test_stale_and_blocked_gps_preserve_previous_reliable_heading(): void
    {
        foreach (['STALE', 'BLOCKED'] as $qualityState) {
            [$trip, $position] = $this->movingPosition([
                'display_heading' => 225.0,
                'heading_source' => 'native',
                'gps_quality_state' => $qualityState,
            ]);
            $log = $this->log($trip, ['heading' => 45.0]);

            $result = app(HeadingService::class)->resolve($log, $position);

            $this->assertSame(225.0, $result['display_heading']);
            $this->assertSame('preserved', $result['heading_source']);
        }
    }

    public function test_circular_heading_behavior_handles_north_wraparound(): void
    {
        $service = app(HeadingService::class);
        [$trip, $position] = $this->movingPosition(['display_heading' => 359.0]);
        $log = $this->log($trip, ['heading' => 1.0]);

        $this->assertEquals(2.0, $service->angularDifference(359.0, 1.0));
        $result = $service->resolve($log, $position);
        $this->assertSame(1.0, $result['display_heading']);
    }

    public function test_fleet_and_admin_api_heading_payload_parity(): void
    {
        $fleetUser = User::factory()->create(['role' => 'dispatcher']);
        $adminUser = User::factory()->create(['role' => 'admin']);
        $bus = Bus::factory()->create(['status' => 'active']);
        $trip = Trip::factory()->create(['bus_id' => $bus->id, 'status' => 'ongoing']);
        VehiclePosition::create([
            'bus_id' => $bus->id,
            'trip_id' => $trip->id,
            'lat' => 14.5969,
            'lng' => 121.0975,
            'speed' => 1.5,
            'heading' => null,
            'display_heading' => 90.0,
            'heading_source' => 'derived',
            'heading_updated_at' => Carbon::parse('2026-07-20T01:00:00+00:00'),
            'status' => 'Moving',
            'movement_state' => 'MOVING',
            'movement_state_updated_at' => now(),
            'gps_quality_state' => 'GOOD',
            'last_updated_at' => now(),
        ]);

        $fleetBus = collect($this->actingAs($fleetUser)->getJson('/fleet/api/bus-gps-positions')->assertOk()->json('buses'))
            ->firstWhere('id', $bus->id);
        $adminBus = collect($this->actingAs($adminUser)->getJson(route('admin.api.fleet-data'))->assertOk()->json('buses'))
            ->firstWhere('id', $bus->id);

        foreach (['heading', 'display_heading', 'heading_source', 'heading_updated_at'] as $key) {
            $this->assertArrayHasKey($key, $fleetBus);
            $this->assertArrayHasKey($key, $adminBus);
            $this->assertEquals($fleetBus[$key], $adminBus[$key]);
        }

        $this->assertNull($fleetBus['heading']);
        $this->assertSame(90.0, (float) $fleetBus['display_heading']);
        $this->assertSame('derived', $fleetBus['heading_source']);
    }

    public function test_fleet_and_admin_marker_rotation_use_display_heading(): void
    {
        $fleetMonitor = file_get_contents(resource_path('views/fleet/monitor/index.blade.php'));
        $adminMap = file_get_contents(public_path('js/admin-dashboard/fleet-map.js'));

        $this->assertStringContainsString('fresh.display_heading', $fleetMonitor);
        $this->assertStringContainsString('bus.displayHeading', $fleetMonitor);
        $this->assertStringContainsString('rotate(${displayHeading}deg)', $fleetMonitor);
        $this->assertStringContainsString('bus.displayHeading', $adminMap);
        $this->assertStringContainsString('rotate(${displayHeading}deg)', $adminMap);
    }

    private function movingPosition(array $overrides = []): array
    {
        $bus = Bus::factory()->create(['status' => 'active']);
        $trip = Trip::factory()->create(['bus_id' => $bus->id, 'status' => 'ongoing']);
        $position = VehiclePosition::create(array_merge([
            'bus_id' => $bus->id,
            'trip_id' => $trip->id,
            'lat' => 14.5969,
            'lng' => 121.0975,
            'speed' => 1.5,
            'heading' => null,
            'display_heading' => null,
            'heading_source' => 'unavailable',
            'heading_updated_at' => now()->subMinute(),
            'status' => 'Moving',
            'movement_state' => 'MOVING',
            'movement_state_updated_at' => now()->subMinute(),
            'gps_quality_state' => 'GOOD',
            'last_updated_at' => now(),
        ], $overrides));

        return [$trip, $position];
    }

    private function log(Trip $trip, array $overrides = []): GPSLog
    {
        return GPSLog::create(array_merge([
            'trip_id' => $trip->id,
            'lat' => 14.5969,
            'lng' => 121.0975,
            'speed' => 1.5,
            'heading' => 90.0,
            'accuracy' => 10.0,
            'timestamp' => now(),
            'received_at' => now(),
            'gps_fix_timestamp' => now(),
            'gps_fix_age_ms' => 0,
            'is_cached_fix' => false,
            'speed_source' => 'native',
            'processing_status' => 'pending',
        ], $overrides));
    }
}
