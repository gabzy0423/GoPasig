<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteCorridor extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'buffer_width',
        'source_type',
        'measurement_method',
        'geometry',
    ];

    protected $casts = [
        'buffer_width' => 'float',
        'geometry' => 'array',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }
}
