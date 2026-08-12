<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteVariantCorridor extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_variant_id',
        'geometry',
        'geometry_hash',
        'coordinate_count',
        'generated_at',
        'generation_source',
    ];

    protected $casts = [
        'geometry' => 'array',
        'coordinate_count' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function routeVariant()
    {
        return $this->belongsTo(RouteVariant::class);
    }
}
