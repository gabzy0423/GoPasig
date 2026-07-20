<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\ValueObjects\Coordinate;
use App\Services\GPS\KalmanFilterService;
use Illuminate\Support\Facades\Cache;

class KalmanFilterServiceTest extends TestCase
{
    use RefreshDatabase;
    public function test_kalman_filter_smooths_jitter()
    {
        $service = new KalmanFilterService();
        $busId = 999;

        // Clear cache first
        Cache::forget("bus_kalman_state_{$busId}");

        // Seeding the filter: first point establishes state
        $c1 = new Coordinate(14.5593, 121.0805);
        $smoothed1 = $service->smooth($busId, $c1);
        $this->assertEquals(14.5593, $smoothed1->getLatitude());

        // Introduce a noisy GPS jump
        $c2 = new Coordinate(14.5650, 121.0850);
        $smoothed2 = $service->smooth($busId, $c2);

        // Smoothed point should be closer to state than raw jump due to inertia
        $this->assertLessThan(14.5650, $smoothed2->getLatitude());
        $this->assertGreaterThan(14.5593, $smoothed2->getLatitude());
    }
    public function test_kalman_state_is_isolated_per_trip()
    {
        $service = new KalmanFilterService();
        $busId = 1001;
        $tripOneId = 501;
        $tripTwoId = 502;

        Cache::forget("bus_kalman_state_{$busId}_{$tripOneId}");
        Cache::forget("bus_kalman_state_{$busId}_{$tripTwoId}");

        $service->smooth($busId, new Coordinate(14.5593, 121.0805), $tripOneId);
        $service->smooth($busId, new Coordinate(14.5700, 121.0900), $tripOneId);

        $newTripFirstPoint = new Coordinate(14.5969, 121.0975);
        $smoothed = $service->smooth($busId, $newTripFirstPoint, $tripTwoId);

        $this->assertEquals(14.5969, round($smoothed->getLatitude(), 4));
        $this->assertEquals(121.0975, round($smoothed->getLongitude(), 4));
        $this->assertTrue(Cache::has("bus_kalman_state_{$busId}_{$tripOneId}"));
        $this->assertTrue(Cache::has("bus_kalman_state_{$busId}_{$tripTwoId}"));
    }
}

