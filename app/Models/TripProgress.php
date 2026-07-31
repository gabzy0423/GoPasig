<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripProgress extends Model
{
    use HasFactory;

    protected $table = 'trip_progresses';

    protected $fillable = [
        'trip_id',
        'current_stop_id',
        'current_route_variant_stop_id',
        'next_stop_id',
        'next_route_variant_stop_id',
        'last_completed_stop_id',
        'last_completed_route_variant_stop_id',
        'completed_stops_count',
        'remaining_stops_count',
        'trip_percentage',
        'route_adherence',
        'current_delay_minutes',
        'upcoming_etas',
    ];

    protected $casts = [
        'current_route_variant_stop_id' => 'integer',
        'next_route_variant_stop_id' => 'integer',
        'last_completed_route_variant_stop_id' => 'integer',
        'completed_stops_count' => 'integer',
        'remaining_stops_count' => 'integer',
        'trip_percentage' => 'float',
        'current_delay_minutes' => 'integer',
        'upcoming_etas' => 'array',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function currentStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'current_stop_id');
    }

    public function currentRouteVariantStop(): BelongsTo
    {
        return $this->belongsTo(RouteVariantStop::class, 'current_route_variant_stop_id');
    }

    public function nextStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'next_stop_id');
    }

    public function nextRouteVariantStop(): BelongsTo
    {
        return $this->belongsTo(RouteVariantStop::class, 'next_route_variant_stop_id');
    }

    public function lastCompletedStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'last_completed_stop_id');
    }

    public function lastCompletedRouteVariantStop(): BelongsTo
    {
        return $this->belongsTo(RouteVariantStop::class, 'last_completed_route_variant_stop_id');
    }
}