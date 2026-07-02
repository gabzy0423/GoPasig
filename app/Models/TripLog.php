<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripLog extends Model
{
    use HasFactory;

    protected $table = 'trip_logs';

    protected $fillable = [
        'driver_id',
        'trip_id',
        'bus_id',
        'route_id',
        'started_at',
        'completed_at',
        'passengers',
        'alighted_passengers',
        'peak_passengers',
        'status',
        'is_on_time',
        'delay_minutes',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'passengers' => 'integer',
        'alighted_passengers' => 'integer',
        'peak_passengers' => 'integer',
        'is_on_time' => 'boolean',
        'delay_minutes' => 'integer',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function getTripDurationMinutesAttribute(): int
    {
        if (!$this->started_at || !$this->completed_at) {
            return 0;
        }
        return (int) abs($this->started_at->diffInMinutes($this->completed_at));
    }

    public function getOccupancyRateAttribute(): float
    {
        if (!$this->bus || $this->bus->capacity === 0) {
            return 0;
        }
        return round(($this->peak_passengers / $this->bus->capacity) * 100, 1);
    }

    public function getEfficiencyScoreAttribute(): int
    {
        $score = 0;
        $score += $this->is_on_time ? 50 : max(0, 50 - ($this->delay_minutes * 2));
        $score += min(50, $this->occupancy_rate);
        return min(100, (int) round($score));
    }

    public function scopeForDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeForBus($query, int $busId)
    {
        return $query->where('bus_id', $busId);
    }

    public function scopeForRoute($query, int $routeId)
    {
        return $query->where('route_id', $routeId);
    }

    public function scopeFromDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('completed_at', [$startDate, $endDate]);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('completed_at', [$startDate, $endDate]);
    }

    public function scopeRecent($query, int $days = 30)
    {
        $startDate = \Carbon\Carbon::now()->subDays($days);
        return $query->where('completed_at', '>=', $startDate);
    }

    public function scopeOnTime($query)
    {
        return $query->where('is_on_time', true);
    }

    public function scopeDelayed($query)
    {
        return $query->where('is_on_time', false);
    }

    public static function getDriverStats(int $driverId, int $days = 30): array
    {
        $startDate = \Carbon\Carbon::now()->subDays($days);
        $trips = self::forDriver($driverId)->where('completed_at', '>=', $startDate)->get();

        if ($trips->isEmpty()) {
            return ['driver_id' => $driverId, 'total_trips' => 0, 'total_passengers' => 0, 'on_time_rate' => 0, 'avg_efficiency_score' => 0];
        }

        return [
            'driver_id' => $driverId,
            'total_trips' => $trips->count(),
            'total_passengers' => (int) $trips->sum('peak_passengers'),
            'on_time_rate' => round(($trips->where('is_on_time', true)->count() / $trips->count()) * 100, 1),
            'avg_efficiency_score' => round($trips->avg('efficiency_score'), 1),
        ];
    }

    public static function getRouteStats(int $routeId, int $days = 30): array
    {
        $startDate = \Carbon\Carbon::now()->subDays($days);
        $trips = self::forRoute($routeId)->where('completed_at', '>=', $startDate)->get();

        if ($trips->isEmpty()) {
            return ['route_id' => $routeId, 'total_trips' => 0, 'total_passengers' => 0, 'avg_occupancy_rate' => 0];
        }

        return [
            'route_id' => $routeId,
            'total_trips' => $trips->count(),
            'total_passengers' => (int) $trips->sum('peak_passengers'),
            'avg_occupancy_rate' => round($trips->avg('occupancy_rate'), 1),
        ];
    }

    public static function getBusStats(int $busId, int $days = 30): array
    {
        $startDate = \Carbon\Carbon::now()->subDays($days);
        $trips = self::forBus($busId)->where('completed_at', '>=', $startDate)->get();

        if ($trips->isEmpty()) {
            return ['bus_id' => $busId, 'total_trips' => 0, 'total_passengers' => 0, 'avg_occupancy_rate' => 0];
        }

        return [
            'bus_id' => $busId,
            'total_trips' => $trips->count(),
            'total_passengers' => (int) $trips->sum('peak_passengers'),
            'avg_occupancy_rate' => round($trips->avg('occupancy_rate'), 1),
        ];
    }
}
