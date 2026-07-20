<?php

namespace App\Services\GPS;

use App\Services\GPS\Contracts\PositionFilterInterface;
use App\Services\Contracts\KalmanFilterServiceInterface;
use App\Services\ValueObjects\Coordinate;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class KalmanFilterService implements PositionFilterInterface, KalmanFilterServiceInterface
{
    private ?float $Q = null;
    private ?float $R = null;

    private function getQ(): float
    {
        if ($this->Q === null) {
            $this->Q = (float) SystemSetting::get('kalman_process_variance', 0.00002);
        }
        return $this->Q;
    }

    private function getR(): float
    {
        if ($this->R === null) {
            $this->R = (float) SystemSetting::get('kalman_measurement_variance', 0.00015);
        }
        return $this->R;
    }

    public function filter(int|string $busId, Coordinate $coord, int|string|null $tripId = null): Coordinate
    {
        return $this->smooth($busId, $coord, $tripId);
    }

    public function smooth(int|string $busId, Coordinate $coord, int|string|null $tripId = null): Coordinate
    {
        $lat = $coord->getLatitude();
        $lng = $coord->getLongitude();

        $cacheKey = $tripId === null
            ? "bus_kalman_state_{$busId}"
            : "bus_kalman_state_{$busId}_{$tripId}";

        $state = Cache::get($cacheKey, [
            'lat' => $lat,
            'lng' => $lng,
            'P_lat' => 1.0,
            'P_lng' => 1.0,
        ]);

        $Q = $this->getQ();
        $R = $this->getR();

        // Latitude Filter
        $pred_lat = $state['lat'];
        $pred_P_lat = $state['P_lat'] + $Q;

        $K_lat = $pred_P_lat / ($pred_P_lat + $R);
        $new_lat = $pred_lat + $K_lat * ($lat - $pred_lat);
        $new_P_lat = (1 - $K_lat) * $pred_P_lat;

        // Longitude Filter
        $pred_lng = $state['lng'];
        $pred_P_lng = $state['P_lng'] + $Q;

        $K_lng = $pred_P_lng / ($pred_P_lng + $R);
        $new_lng = $pred_lng + $K_lng * ($lng - $pred_lng);
        $new_P_lng = (1 - $K_lng) * $pred_P_lng;

        $newState = [
            'lat' => $new_lat,
            'lng' => $new_lng,
            'P_lat' => $new_P_lat,
            'P_lng' => $new_P_lng,
        ];
        Cache::put($cacheKey, $newState, now()->addHour());

        return new Coordinate(
            $new_lat,
            $new_lng,
            $coord->getBearing(),
            $coord->getAccuracy(),
            $coord->getSpeed(),
            $coord->getTimestamp()
        );
    }
}