<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripPassengerEvent extends Model
{
    use HasFactory;

    public const TYPE_BOARDED = 'boarded';
    public const TYPE_ALIGHTED = 'alighted';

    protected $fillable = [
        'trip_id',
        'driver_id',
        'bus_id',
        'route_id',
        'event_type',
        'passenger_delta',
        'onboard_after',
        'recorded_at',
    ];

    protected $casts = [
        'passenger_delta' => 'integer',
        'onboard_after' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }
}
