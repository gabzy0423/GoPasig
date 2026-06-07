<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemandThreshold extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'time_slot',
        'day_of_week',
        'threshold_count',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }
}
