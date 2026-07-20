<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GPSLog extends Model
{
    use HasFactory;

    protected $table = 'gps_logs';

    protected $fillable = [
        'trip_id',
        'lat',
        'lng',
        'speed',
        'heading',
        'accuracy',
        'timestamp',
        'received_at',
        'gps_fix_timestamp',
        'gps_fix_age_ms',
        'is_cached_fix',
        'speed_source',
        'processing_status',
        'processed_at',
        'filtered_lat',
        'filtered_lng',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'speed' => 'float',
        'heading' => 'float',
        'accuracy' => 'float',
        'timestamp' => 'datetime',
        'received_at' => 'datetime',
        'gps_fix_timestamp' => 'datetime',
        'gps_fix_age_ms' => 'integer',
        'is_cached_fix' => 'boolean',
        'processed_at' => 'datetime',
        'filtered_lat' => 'float',
        'filtered_lng' => 'float',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
