<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Trip;
use App\Models\VehiclePosition;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LiveGpsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_gps_ingestion_api_processes_live_telemetry_synchronously()
    {
        $trip = Trip::factory()->create();

        $response = $this->postJson(route('api.driver.location', $trip->id), [
            'lat' => 14.5764,
            'lng' => 121.0851,
            'speed' => 15.5,
            'heading' => 180,
            'accuracy' => 10.0,
            'timestamp' => now()->toIso8601String()
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['log_id', 'processing_ms']);

        $this->assertDatabaseHas('gps_logs', [
            'trip_id' => $trip->id,
            'lat' => 14.5764,
            'accuracy' => 10.0,
            'processing_status' => 'processed'
        ]);

        $position = VehiclePosition::where('bus_id', $trip->bus_id)->first();
        $this->assertNotNull($position);
        $this->assertEquals($trip->id, $position->trip_id);
        $this->assertEquals(14.5764, round((float) $position->lat, 4));
        $this->assertEquals(121.0851, round((float) $position->lng, 4));
    }

    public function test_bus_gps_positions_api_returns_correct_presentation_payload()
    {
        $user = \App\Models\User::factory()->create(['role' => 'fleet_manager']);
        $route = \App\Models\Route::create([
            'name' => 'Route Gold',
            'status' => 'active'
        ]);
        $bus = \App\Models\Bus::factory()->create([
            'plate_number' => 'PAS-999',
            'status' => 'active',
            'lat' => 14.5,
            'lng' => 121.0,
            'speed' => 20,
            'route_id' => $route->id
        ]);

        $driver = \App\Models\Driver::create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'emp_id' => 'EMP-999',
            'license_number' => 'N99-99-999999',
            'license_expiry' => '2028-12-12',
            'status' => 'active',
            'assigned_bus' => 'PAS-999',
            'assigned_route' => $route->id,
        ]);

        $trip = \App\Models\Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'started_at' => now(),
        ]);

        // Create a VehiclePosition record (which serves as the latest state of the vehicle)
        $position = \App\Models\VehiclePosition::create([
            'bus_id' => $bus->id,
            'trip_id' => $trip->id,
            'lat' => 14.55,
            'lng' => 121.05,
            'speed' => 22.0,
            'heading' => 90.0,
            'status' => 'active',
            'corridor_distance' => 12.5,
            'last_updated_at' => now(),
        ]);

        // Create active TripProgress record
        $progress = \App\Models\TripProgress::create([
            'trip_id' => $trip->id,
            'completed_stops_count' => 4,
            'remaining_stops_count' => 2,
            'trip_percentage' => 66.7,
            'route_adherence' => 'On Route',
        ]);

        // Access the API endpoint
        $response = $this->actingAs($user)->getJson('/fleet/api/bus-gps-positions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'buses' => [
                    '*' => [
                        'plate_number',
                        'lat',
                        'lng',
                        'speed',
                        'nearest_stop',
                        'current_fence',
                        'route_adherence',
                        'corridor_distance',
                        'dwell_time_seconds',
                        'trip_id',
                        'coordinate_source',
                        'has_live_telemetry',
                        'state_mismatch',
                        'last_gps_at',
                        'trip_progress' => [
                            'completed_stops',
                            'remaining_stops',
                            'completion_percentage'
                        ]
                    ]
                ],
                'geofences',
                'variant_corridors'
            ]);

        // Assert exact values are returned directly from DB state
        $data = $response->json();
        $this->assertNotEmpty($data['buses']);
        
        $busData = collect($data['buses'])->firstWhere('plate_number', 'PAS-999');
        $this->assertNotNull($busData);
        $this->assertEquals(14.55, $busData['lat']);
        $this->assertEquals(121.05, $busData['lng']);
        $this->assertEquals(22.0, $busData['speed']);
        $this->assertEquals($trip->id, $busData['trip_id']);
        $this->assertEquals('vehicle_position', $busData['coordinate_source']);
        $this->assertTrue($busData['has_live_telemetry']);
        $this->assertFalse($busData['state_mismatch']);
        $this->assertNotNull($busData['last_gps_at']);
        $this->assertEquals('On Route', $busData['route_adherence']);
        $this->assertEquals(12.5, $busData['corridor_distance']);
        $this->assertEquals(4, $busData['trip_progress']['completed_stops']);
        $this->assertEquals(2, $busData['trip_progress']['remaining_stops']);
        $this->assertEquals(66.7, $busData['trip_progress']['completion_percentage']);

        $adminResponse = $this->actingAs($user)->getJson(route('admin.api.fleet-data'));
        $adminResponse->assertStatus(200);
        $adminBus = collect($adminResponse->json('buses'))->firstWhere('plate_number', 'PAS-999');

        $this->assertNotNull($adminBus);
        $this->assertEquals($busData['trip_id'], $adminBus['trip_id']);
        $this->assertEquals($busData['lat'], $adminBus['lat']);
        $this->assertEquals($busData['lng'], $adminBus['lng']);
        $this->assertEquals('vehicle_position', $adminBus['coordinate_source']);
        $this->assertTrue($adminBus['has_live_telemetry']);
        $this->assertFalse($adminBus['state_mismatch']);
        $this->assertNotNull($adminBus['last_gps_at']);
    }

    public function test_sequential_utc_packets_remain_processed_after_persistence()
    {
        $trip = Trip::factory()->create();

        foreach ([
            ['lat' => 14.5969587, 'lng' => 121.0976005, 'timestamp' => '2026-07-16T15:11:43+00:00'],
            ['lat' => 14.5969526, 'lng' => 121.0976038, 'timestamp' => '2026-07-16T15:11:46+00:00'],
            ['lat' => 14.5969641, 'lng' => 121.0976050, 'timestamp' => '2026-07-16T15:11:52+00:00'],
            ['lat' => 14.5969638, 'lng' => 121.0976058, 'timestamp' => '2026-07-16T15:11:55+00:00'],
        ] as $packet) {
            $this->postJson(route('api.driver.location', $trip->id), array_merge($packet, [
                'speed' => 0,
                'heading' => 0,
                'accuracy' => 10,
            ]))->assertStatus(200);
        }

        $this->assertDatabaseCount('gps_logs', 4);
        $this->assertDatabaseMissing('gps_logs', ['processing_status' => 'invalid']);
        $this->assertDatabaseMissing('gps_logs', ['processing_status' => 'pending']);
    }

    public function test_stationary_packet_refreshes_vehicle_position_timestamp()
    {
        $trip = Trip::factory()->create();
        $payload = [
            'lat' => 14.5969,
            'lng' => 121.0975,
            'speed' => 0,
            'heading' => 0,
            'accuracy' => 10,
        ];

        $this->postJson(route('api.driver.location', $trip->id), array_merge($payload, [
            'timestamp' => '2026-07-16T15:11:43+00:00',
        ]))->assertStatus(200);
        $firstPosition = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();

        $this->postJson(route('api.driver.location', $trip->id), array_merge($payload, [
            'timestamp' => '2026-07-16T15:11:46+00:00',
        ]))->assertStatus(200);
        $secondPosition = $firstPosition->fresh();

        $this->assertEquals(14.5969, round((float) $secondPosition->lat, 4));
        $this->assertEquals(121.0975, round((float) $secondPosition->lng, 4));
        $this->assertTrue($secondPosition->last_updated_at->greaterThan($firstPosition->last_updated_at));
    
    }
    public function test_gps_fix_metadata_distinguishes_fresh_fixes_from_cached_heartbeats()
    {
        $trip = Trip::factory()->create();
        $fixTimestamp = '2026-07-17T03:00:00+00:00';
        $lat = 14.5969594;
        $lng = 121.0976008;

        $freshNative = $this->postJson(route('api.driver.location', $trip->id), [
            'lat' => $lat,
            'lng' => $lng,
            'speed' => 0.6908,
            'heading' => 92.0,
            'accuracy' => 20.0,
            'timestamp' => '2026-07-17T03:00:01+00:00',
            'gps_fix_timestamp' => $fixTimestamp,
            'gps_fix_age_ms' => 1000,
            'is_cached_fix' => false,
            'speed_source' => 'native',
        ]);

        $freshNative->assertStatus(200)->assertJsonPath('success', true);
        $firstPosition = VehiclePosition::where('bus_id', $trip->bus_id)->firstOrFail();
        $freshNativeLog = \App\Models\GPSLog::latest('id')->firstOrFail();

        $this->assertFalse($freshNativeLog->is_cached_fix);
        $this->assertEquals('native', $freshNativeLog->speed_source);
        $this->assertEquals(1000, $freshNativeLog->gps_fix_age_ms);
        $this->assertEquals($fixTimestamp, $freshNativeLog->gps_fix_timestamp->toIso8601String());
        $this->assertEquals(0.6908, (float) $freshNativeLog->speed);
        $this->assertEquals(92.0, (float) $freshNativeLog->heading);
        $this->assertEquals(20.0, (float) $freshNativeLog->accuracy);
        $this->assertEquals('processed', $freshNativeLog->processing_status);

        $freshCalculated = $this->postJson(route('api.driver.location', $trip->id), [
            'lat' => 14.5969600,
            'lng' => 121.0976012,
            'speed' => 0.0,
            'heading' => 0.0,
            'accuracy' => 21.0,
            'timestamp' => '2026-07-17T03:00:03+00:00',
            'gps_fix_timestamp' => '2026-07-17T03:00:03+00:00',
            'gps_fix_age_ms' => 0,
            'is_cached_fix' => false,
            'speed_source' => 'calculated',
        ]);

        $freshCalculated->assertStatus(200)->assertJsonPath('success', true);
        $freshCalculatedLog = \App\Models\GPSLog::latest('id')->firstOrFail();

        $this->assertFalse($freshCalculatedLog->is_cached_fix);
        $this->assertEquals('calculated', $freshCalculatedLog->speed_source);
        $this->assertEquals(0.0, (float) $freshCalculatedLog->heading);
        $this->assertEquals(0.0, (float) $freshCalculatedLog->speed);

        $cachedHeartbeat = $this->postJson(route('api.driver.location', $trip->id), [
            'lat' => $lat,
            'lng' => $lng,
            'speed' => 0.6908,
            'heading' => 92.0,
            'accuracy' => 20.0,
            'timestamp' => '2026-07-17T03:00:08+00:00',
            'gps_fix_timestamp' => $fixTimestamp,
            'gps_fix_age_ms' => 8000,
            'is_cached_fix' => true,
            'speed_source' => 'cached',
        ]);

        $cachedHeartbeat->assertStatus(200)->assertJsonPath('success', true);
        $cachedLog = \App\Models\GPSLog::latest('id')->firstOrFail();
        $secondPosition = $firstPosition->fresh();

        $this->assertTrue($cachedLog->is_cached_fix);
        $this->assertEquals('cached', $cachedLog->speed_source);
        $this->assertEquals($fixTimestamp, $cachedLog->gps_fix_timestamp->toIso8601String());
        $this->assertEquals(8000, $cachedLog->gps_fix_age_ms);
        $this->assertGreaterThan($freshNativeLog->gps_fix_age_ms, $cachedLog->gps_fix_age_ms);
        $this->assertEquals($lat, (float) $cachedLog->lat);
        $this->assertEquals($lng, (float) $cachedLog->lng);
        $this->assertEquals(0.6908, (float) $cachedLog->speed);
        $this->assertEquals(92.0, (float) $cachedLog->heading);
        $this->assertEquals(20.0, (float) $cachedLog->accuracy);
        $this->assertEquals('processed', $cachedLog->processing_status);
        $this->assertEquals($trip->id, $secondPosition->trip_id);
        $this->assertTrue($secondPosition->last_updated_at->greaterThan($firstPosition->last_updated_at));
    }
}








