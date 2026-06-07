<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommuterTrip extends Model
{
    use HasFactory;

    protected $fillable = [
        'origin_stop_id',
        'destination_stop_id',
        'route_id',
        'status',
        'boarded_at',
        'arrived_at',
        'timestamp',
    ];

    protected $casts = [
        'boarded_at' => 'datetime',
        'arrived_at' => 'datetime',
        'timestamp' => 'datetime',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function originStop()
    {
        return $this->belongsTo(Stop::class, 'origin_stop_id');
    }

    public function destinationStop()
    {
        return $this->belongsTo(Stop::class, 'destination_stop_id');
    }
}
