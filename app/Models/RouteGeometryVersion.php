<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteGeometryVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'polyline_coordinates',
        'vertex_count',
        'length_km',
        'label',
        'created_by_user_id',
        'restored_from_version',
    ];

    protected $casts = [
        'polyline_coordinates' => 'array',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
