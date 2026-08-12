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
        'origin_route_variant_stop_id',
        'destination_stop_id',
        'destination_route_variant_stop_id',
        'route_id',
        'route_variant_id',
        'bus_id',
        'status',
        'is_simulated',
        'boarded_at',
        'arrived_at',
    ];

    protected $casts = [
        'is_simulated' => 'boolean',
        'boarded_at' => 'datetime',
        'arrived_at' => 'datetime',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function routeVariant()
    {
        return $this->belongsTo(RouteVariant::class);
    }

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    public function originStop()
    {
        return $this->belongsTo(Stop::class, 'origin_stop_id');
    }

    public function destinationStop()
    {
        return $this->belongsTo(Stop::class, 'destination_stop_id');
    }

    public function originRouteVariantStop()
    {
        return $this->belongsTo(RouteVariantStop::class, 'origin_route_variant_stop_id');
    }

    public function destinationRouteVariantStop()
    {
        return $this->belongsTo(RouteVariantStop::class, 'destination_route_variant_stop_id');
    }

    public function resolvedOriginStop(): Stop|RouteVariantStop|null
    {
        return $this->originRouteVariantStop ?? $this->originStop;
    }

    public function resolvedDestinationStop(): Stop|RouteVariantStop|null
    {
        return $this->destinationRouteVariantStop ?? $this->destinationStop;
    }

    public function session()
    {
        return $this->belongsTo(CommuterSession::class, 'session_token', 'session_token');
    }
}
