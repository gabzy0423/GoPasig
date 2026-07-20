<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Route extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'color', 'description', 'polyline_coordinates', 'geometry_version', 'status', 'travel_time_minutes', 'delay_threshold_minutes', 'min_speed', 'max_speed', 'target_on_time_rate', 'target_headway_minutes', 'min_buses_required'];

    protected $casts = [
        'polyline_coordinates' => 'array',
    ];

    public static function boot()
    {
        parent::boot();

        static::saved(function ($route) {
            \Illuminate\Support\Facades\Cache::forget('routes_all');
            try {
                app(\App\Repositories\Contracts\RouteGeometryRepositoryInterface::class)->clearAll($route->id);
            } catch (\Exception $e) {}
        });

        static::deleted(function ($route) {
            \Illuminate\Support\Facades\Cache::forget('routes_all');
            try {
                app(\App\Repositories\Contracts\RouteGeometryRepositoryInterface::class)->clearAll($route->id);
            } catch (\Exception $e) {}
        });
    }

    public static function getAllCached()
    {
        return \Illuminate\Support\Facades\Cache::remember('routes_all', 86400, function () {
            return self::all();
        });
    }

    public function getColorAttribute($value)
    {
        return $value ?: config('brand.route_color_default', '#003F87');
    }

    public function stops()
    {
        return $this->hasMany(Stop::class)->orderBy('sequence');
    }

    public function buses()
    {
        return $this->hasMany(Bus::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class)->orderBy('departure_time');
    }

    public function durations()
    {
        return $this->hasMany(RouteDuration::class);
    }
}
