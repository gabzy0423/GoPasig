<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class RouteServiceSchedule extends Model
{
    use HasFactory;

    public const CONFIG_CONTINUOUS = 'continuous';
    public const SOURCE_BENEFICIARY_OFFICIAL = 'beneficiary_official';

    protected $fillable = [
        'route_id',
        'route_variant_id',
        'first_trip_time',
        'last_trip_time',
        'service_configuration',
        'service_days',
        'is_active',
        'source',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'service_days' => 'array',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (RouteServiceSchedule $schedule) {
            $variant = $schedule->routeVariant ?: RouteVariant::find($schedule->route_variant_id);

            if (! $variant) {
                throw new InvalidArgumentException('Route service schedule requires a valid RouteVariant.');
            }

            if ((int) $variant->route_id !== (int) $schedule->route_id) {
                throw new InvalidArgumentException('Route service schedule RouteVariant must belong to the selected Route.');
            }

            if ($schedule->effective_from && $schedule->effective_until) {
                $from = $schedule->effective_from instanceof Carbon ? $schedule->effective_from : Carbon::parse($schedule->effective_from);
                $until = $schedule->effective_until instanceof Carbon ? $schedule->effective_until : Carbon::parse($schedule->effective_until);

                if ($until->lt($from)) {
                    throw new InvalidArgumentException('Route service schedule effective_until must be on or after effective_from.');
                }
            }
        });
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function routeVariant()
    {
        return $this->belongsTo(RouteVariant::class);
    }
}
