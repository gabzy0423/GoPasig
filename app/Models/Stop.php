<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class Stop extends Model
{
    use HasFactory;

    protected $fillable = ['route_id', 'name', 'lat', 'lng', 'radius_meters', 'sequence', 'segment_weight', 'amenities'];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'segment_weight' => 'float',
    ];

    public static function boot()
    {
        parent::boot();

        static::saved(function () {
            \Illuminate\Support\Facades\Cache::forget('stops_all');
        });

        static::deleted(function () {
            \Illuminate\Support\Facades\Cache::forget('stops_all');
        });
    }

    public static function getAllCached()
    {
        return \Illuminate\Support\Facades\Cache::remember('stops_all', 86400, function () {
            return self::all();
        });
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    // -------------------------------------------------------------------------
    // ETA helpers
    // -------------------------------------------------------------------------

    /**
     * Haversine great-circle distance between two lat/lng points, in metres.
     */
    public static function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $c1 = new \App\Services\ValueObjects\Coordinate($lat1, $lng1);
        $c2 = new \App\Services\ValueObjects\Coordinate($lat2, $lng2);
        return app(\App\Services\Contracts\GeospatialServiceInterface::class)->calculateDistance($c1, $c2);
    }

    /**
     * Distribute $totalMinutes across a sequence of stops proportionally to
     * the database-driven segment weights (or fallback to Haversine distance
     * between consecutive stop pairs).
     *
     * Returns an array of cumulative minute-offsets from the first stop,
     * indexed 0 … (n-1).  Index 0 is always 0 (departure stop).
     *
     * Example for a 3-stop route where segment 1→2 is twice as long as 2→3:
     *   [0, 20, 30]  (for a 30-minute route)
     *
     * Falls back to distance-based or linear distribution when database weights are missing.
     *
     * @param  Collection|array  $stops         Ordered Stop models (must have lat, lng).
     * @param  float             $totalMinutes  Total route travel time in minutes.
     * @param  float             $fallbackInterval  Minutes per segment used in the fallback.
     * @return float[]  Cumulative offsets in minutes.
     */
    public static function getDistanceWeightedOffsets(
        $stops,
        float $totalMinutes,
        float $fallbackInterval = 5.0
    ): array {
        $stops = collect($stops)->values();
        $count = $stops->count();

        if ($count <= 1) {
            return [0.0];
        }

        // 1. Check if database-driven segment weights are available for all segments
        $segmentWeights = [];
        $hasDatabaseWeights = true;
        for ($i = 1; $i < $count; $i++) {
            $weight = $stops[$i]->segment_weight ?? null;
            if ($weight === null || (float)$weight <= 0) {
                $hasDatabaseWeights = false;
                break;
            }
            $segmentWeights[] = (float) $weight;
        }

        if ($hasDatabaseWeights) {
            $totalWeight = array_sum($segmentWeights);
            if ($totalWeight > 0) {
                $offsets = [0.0];
                $cumulative = 0.0;
                foreach ($segmentWeights as $weight) {
                    $cumulative += ($weight / $totalWeight) * $totalMinutes;
                    $offsets[] = round($cumulative, 2);
                }
                return $offsets;
            }
        }

        // Compute segment distances (Haversine fallback)
        $segmentDistances = [];
        for ($i = 0; $i < $count - 1; $i++) {
            $segmentDistances[] = self::haversineMeters(
                (float) $stops[$i]->lat,  (float) $stops[$i]->lng,
                (float) $stops[$i + 1]->lat, (float) $stops[$i + 1]->lng
            );
        }

        $totalDistance = array_sum($segmentDistances);

        // Build cumulative offsets
        $offsets = [0.0];
        $cumulative = 0.0;

        if ($totalDistance > 0) {
            // Distance-weighted distribution
            foreach ($segmentDistances as $dist) {
                $cumulative += ($dist / $totalDistance) * $totalMinutes;
                $offsets[] = round($cumulative, 2);
            }
        } else {
            // Fallback: all stops same coordinates → linear distribution
            for ($i = 1; $i < $count; $i++) {
                $cumulative += $fallbackInterval;
                $offsets[] = round($cumulative, 2);
            }
        }

        return $offsets;
    }
}
