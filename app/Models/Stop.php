<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stop extends Model
{
    use HasFactory;

    protected $fillable = ['route_id', 'name', 'lat', 'lng', 'radius_meters', 'sequence', 'amenities'];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }
}
