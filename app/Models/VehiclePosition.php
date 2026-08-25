<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehiclePosition extends Model
{
    use HasFactory;

    protected $table = 'vehicle_positions';

    protected $fillable = [
        'bus_id',
        'trip_id',
        'lat',
        'lng',
        'heading',
                'display_heading',
        'heading_source',
        'heading_updated_at',
        'speed',
        'status',
        'movement_state',
        'movement_confidence',
        'movement_reason',
        'movement_state_updated_at',
        'movement_positive_samples',
        'movement_negative_samples',
        'gps_quality_state',
        'gps_quality_reason',
        'gps_quality_updated_at',
        'gps_fix_age_seconds',
        'last_gps_fix_at',
        'last_updated_at',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'heading' => 'float',
                'display_heading' => 'float',
        'heading_updated_at' => 'datetime',
        'speed' => 'float',
        'movement_confidence' => 'float',
        'movement_state_updated_at' => 'datetime',
        'movement_positive_samples' => 'integer',
        'movement_negative_samples' => 'integer',
        'gps_quality_updated_at' => 'datetime',
        'gps_fix_age_seconds' => 'integer',
        'last_gps_fix_at' => 'datetime',
        'last_updated_at' => 'datetime',
    ];

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}



