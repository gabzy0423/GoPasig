<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommuterTrip extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_token',
        'origin_stop_id',
        'destination_stop_id',
        'route_id',
        'bus_id',
        'status',
        'boarded_at',
        'arrived_at',
    ];

    protected $casts = [
        'boarded_at' => 'datetime',
        'arrived_at' => 'datetime',
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

    public function session()
    {
        return $this->belongsTo(CommuterSession::class, 'session_token', 'session_token');
    }
}
