<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\GeofenceType;

class Geofence extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'geometry',
        'radius',
        'priority',
        'lat',
        'lng',
        'status',
    ];

    protected $casts = [
        'type' => GeofenceType::class,
        'geometry' => 'array',
        'radius' => 'float',
        'priority' => 'integer',
        'lat' => 'float',
        'lng' => 'float',
    ];
}
