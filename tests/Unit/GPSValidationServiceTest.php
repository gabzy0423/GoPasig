<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\GPSLog;
use App\Services\Routing\GPSValidationService;
use App\Services\Contracts\GeospatialServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GPSValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_zero_coordinates()
    {
        $geospatial = app(GeospatialServiceInterface::class);
        $service = new GPSValidationService($geospatial);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid coordinate values");

        $service->validate([
            'lat' => 0.0,
            'lng' => 121.0,
            'timestamp' => now()->toIso8601String(),
            'speed' => 10,
            'heading' => 90
        ]);
    }

    public function test_rejects_high_accuracy_errors()
    {
        config(['fleet.gps.max_accuracy_meters' => 50.0]);

        $geospatial = app(GeospatialServiceInterface::class);
        $service = new GPSValidationService($geospatial);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("GPS signal accuracy");

        $service->validate([
            'lat' => 14.5,
            'lng' => 121.0,
            'accuracy' => 120.0, // > 50.0 limit
            'timestamp' => now()->toIso8601String(),
            'speed' => 10,
            'heading' => 90
        ]);
    }

    public function test_rejects_future_timestamps()
    {
        $geospatial = app(GeospatialServiceInterface::class);
        $service = new GPSValidationService($geospatial);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("GPS timestamp cannot be in the future");

        $service->validate([
            'lat' => 14.5,
            'lng' => 121.0,
            'timestamp' => now()->addMinutes(10)->toIso8601String(),
            'speed' => 10,
            'heading' => 90
        ]);
    }

    public function test_rejects_out_of_order_timestamps()
    {
        $geospatial = app(GeospatialServiceInterface::class);
        $service = new GPSValidationService($geospatial);

        $lastLog = new GPSLog([
            'lat' => 14.5,
            'lng' => 121.0,
            'timestamp' => now()
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Out-of-order or duplicate GPS timestamp");

        $service->validate([
            'lat' => 14.5001,
            'lng' => 121.0001,
            'timestamp' => now()->subMinutes(2)->toIso8601String(),
            'speed' => 10,
            'heading' => 90
        ], $lastLog);
    }
    public function test_accepts_small_stationary_drift_with_chronological_utc_timestamps()
    {
        $service = new GPSValidationService(app(GeospatialServiceInterface::class));
        $lastLog = new GPSLog([
            'lat' => 14.5969587,
            'lng' => 121.0976005,
            'timestamp' => '2026-07-16 15:11:43',
        ]);

        $service->validate([
            'gps_log_id' => 2,
            'trip_id' => 18,
            'bus_id' => 3,
            'lat' => 14.5969526,
            'lng' => 121.0976038,
            'speed' => 0,
            'timestamp' => '2026-07-16T15:11:46+00:00',
        ], $lastLog);

        $this->assertTrue(true);
    }

    public function test_accepts_same_coordinates_with_newer_timestamp()
    {
        $service = new GPSValidationService(app(GeospatialServiceInterface::class));
        $lastLog = new GPSLog(['lat' => 14.5969, 'lng' => 121.0975, 'timestamp' => '2026-07-16T15:11:43+00:00']);

        $service->validate([
            'lat' => 14.5969,
            'lng' => 121.0975,
            'speed' => 0,
            'timestamp' => '2026-07-16T15:11:46+00:00',
        ], $lastLog);

        $this->assertTrue(true);
    }

    public function test_rejects_same_coordinates_with_same_timestamp_as_duplicate_packet()
    {
        $service = new GPSValidationService(app(GeospatialServiceInterface::class));
        $lastLog = new GPSLog(['lat' => 14.5969, 'lng' => 121.0975, 'timestamp' => '2026-07-16T15:11:43+00:00']);

        $this->expectExceptionMessage('Duplicate GPS packet');
        $service->validate([
            'lat' => 14.5969,
            'lng' => 121.0975,
            'speed' => 0,
            'timestamp' => '2026-07-16T15:11:43+00:00',
        ], $lastLog);
    }

    public function test_accepts_small_gps_drift_with_newer_timestamp()
    {
        $service = new GPSValidationService(app(GeospatialServiceInterface::class));
        $lastLog = new GPSLog(['lat' => 14.5969587, 'lng' => 121.0976005, 'timestamp' => '2026-07-16T15:11:43+00:00']);

        $service->validate([
            'lat' => 14.5969526,
            'lng' => 121.0976038,
            'speed' => 0,
            'timestamp' => '2026-07-16T15:11:46+00:00',
        ], $lastLog);

        $this->assertTrue(true);
    }

    public function test_accepts_moving_coordinates_with_newer_timestamp()
    {
        $service = new GPSValidationService(app(GeospatialServiceInterface::class));
        $lastLog = new GPSLog(['lat' => 14.5969, 'lng' => 121.0975, 'timestamp' => '2026-07-16T15:11:43+00:00']);

        $service->validate([
            'lat' => 14.5975,
            'lng' => 121.0980,
            'speed' => 20,
            'timestamp' => '2026-07-16T15:11:53+00:00',
        ], $lastLog);

        $this->assertTrue(true);
    }
}


