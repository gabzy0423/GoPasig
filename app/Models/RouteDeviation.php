<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteDeviation extends Model
{
    use HasFactory;

    protected $table = 'route_deviations';

    protected $fillable = [
        'trip_id',
        'lat',
        'lng',
        'distance_meters',
        'severity',
        'detected_at',
        'resolved_at',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'distance_meters' => 'float',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
