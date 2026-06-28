<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class GPSKalmanFilter
{

    private static ?float $Q = null;


    private static ?float $R = null;

    private static function getQ(): float
    {
        if (self::$Q === null) {
            self::$Q = (float) SystemSetting::get('kalman_process_variance', 0.00002);
        }
        return self::$Q;
    }

    private static function getR(): float
    {
        if (self::$R === null) {
            self::$R = (float) SystemSetting::get('kalman_measurement_variance', 0.00015);
        }
        return self::$R;
    }

    /**
     * Smooth raw coordinates using a 1D Kalman Filter.
     *
     * @param int|string $busId
     * @param float $lat
     * @param float $lng
     * @return array ['lat' => float, 'lng' => float]
     */
    public static function smooth($busId, $lat, $lng)
    {
        $lat = (float) $lat;
        $lng = (float) $lng;

        $cacheKey = "bus_kalman_state_{$busId}";

        // Retrieve state from cache. If it doesn't exist, initialize with current measurements.
        $state = Cache::get($cacheKey, [
            'lat' => $lat,
            'lng' => $lng,
            'P_lat' => 1.0,
            'P_lng' => 1.0,
        ]);

        $Q = self::getQ();
        $R = self::getR();

        // --- Latitude Filter ---
        // 1. Predict
        $pred_lat = $state['lat'];
        $pred_P_lat = $state['P_lat'] + $Q;

        // 2. Update
        $K_lat = $pred_P_lat / ($pred_P_lat + $R);
        $new_lat = $pred_lat + $K_lat * ($lat - $pred_lat);
        $new_P_lat = (1 - $K_lat) * $pred_P_lat;

        // --- Longitude Filter ---
        // 1. Predict
        $pred_lng = $state['lng'];
        $pred_P_lng = $state['P_lng'] + $Q;

        // 2. Update
        $K_lng = $pred_P_lng / ($pred_P_lng + $R);
        $new_lng = $pred_lng + $K_lng * ($lng - $pred_lng);
        $new_P_lng = (1 - $K_lng) * $pred_P_lng;

        // Save smoothed state in cache for 1 hour of inactivity
        $newState = [
            'lat' => $new_lat,
            'lng' => $new_lng,
            'P_lat' => $new_P_lat,
            'P_lng' => $new_P_lng,
        ];
        Cache::put($cacheKey, $newState, now()->addHour());

        return [
            'lat' => $new_lat,
            'lng' => $new_lng,
        ];
    }

    /**
     * Compute geographical distance in meters between two lat/lng pairs using the Haversine formula.
     *
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float Distance in meters
     */
    public static function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371000; // Earth's radius in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
