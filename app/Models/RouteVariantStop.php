<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteVariantStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_variant_id',
        'canonical_stop_id',
        'name',
        'lat',
        'lng',
        'radius_meters',
        'sequence',
        'stop_type',
        'coordinate_status',
        'coordinate_source',
        'coordinates_verified_at',
        'coordinates_verified_by_user_id',
        'coordinate_notes',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'radius_meters' => 'integer',
        'sequence' => 'integer',
        'coordinates_verified_at' => 'datetime',
    ];

    public function routeVariant()
    {
        return $this->belongsTo(RouteVariant::class);
    }

    public function canonicalStop()
    {
        return $this->belongsTo(Stop::class, 'canonical_stop_id');
    }
}

