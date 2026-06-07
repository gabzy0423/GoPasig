<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'color', 'description', 'polyline_coordinates', 'status', 'travel_time_minutes'];

    protected $casts = [
        'polyline_coordinates' => 'array',
    ];

    public function stops()
    {
        return $this->hasMany(Stop::class)->orderBy('sequence');
    }

    public function buses()
    {
        return $this->hasMany(Bus::class);
    }

    public function durations()
    {
        return $this->hasMany(RouteDuration::class);
    }
}
