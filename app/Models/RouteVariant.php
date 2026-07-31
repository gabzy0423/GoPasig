<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'direction',
        'origin_name',
        'destination_name',
        'polyline_coordinates',
        'geometry_version',
        'geometry_status',
        'is_default',
    ];

    protected $casts = [
        'polyline_coordinates' => 'array',
        'geometry_version' => 'integer',
        'is_default' => 'boolean',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function stops()
    {
        return $this->hasMany(RouteVariantStop::class)->orderBy('sequence');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function geometryVersions()
    {
        return $this->hasMany(RouteVariantGeometryVersion::class)->latest('id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
    public function serviceSchedules()
    {
        return $this->hasMany(RouteServiceSchedule::class);
    }

    public function activeServiceSchedules()
    {
        return $this->hasMany(RouteServiceSchedule::class)->where('is_active', true);
    }
}

