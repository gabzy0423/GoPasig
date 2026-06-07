<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RouteDuration extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'duration_minutes',
        'day_of_week',
        'time_slot',
        'notes',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * Get duration para sa specific route at conditions
     * Kung may specific time slot/day, gamitin yan
     * Otherwise, gamitin ang general duration (NULL values)
     */
    public static function getDuration($routeId, $dayOfWeek = null, $timeSlot = null)
    {
        // Try specific match first (day + time slot)
        if ($dayOfWeek && $timeSlot) {
            $duration = self::where('route_id', $routeId)
                ->where('day_of_week', $dayOfWeek)
                ->where('time_slot', $timeSlot)
                ->first();

            if ($duration) {
                return $duration->duration_minutes;
            }
        }

        // Try day-only match
        if ($dayOfWeek) {
            $duration = self::where('route_id', $routeId)
                ->where('day_of_week', $dayOfWeek)
                ->whereNull('time_slot')
                ->first();

            if ($duration) {
                return $duration->duration_minutes;
            }
        }

        // Try time slot-only match
        if ($timeSlot) {
            $duration = self::where('route_id', $routeId)
                ->whereNull('day_of_week')
                ->where('time_slot', $timeSlot)
                ->first();

            if ($duration) {
                return $duration->duration_minutes;
            }
        }

        // Fall back sa general duration (both NULL)
        $duration = self::where('route_id', $routeId)
            ->whereNull('day_of_week')
            ->whereNull('time_slot')
            ->first();

        return $duration?->duration_minutes ?? 45; // Default 45 minutes
    }
}

