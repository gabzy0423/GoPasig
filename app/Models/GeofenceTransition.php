<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeofenceTransition extends Model
{
    use HasFactory;

    protected $fillable = [
        'bus_id',
        'trip_id',
        'geofence_id',
        'entered_at',
        'exited_at',
        'duration_seconds',
    ];

    protected $casts = [
        'entered_at' => 'datetime',
        'exited_at' => 'datetime',
        'duration_seconds' => 'integer',
    ];

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function geofence()
    {
        return $this->belongsTo(Geofence::class);
    }
}
