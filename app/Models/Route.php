<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Route extends Model
{
    use HasFactory, SoftDeletes;

    private const CANONICAL_PRODUCTION_ROUTE_NAMES = [
        'Route 2',
        'Route 3',
        'Route 4',
    ];

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

    public static function canonicalProductionNames(): array
    {
        return self::CANONICAL_PRODUCTION_ROUTE_NAMES;
    }

    public function isCanonicalProduction(): bool
    {
        return in_array($this->name, self::canonicalProductionNames(), true);
    }

    public static function getCanonicalProductionCached()
    {
        $order = array_flip(self::canonicalProductionNames());

        return self::getAllCached()
            ->whereIn('name', self::canonicalProductionNames())
            ->whereNotIn('status', ['inactive', 'Inactive'])
            ->sortBy(fn (Route $route) => $order[$route->name] ?? PHP_INT_MAX)
            ->values();
    }

    public function scopeCanonicalProduction($query)
    {
        $names = self::canonicalProductionNames();
        $case = 'case ' . collect($names)
            ->map(fn ($name, $index) => 'when name = ? then ' . $index)
            ->implode(' ') . ' else 999 end';

        return $query
            ->whereIn('name', $names)
            ->orderByRaw($case, $names);
    }

    public function scopePublicCommuterVisible($query)
    {
        return $query->canonicalProduction()
            ->whereNotIn('status', ['inactive', 'Inactive']);
    }

    public function scopePublicCommuterActiveService($query)
    {
        return $query->canonicalProduction()
            ->whereNotIn('status', ['suspended', 'inactive', 'Suspended', 'Inactive']);
    }

    public function getColorAttribute($value)
    {
        return $value ?: config('brand.route_color_default', '#003F87');
    }

    public function stops()
    {
        return $this->hasMany(Stop::class)->orderBy('sequence');
    }

    public function variants()
    {
        return $this->hasMany(RouteVariant::class);
    }

    public function defaultVariant()
    {
        return $this->hasOne(RouteVariant::class)->where('is_default', true);
    }

    public function buses()
    {
        return $this->hasMany(Bus::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class)->orderBy('departure_time');
    }
    public function serviceSchedules()
    {
        return $this->hasMany(RouteServiceSchedule::class);
    }

    public function activeServiceSchedules()
    {
        return $this->hasMany(RouteServiceSchedule::class)->where('is_active', true);
    }

    public function durations()
    {
        return $this->hasMany(RouteDuration::class);
    }

}
