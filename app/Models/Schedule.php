<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    // Schedule status constants — single source of truth for status strings
    const STATUS_ON_TIME   = 'On time';
    const STATUS_DELAYED   = 'Delayed';
    const STATUS_CANCELLED = 'Cancelled';

    protected $fillable = [
        'route_id',
        'bus_id',
        'driver_id',
        'departure_time',
        'arrival_time',
        'passengers',
        'status',
        'delay_minutes',
        'actual_departure_time',
        'actual_arrival_time',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
